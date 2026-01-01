<?php

use App\Models\CustomerUser;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app.base')] class extends Component {
    public ?CustomerUser $customer = null;

    public string $address_line1 = '';
    public string $address_line2 = '';
    public string $city = '';
    public string $state = '';
    public string $postal_code = '';
    public string $country = '';

    public string $billing_address_line1 = '';
    public string $billing_address_line2 = '';
    public string $billing_city = '';
    public string $billing_state = '';
    public string $billing_postal_code = '';
    public string $billing_country = '';

    public array $editing = [];
    public string $lastSavedField = '';

    public function mount(): void
    {
        $this->customer = CustomerUser::firstOrCreate(
            ['user_id' => Auth::id()],
            ['name' => Auth::user()?->name, 'email' => Auth::user()?->email]
        );

        foreach ([
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'country',
            'billing_address_line1',
            'billing_address_line2',
            'billing_city',
            'billing_state',
            'billing_postal_code',
            'billing_country',
        ] as $field) {
            $this->{$field} = (string) ($this->customer->{$field} ?? '');
            $this->editing[$field] = false;
        }
    }

    public function toggle(string $field): void
    {
        if (! array_key_exists($field, $this->editing)) {
            return;
        }

        $this->editing[$field] = ! $this->editing[$field];
    }

    public function saveField(string $field): void
    {
        $rules = [
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:120'],
            'billing_address_line1' => ['nullable', 'string', 'max:255'],
            'billing_address_line2' => ['nullable', 'string', 'max:255'],
            'billing_city' => ['nullable', 'string', 'max:120'],
            'billing_state' => ['nullable', 'string', 'max:120'],
            'billing_postal_code' => ['nullable', 'string', 'max:40'],
            'billing_country' => ['nullable', 'string', 'max:120'],
        ];

        if (! isset($rules[$field])) {
            return;
        }

        $validated = $this->validate([
            $field => $rules[$field],
        ]);

        $this->{$field} = (string) ($validated[$field] ?? '');

        $this->customer?->fill([
            $field => $this->nullable($validated[$field] ?? null),
        ])->save();

        $this->lastSavedField = $field;
        $this->editing[$field] = false;
        $this->dispatch('address-updated');
    }

    public function clearSavedField(): void
    {
        $this->lastSavedField = '';
    }

    public function copyHomeToBilling(): void
    {
        $map = [
            'address_line1' => 'billing_address_line1',
            'address_line2' => 'billing_address_line2',
            'city' => 'billing_city',
            'state' => 'billing_state',
            'postal_code' => 'billing_postal_code',
            'country' => 'billing_country',
        ];

        foreach ($map as $home => $billing) {
            $this->{$billing} = $this->{$home};
            $this->editing[$billing] = false;
        }

        $this->customer?->forceFill([
            'billing_address_line1' => $this->nullable($this->billing_address_line1),
            'billing_address_line2' => $this->nullable($this->billing_address_line2),
            'billing_city' => $this->nullable($this->billing_city),
            'billing_state' => $this->nullable($this->billing_state),
            'billing_postal_code' => $this->nullable($this->billing_postal_code),
            'billing_country' => $this->nullable($this->billing_country),
        ])->save();

        $this->dispatch('address-updated');
    }

    private function nullable(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}; ?>

<x-settings.layout
    :customer="$customer"
    :heading="__('Address')"
    :subheading="__('Home and billing addresses used across bookings and receipts')"
>
    <span wire:poll.2s="clearSavedField" class="hidden"></span>
    <div class="space-y-6">
        <div class="bg-base-100 border border-base-300/60 rounded-2xl p-4 space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-base-content">Home address</h2>
                    <p class="text-sm text-base-content/70">Shown on confirmations and check-in details.</p>
                </div>
                </div>

                <div class="divide-y divide-base-300/70">
                    @foreach ([
                        ['label' => 'Address line 1', 'field' => 'address_line1', 'placeholder' => '123 Main St'],
                        ['label' => 'Address line 2', 'field' => 'address_line2', 'placeholder' => 'Apartment, suite'],
                        ['label' => 'City', 'field' => 'city', 'placeholder' => 'City'],
                        ['label' => 'State / Region', 'field' => 'state', 'placeholder' => 'State'],
                        ['label' => 'Postal code', 'field' => 'postal_code', 'placeholder' => 'ZIP / Postcode'],
                        ['label' => 'Country', 'field' => 'country', 'placeholder' => 'Country'],
                    ] as $row)
                        <div class="py-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-base-content/70">{{ $row['label'] }}</p>
                                <p class="text-base font-semibold text-base-content">{{ $this->{$row['field']} ?: 'Not set' }}</p>
                            </div>
                            <div class="flex items-center gap-2 min-w-[96px] justify-end">
                                @if ($editing[$row['field']])
                                    <input type="text" wire:model="{{ $row['field'] }}" class="input input-sm input-bordered" placeholder="{{ $row['placeholder'] }}">
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="btn btn-primary btn-sm" wire:click="saveField('{{ $row['field'] }}')">Save</button>
                                        <button type="button" class="btn btn-ghost btn-sm" wire:click="toggle('{{ $row['field'] }}')">Cancel</button>
                                    </div>
                                @else
                                    @if ($lastSavedField === $row['field'])
                                        <span class="text-success text-sm">Saved.</span>
                                    @else
                                        <button type="button" class="btn btn-ghost btn-sm" wire:click="toggle('{{ $row['field'] }}')">Edit</button>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="bg-base-100 border border-base-300/60 rounded-2xl p-4 space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-base-content">Billing address</h2>
                    <p class="text-sm text-base-content/70">Used for invoices and payment confirmations.</p>
                </div>
                    <button type="button" class="btn btn-outline btn-sm" wire:click="copyHomeToBilling">Copy home address</button>
                </div>

                <div class="divide-y divide-base-300/70">
                    @foreach ([
                        ['label' => 'Billing line 1', 'field' => 'billing_address_line1', 'placeholder' => '123 Main St'],
                        ['label' => 'Billing line 2', 'field' => 'billing_address_line2', 'placeholder' => 'Apartment, suite'],
                        ['label' => 'City', 'field' => 'billing_city', 'placeholder' => 'City'],
                        ['label' => 'State / Region', 'field' => 'billing_state', 'placeholder' => 'State'],
                        ['label' => 'Postal code', 'field' => 'billing_postal_code', 'placeholder' => 'ZIP / Postcode'],
                        ['label' => 'Country', 'field' => 'billing_country', 'placeholder' => 'Country'],
                    ] as $row)
                        <div class="py-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-base-content/70">{{ $row['label'] }}</p>
                                <p class="text-base font-semibold text-base-content">{{ $this->{$row['field']} ?: 'Not set' }}</p>
                            </div>
                            <div class="flex items-center gap-2 min-w-[96px] justify-end">
                                @if ($editing[$row['field']])
                                    <input type="text" wire:model="{{ $row['field'] }}" class="input input-sm input-bordered" placeholder="{{ $row['placeholder'] }}">
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="btn btn-primary btn-sm" wire:click="saveField('{{ $row['field'] }}')">Save</button>
                                        <button type="button" class="btn btn-ghost btn-sm" wire:click="toggle('{{ $row['field'] }}')">Cancel</button>
                                    </div>
                                @else
                                    @if ($lastSavedField === $row['field'])
                                        <span class="text-success text-sm">Saved.</span>
                                    @else
                                        <button type="button" class="btn btn-ghost btn-sm" wire:click="toggle('{{ $row['field'] }}')">Edit</button>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-settings.layout>
