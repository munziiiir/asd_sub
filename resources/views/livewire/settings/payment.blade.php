<?php

use App\Models\CustomerUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app.base')] class extends Component {
    public ?CustomerUser $customer = null;

    public string $card_brand = '';
    public string $card_last_four = '';
    public ?int $card_exp_month = null;
    public ?int $card_exp_year = null;

    public string $card_number = '';
    public string $expiry = '';
    public string $cvv = '';

    public bool $editing = false;
    public bool $detectingBrand = false;

    public function mount(): void
    {
        $this->customer = CustomerUser::firstOrCreate(
            ['user_id' => Auth::id()],
            ['name' => Auth::user()?->name, 'email' => Auth::user()?->email]
        );

        $this->card_brand = (string) ($this->customer->card_brand ?? '');
        $this->card_last_four = (string) ($this->customer->card_last_four ?? '');
        $this->card_exp_month = $this->customer->card_exp_month;
        $this->card_exp_year = $this->customer->card_exp_year;
    }

    public function toggleEdit(): void
    {
        if ($this->card_last_four) {
            return;
        }

        $this->editing = ! $this->editing;
        $this->card_number = '';
        $this->expiry = '';
        $this->cvv = '';
        $this->detectingBrand = false;
        $this->card_number = '';
        $this->expiry = '';
        $this->cvv = '';
    }

    public function updatedCardNumber(): void
    {
        $number = preg_replace('/\\D+/', '', $this->card_number);
        $this->card_number = $number;
        $this->detectingBrand = true;
        $this->card_brand = '';

        $brand = $this->detectBrandFromNumber($number);
        $this->card_brand = $brand;
        $this->detectingBrand = false;
    }

    public function saveCard(): void
    {
        $this->validate([
            'card_number' => ['required', 'string', 'regex:/^[0-9]{12,19}$/'],
            'expiry' => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\\/(\\d{2})$/'],
            'cvv' => ['required', 'string', 'regex:/^[0-9]{3,4}$/'],
        ]);

        [$month, $year] = $this->parseExpiry($this->expiry);
        $brand = $this->card_brand ?: $this->detectBrandFromNumber($this->card_number);
        $lastFour = substr($this->card_number, -4);
        $cardNumberHash = Hash::make($this->card_number);
        $cardCvvHash = Hash::make($this->cvv);
        $this->customer?->forceFill([
            'card_brand' => $brand,
            'card_last_four' => $lastFour,
            'card_exp_month' => $month,
            'card_exp_year' => $year,
            'card_number_hash' => Hash::make($this->card_number),
            'card_cvv_hash' => Hash::make($this->cvv),
        ])->save();

        $this->card_brand = $brand;
        $this->card_last_four = $lastFour;
        $this->card_exp_month = $month;
        $this->card_exp_year = $year;

        $this->editing = false;
        $this->card_number = '';
        $this->expiry = '';
        $this->cvv = '';

        $this->dispatch('payment-updated');
    }

    public function clearCard(): void
    {
        $this->card_brand = '';
        $this->card_last_four = '';
        $this->card_exp_month = null;
        $this->card_exp_year = null;

        $this->customer?->forceFill([
            'card_brand' => null,
            'card_last_four' => null,
            'card_exp_month' => null,
            'card_exp_year' => null,
            'card_number_hash' => null,
            'card_cvv_hash' => null,
        ])->save();

        $this->dispatch('payment-updated');
    }

    private function parseExpiry(string $value): array
    {
        [$m, $y] = explode('/', $value);

        return [(int) $m, 2000 + (int) $y];
    }

    private function detectBrandFromNumber(?string $number): string
    {
        $num = $number ?? '';

        $patterns = [
            'Visa' => '/^4[0-9]{6,}$/',
            'Mastercard' => '/^(5[1-5][0-9]{5,}|2[2-7][0-9]{5,})/',
            'American Express' => '/^3[47][0-9]{5,}/',
            'Discover' => '/^6(?:011|5[0-9]{2})[0-9]{3,}/',
            'JCB' => '/^(?:2131|1800|35\\d{3})\\d{11}$/',
            'Diners Club' => '/^3(?:0[0-5]|[68][0-9])[0-9]{4,}/',
        ];

        foreach ($patterns as $brand => $regex) {
            if (preg_match($regex, $num)) {
                return $brand;
            }
        }

        return 'Unknown';
    }

    private function maskedLastFour(): string
    {
        return $this->card_last_four ? '•••• ' . $this->card_last_four : 'Not set';
    }
}; ?>

<x-settings.layout
    :customer="$customer"
    :heading="__('Payment')"
    :subheading="__('Save a card for faster checkouts and invoices')"
>
    <div class="bg-base-100 border border-base-300/60 rounded-2xl p-4 space-y-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-base-content">Saved card</h2>
                <p class="text-sm text-base-content/70">We only keep a fingerprint and hashes for security.</p>
            </div>
            <div class="flex items-center gap-2">
                @if ($card_last_four)
                    <button type="button" class="btn btn-error btn-outline btn-xs" wire:click="clearCard">Remove</button>
                @endif
            </div>
        </div>

        @if (! $card_last_four && ! $editing)
            <div class="rounded-xl border border-dashed border-base-300/80 p-4 bg-base-200/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <p class="font-semibold text-base-content">No card saved</p>
                    <p class="text-sm text-base-content/70">Add a card to speed up future payments.</p>
                </div>
                <button type="button" class="btn btn-primary btn-sm" wire:click="toggleEdit">Add card</button>
            </div>
        @endif

        @if ($card_last_four && ! $editing)
            @php
                $maskedNumber = '•••• •••• •••• '.$card_last_four;
                $expiryLabel = str_pad($card_exp_month, 2, '0', STR_PAD_LEFT).'/'.substr((string) $card_exp_year, -2);
                $holder = $customer?->name ?? auth()->user()->name;
            @endphp
            <div class="hover-3d cursor-pointer">
                <div class="w-90 text-black rounded-2xl p-5 relative overflow-hidden bg-[radial-gradient(circle_at_bottom_left,#ffffff08_35%,transparent_36%),radial-gradient(circle_at_top_right,#ffffff08_35%,transparent_36%)] bg-size-[4.95em_4.95em] shadow-xl">
                    <div class="absolute inset-0 pointer-events-none bg-gradient-to-tr from-primary/5 via-transparent to-secondary/5"></div>
                    <div class="relative space-y-6">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-semibold uppercase tracking-[0.2em]">{{ $card_brand ?: 'Card' }}</div>
                            <div class="text-4xl opacity-10">❁</div>
                        </div>
                        <div class="text-xl font-semibold tracking-widest">{{ $maskedNumber }}</div>
                        <div class="flex items-center justify-between text-sm">
                            <div class="space-y-1">
                                <div class="text-xs opacity-60">Card holder</div>
                                <div class="font-semibold">{{ $holder }}</div>
                            </div>
                            <div class="space-y-1 text-right">
                                <div class="text-xs opacity-60">Expires</div>
                                <div class="font-semibold">{{ $expiryLabel }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div>
            </div>
        @endif

        @if ($editing)
            <div class="space-y-4">
                <div class="grid gap-3">
                    <label class="flex flex-col gap-1" x-data="cardNumberInput(@entangle('card_number'), @entangle('card_brand'))">
                        <span class="label-text font-semibold">Card number</span>
                        <div class="flex items-center gap-2">
                            <input
                                type="text"
                                inputmode="numeric"
                                autocomplete="cc-number"
                                x-model="display"
                                x-on:input="formatNumber($event)"
                                class="input input-bordered w-full"
                                placeholder="4242 4242 4242 4242"
                            >
                            <span class="min-w-[120px] text-sm text-base-content/70 inline-flex items-center gap-2">
                                <span x-show="detecting" class="loading loading-spinner loading-sm"></span>
                                <span x-text="detecting ? 'Detecting…' : (brand || 'Unknown')"></span>
                            </span>
                        </div>
                    </label>

                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="flex flex-col gap-1" x-data="expiryInput(@entangle('expiry'))">
                            <span class="label-text font-semibold">Expiry (MM/YY)</span>
                            <input
                                type="text"
                                inputmode="numeric"
                                autocomplete="cc-exp"
                                x-model="display"
                                x-on:input="formatExpiry($event)"
                                class="input input-bordered"
                                placeholder="08/29"
                            >
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="label-text font-semibold">Security code</span>
                            <input
                                type="text"
                                wire:model="cvv"
                                class="input input-bordered"
                                placeholder="123"
                                autocomplete="cc-csc"
                            >
                        </label>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="saveCard">Save card</button>
                    <button type="button" class="btn btn-ghost btn-sm" wire:click="toggleEdit">Cancel</button>
                    <span wire:loading wire:target="saveCard" class="loading loading-dots loading-sm text-primary"></span>
                </div>
            </div>
        @endif
    </div>
</x-settings.layout>

<script>
    function cardNumberInput(cardEntangle, brandEntangle) {
        return {
            card: cardEntangle,
            brand: brandEntangle,
            display: '',
            detecting: false,
            init() {
                this.display = this.formatDisplay(this.card || '');
            },
            formatDisplay(raw) {
                return (raw || '')
                    .replace(/\D/g, '')
                    .slice(0, 19)
                    .replace(/(.{4})/g, '$1 ')
                    .trim();
            },
            detectBrandLocal(number) {
                const patterns = [
                    ['Visa', /^4[0-9]{6,}$/],
                    ['Mastercard', /^(5[1-5][0-9]{5,}|2[2-7][0-9]{5,})/],
                    ['American Express', /^3[47][0-9]{5,}/],
                    ['Discover', /^6(?:011|5[0-9]{2})[0-9]{3,}/],
                    ['JCB', /^(?:2131|1800|35\d{3})\d{11}$/],
                    ['Diners Club', /^3(?:0[0-5]|[68][0-9])[0-9]{4,}/],
                ];
                for (const [name, regex] of patterns) {
                    if (regex.test(number)) return name;
                }
                return 'Unknown';
            },
            formatNumber(event) {
                const raw = (event.target.value || '').replace(/\D/g, '').slice(0, 19);
                this.card = raw;
                this.display = this.formatDisplay(raw);
                this.detecting = true;
                const brand = this.detectBrandLocal(raw);
                this.brand = brand;
                brandEntangle = brand;
                this.$nextTick(() => { this.detecting = false; });
            },
        };
    }

    function expiryInput(expiryEntangle) {
        return {
            raw: expiryEntangle,
            display: '',
            init() {
                this.display = this.formatDisplay(this.raw || '');
                this.raw = this.display;
            },
            clean(raw) {
                return (raw || '').replace(/\D/g, '').slice(0, 4);
            },
            formatDisplay(raw) {
                const digits = this.clean(raw);
                if (digits.length <= 2) return digits;
                return digits.slice(0, 2) + '/' + digits.slice(2, 4);
            },
            formatExpiry(event) {
                const input = event.target;
                const digits = this.clean(input.value);
                this.display = this.formatDisplay(digits);
                this.raw = this.display;
                this.$nextTick(() => {
                    const pos = this.display.length;
                    input.setSelectionRange(pos, pos);
                });
            },
        };
    }
</script>
