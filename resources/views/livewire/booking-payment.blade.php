<section class="bg-base-200 min-h-[calc(100vh-6rem)] px-6 py-10">
    <div class="max-w-5xl mx-auto space-y-6">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <p class="text-sm uppercase tracking-widest text-primary font-semibold">Secure checkout</p>
                <h1 class="text-3xl md:text-4xl font-bold text-base-content">Confirm & pay</h1>
                <p class="text-base-content/70 mt-1">
                    @if($reservation->status === 'NoShow')
                        Settle the outstanding balance for your no-show booking.
                    @else
                        Choose a card and pay your first-night deposit to confirm your booking.
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('bookings.index') }}" class="btn btn-ghost btn-sm">← Back to bookings</a>
                <a href="{{ route('bookings.show', $reservation) }}" class="btn btn-ghost btn-sm">View booking</a>
            </div>
        </div>

        @if ($errorMessage)
            <div class="alert alert-error shadow-sm">
                <span>{{ $errorMessage }}</span>
            </div>
        @endif
        @if ($statusMessage)
            <div class="alert alert-success shadow-sm">
                <span>{{ $statusMessage }}</span>
            </div>
        @endif

        @if ($processing || $success)
            <div class="card bg-base-100 shadow border border-base-300/70">
                <div class="card-body space-y-3">
                    @if ($success)
                        <div class="flex items-center gap-2">
                            <span class="badge badge-success">Paid</span>
                            <p class="font-semibold text-success">Payment confirmed</p>
                        </div>
                        <p class="text-sm text-base-content/80">We’re redirecting you to the booking details. If nothing happens, use the button below.</p>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('bookings.show', $reservation) }}" class="btn btn-success btn-sm">Go to booking</a>
                            <span class="loading loading-dots loading-sm text-success" aria-hidden="true"></span>
                        </div>
                    @else
                        <div class="flex items-center gap-3">
                            <span class="loading loading-spinner loading-lg text-primary"></span>
                            <div>
                                <p class="font-semibold text-base-content">Processing payment…</p>
                                <p class="text-sm text-base-content/70">Keep this tab open while we confirm your booking.</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
        @if (! $processing && ! $success)
        <div class="grid gap-6 lg:grid-cols-[1.1fr,0.9fr]">
            <form wire:submit.prevent="pay" class="card bg-base-100 shadow border border-base-300/70 relative overflow-hidden">
                <div class="card-body space-y-6">
                    <div class="flex items-center justify-between">
                        <h2 class="card-title text-base-content">Payment method</h2>
                        @if ($processing)
                            <span class="loading loading-spinner loading-sm text-primary"></span>
                        @endif
                    </div>

                    <div class="space-y-3">
                        <label class="cursor-pointer flex items-start gap-3 border border-base-300/60 rounded-xl p-3 @if($cardOption === 'saved') ring-2 ring-primary/70 @endif @if(!$card_last_four) opacity-60 @endif">
                            <input
                                type="radio"
                                class="radio radio-primary mt-1"
                                value="saved"
                                wire:model.live="cardOption"
                                @disabled(! $card_last_four)
                            >
                            <div class="space-y-1">
                                <p class="font-semibold text-base-content">Use saved card</p>
                                <p class="text-sm text-base-content/70">
                                    @if ($card_last_four)
                                        {{ $card_brand ?: 'Card' }} ending in {{ $card_last_four }} · Expires {{ str_pad((string) $card_exp_month, 2, '0', STR_PAD_LEFT) }}/{{ substr((string) $card_exp_year, -2) }}
                                    @else
                                        Add a new card to save it for next time.
                                    @endif
                                </p>
                            </div>
                        </label>

                        <label class="cursor-pointer flex items-start gap-3 border border-base-300/60 rounded-xl p-3 @if($cardOption === 'new') ring-2 ring-primary/70 @endif">
                            <input
                                type="radio"
                                class="radio radio-primary mt-1"
                                value="new"
                                wire:model.live="cardOption"
                            >
                            <div class="space-y-1">
                                <p class="font-semibold text-base-content">Use a different card</p>
                                <p class="text-sm text-base-content/70">We store a fingerprint only; details are hashed.</p>
                            </div>
                        </label>
                    </div>

                    @if ($cardOption === 'new')
                        <div class="space-y-3">
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
                                        wire:model.live="cvv"
                                        class="input input-bordered"
                                        placeholder="123"
                                        autocomplete="cc-csc"
                                        maxlength="4"
                                        inputmode="numeric"
                                    >
                                </label>
                            </div>
                        </div>
                    @endif

                    <div class="divider"></div>

                    @if($reservation->status !== 'NoShow')
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="font-semibold text-base-content">Add-on services (optional)</h3>
                                <span class="badge badge-outline">Added to your bill</span>
                            </div>
                            <div class="grid gap-3">
                                @foreach ($chargeOptions as $option)
                                    @continue($option['code'] === 'custom')
                                    @php
                                        $checked = $extras[$option['code']]['selected'] ?? false;
                                    @endphp
                                    <div
                                        class="border border-base-300/60 rounded-xl p-3 flex items-start justify-between gap-3"
                                        wire:key="extra-{{ $option['code'] }}"
                                    >
                                        <div class="flex items-start gap-3">
                                            <input
                                                type="checkbox"
                                                class="checkbox checkbox-primary mt-1"
                                                wire:model.live="extras.{{ $option['code'] }}.selected"
                                            >
                                            <div>
                                                <p class="font-semibold text-base-content">{{ $option['label'] }}</p>
                                                <p class="text-sm text-base-content/70">£{{ number_format($option['amount'], 2) }} · Applied to {{ $this->totalGuests }} guest{{ $this->totalGuests === 1 ? '' : 's' }}</p>
                                            </div>
                                        </div>
                                        <span class="badge badge-outline">Total: £{{ number_format($option['amount'] * max(1, $this->totalGuests), 2) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="alert alert-error">
                            <div>
                                <span class="font-semibold">Complete your no-show payment</span>
                                <p class="text-sm text-base-content/80">
                                    You won’t be able to make new bookings until this no-show balance is fully settled. Add-on services are also disabled while you pay this balance.
                                </p>
                            </div>
                        </div>
                    @endif

                    <div class="divider"></div>

                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-base-content/70">
                                @if($reservation->status === 'NoShow')
                                    Due today (balance)
                                @else
                                    Due today (deposit)
                                @endif
                            </p>
                            <p class="text-2xl font-semibold text-base-content">£{{ number_format($this->dueNow, 2) }}</p>
                            <p class="text-xs text-base-content/60">
                                @if($reservation->status === 'NoShow')
                                    This balance must be settled before you can make new bookings.
                                @else
                                    Deposit covers the first night’s room total. Add-ons and remaining nights are due later.
                                @endif
                                @if($this->remainingBalance)
                                    <span class="block">Remaining balance after deposit: £{{ number_format($this->remainingBalance, 2) }}</span>
                                @endif
                            </p>
                        </div>
                        <button type="submit" class="btn btn-primary" @disabled($processing || $success || $this->dueNow <= 0)">
                            <span>
                                @if($this->dueNow > 0)
                                    Pay £{{ number_format($this->dueNow, 2) }}
                                @else
                                    Amount covered
                                @endif
                            </span>
                            <span wire:loading wire:target="pay" class="loading loading-spinner loading-sm ml-2"></span>
                        </button>
                    </div>
                </div>

            </form>

            <div class="space-y-4">
                <div class="card bg-gradient-to-br from-primary/10 via-base-100 to-secondary/10 border border-base-300/60 shadow">
                    <div class="card-body space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-base-content">Booking summary</h3>
                            <span class="badge badge-outline">{{ $reservation->status }}</span>
                        </div>
                        <dl class="space-y-3 text-sm">
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-base-content/70">Code</dt>
                                <dd class="font-semibold text-base-content">{{ $reservation->code }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-base-content/70">Hotel</dt>
                                <dd class="font-semibold text-base-content">{{ $reservation->hotel?->name ?? 'Hotel' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-base-content/70">Dates</dt>
                                <dd class="font-semibold text-base-content">
                                    {{ optional($reservation->check_in_date)->toDateString() }} → {{ optional($reservation->check_out_date)->toDateString() }}
                                    <span class="block text-xs text-base-content/60">{{ $this->nights }} night{{ $this->nights === 1 ? '' : 's' }}</span>
                                </dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-base-content/70">Guests</dt>
                                <dd class="font-semibold text-base-content">
                                    {{ $reservation->adults }} adult{{ $reservation->adults === 1 ? '' : 's' }}
                                    @if($reservation->children)
                                        + {{ $reservation->children }} child{{ $reservation->children === 1 ? '' : 'ren' }}
                                    @endif
                                </dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-base-content/70">Rooms</dt>
                                <dd class="font-semibold text-base-content">
                                    @if($reservation->reservationRooms->count())
                                        <div class="space-y-1">
                                            @foreach ($reservation->reservationRooms as $room)
                                                <span class="block">{{ $room->room?->roomType?->name ?? 'Room' }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        Assigned on arrival
                                    @endif
                                </dd>
                            </div>
                        </dl>
                        <div class="divider my-2"></div>
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/70">Room total ({{ $this->nights }} night{{ $this->nights === 1 ? '' : 's' }})</span>
                                <span class="font-semibold text-base-content">£{{ number_format($this->roomTotal, 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/70">Extras</span>
                                <span class="font-semibold text-base-content">£{{ number_format($this->extrasTotal, 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/70">
                                    @if($reservation->status === 'NoShow')
                                        Due today (balance)
                                    @else
                                        Due today (deposit)
                                    @endif
                                </span>
                                <span class="font-semibold text-base-content">£{{ number_format($this->dueNow, 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-lg font-semibold pt-2">
                                <span>Total</span>
                                <span>£{{ number_format($this->grandTotal, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</section>

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

<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('payment-completed', ({ url }) => {
            if (!url) return;
            setTimeout(() => {
                window.location = url;
            }, 1400);
        });
    });
</script>
