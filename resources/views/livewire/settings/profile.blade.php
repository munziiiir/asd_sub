<?php

use App\Models\CustomerUser;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app.base')] class extends Component {
    public ?CustomerUser $customer = null;

    public string $name = '';
    public string $email = '';
    public string $phone = '';

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

    public bool $billing_same = false;

    public function mount(): void
    {
        $user = Auth::user();

        $this->customer = CustomerUser::firstOrCreate(
            ['user_id' => $user->id],
            ['name' => $user->name, 'email' => $user->email]
        );

        $this->name = $user->name;
        $this->email = $user->email;

        $this->fillFromCustomer($this->customer);
    }

    public function updatedBillingSame(bool $value): void
    {
        if ($value) {
            $this->copyProfileToBilling();
        }
    }

    public function saveProfile(): void
    {
        $user = Auth::user();

        if ($this->billing_same) {
            $this->copyProfileToBilling();
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
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
        ]);

        $user->fill(['name' => $validated['name'], 'email' => $validated['email']]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $customer = $this->customer ?? CustomerUser::firstOrCreate(['user_id' => $user->id]);

        $customer->fill([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $this->nullable($validated['phone']),
            'address_line1' => $this->nullable($validated['address_line1']),
            'address_line2' => $this->nullable($validated['address_line2']),
            'city' => $this->nullable($validated['city']),
            'state' => $this->nullable($validated['state']),
            'postal_code' => $this->nullable($validated['postal_code']),
            'country' => $this->nullable($validated['country']),
            'billing_address_line1' => $this->nullable($validated['billing_address_line1']),
            'billing_address_line2' => $this->nullable($validated['billing_address_line2']),
            'billing_city' => $this->nullable($validated['billing_city']),
            'billing_state' => $this->nullable($validated['billing_state']),
            'billing_postal_code' => $this->nullable($validated['billing_postal_code']),
            'billing_country' => $this->nullable($validated['billing_country']),
        ]);

        $customer->save();

        $this->customer = $customer;
        $this->billing_same = $this->billingAddressesMatch();

        $this->dispatch('profile-updated', name: $user->name);
    }

    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    private function fillFromCustomer(CustomerUser $customer): void
    {
        foreach ([
            'phone',
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
            $this->{$field} = (string) ($customer->{$field} ?? '');
        }

        $this->billing_same = $this->billingAddressesMatch();
    }

    private function billingAddressesMatch(): bool
    {
        $profile = [
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ];

        $billing = [
            $this->billing_address_line1,
            $this->billing_address_line2,
            $this->billing_city,
            $this->billing_state,
            $this->billing_postal_code,
            $this->billing_country,
        ];

        return collect($profile)->map(fn ($value) => trim((string) $value))->values()->toArray()
            === collect($billing)->map(fn ($value) => trim((string) $value))->values()->toArray();
    }

    public function getBillingMatchesProperty(): bool
    {
        return $this->billingAddressesMatch();
    }

    private function copyProfileToBilling(): void
    {
        $this->billing_address_line1 = $this->address_line1;
        $this->billing_address_line2 = $this->address_line2;
        $this->billing_city = $this->city;
        $this->billing_state = $this->state;
        $this->billing_postal_code = $this->postal_code;
        $this->billing_country = $this->country;
    }

    private function nullable(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}; ?>

<x-settings.layout
    :customer="$customer"
    :heading="__('Profile & contact')"
    :subheading="__('Keep your personal, contact, and booking details up to date')"
>
    <form wire:submit.prevent="saveProfile" class="space-y-6">
        <div class="card bg-base-100 shadow-sm border border-base-300/60">
            <div class="card-body space-y-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold text-base-content">Contact & identity</h2>
                        <p class="text-sm text-base-content/70">These details appear on booking confirmations and receipts.</p>
                    </div>
                    @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                        <span class="badge badge-warning badge-outline">Email not verified</span>
                    @else
                        <span class="badge badge-success badge-outline">Email verified</span>
                    @endif
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="flex flex-col gap-1">
                        <span class="label-text font-semibold">Full name</span>
                        <input
                            wire:model="name"
                            type="text"
                            class="input input-bordered"
                            autocomplete="name"
                            required
                        >
                        @error('name') <span class="text-sm text-error mt-1">{{ $message }}</span> @enderror
                    </label>
                    <label class="flex flex-col gap-1">
                        <span class="label-text font-semibold">Email</span>
                        <input
                            wire:model="email"
                            type="email"
                            class="input input-bordered"
                            autocomplete="email"
                            required
                        >
                        @error('email') <span class="text-sm text-error mt-1">{{ $message }}</span> @enderror
                        @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                            <button
                                type="button"
                                wire:click="resendVerificationNotification"
                                class="btn btn-link px-0 text-sm"
                            >
                                Resend verification email
                            </button>
                            @if (session('status') === 'verification-link-sent')
                                <p class="text-xs text-success mt-1">A new verification link has been sent.</p>
                            @endif
                        @endif
                    </label>
                </div>

                <label class="flex flex-col gap-1 md:max-w-sm">
                    <span class="label-text font-semibold">Phone (for stay updates)</span>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-base-content/60">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5h2.5L8 9l-1.75.75a11 11 0 0 0 4 4L11 12l4.5 1.75v2.5a1.75 1.75 0 0 1-1.75 1.75A10 10 0 0 1 4.5 6.25 1.75 1.75 0 0 1 6.25 4.5z"/></svg>
                        </span>
                        <input
                            wire:model="phone"
                            type="text"
                            class="input input-bordered pl-10"
                            placeholder="+44 1234 567890"
                        >
                    </div>
                    @error('phone') <span class="text-sm text-error mt-1">{{ $message }}</span> @enderror
                </label>
            </div>
        </div>

        <div class="card bg-base-100 shadow-sm border border-base-300/60">
            <div class="card-body space-y-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold text-base-content">Home address</h2>
                        <p class="text-sm text-base-content/70">We use this for receipts and pre-filling booking forms.</p>
                    </div>
                    <div class="badge badge-outline gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l3 3m5-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                        Live for upcoming stays
                    </div>
                </div>

                <div class="grid gap-4">
                    <label class="flex flex-col gap-1">
                        <span class="label-text font-semibold">Address line 1</span>
                        <input wire:model="address_line1" type="text" class="input input-bordered" placeholder="Street and number">
                        @error('address_line1') <span class="text-sm text-error mt-1">{{ $message }}</span> @enderror
                    </label>
                    <label class="flex flex-col gap-1">
                        <span class="label-text font-semibold">Address line 2 (optional)</span>
                        <input wire:model="address_line2" type="text" class="input input-bordered" placeholder="Apartment, suite, etc.">
                        @error('address_line2') <span class="text-sm text-error mt-1">{{ $message }}</span> @enderror
                    </label>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="flex flex-col gap-1">
                            <span class="label-text font-semibold">City</span>
                            <input wire:model="city" type="text" class="input input-bordered">
                            @error('city') <span class="text-sm text-error mt-1">{{ $message }}</span> @enderror
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="label-text font-semibold">State / Region</span>
                            <input wire:model="state" type="text" class="input input-bordered">
                            @error('state') <span class="text-sm text-error mt-1">{{ $message }}</span> @enderror
                        </label>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="flex flex-col gap-1">
                            <span class="label-text font-semibold">Postal code</span>
                            <input wire:model="postal_code" type="text" class="input input-bordered">
                            @error('postal_code') <span class="text-sm text-error mt-1">{{ $message }}</span> @enderror
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="label-text font-semibold">Country</span>
                            <input wire:model="country" type="text" class="input input-bordered" placeholder="United Kingdom">
                            @error('country') <span class="text-sm text-error mt-1">{{ $message }}</span> @enderror
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow-sm border border-base-300/60">
            <div class="card-body space-y-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold text-base-content">Billing address</h2>
                        <p class="text-sm text-base-content/70">Set the billing contact for invoices and payment confirmations.</p>
                    </div>
                    <label class="label cursor-pointer gap-2">
                        <span class="label-text text-sm">Same as home</span>
                        <input type="checkbox" wire:model.live="billing_same" class="toggle toggle-primary">
                    </label>
                </div>

                <div class="grid gap-4">
                    <label class="flex flex-col gap-1">
                        <span class="label-text font-semibold">Billing line 1</span>
                        <input wire:model="billing_address_line1" type="text" class="input input-bordered">
                        @error('billing_address_line1') <span class="text-sm text-error mt-1">{{ $message }}</span> @enderror
                    </label>
                    <label class="flex flex-col gap-1">
                        <span class="label-text font-semibold">Billing line 2 (optional)</span>
                        <input wire:model="billing_address_line2" type="text" class="input input-bordered">
                        @error('billing_address_line2') <span class="text-sm text-error mt-1">{{ $message }}</span> @enderror
                    </label>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="flex flex-col gap-1">
                            <span class="label-text font-semibold">City</span>
                            <input wire:model="billing_city" type="text" class="input input-bordered">
                            @error('billing_city') <span class="text-sm text-error mt-1">{{ $message }}</span> @enderror
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="label-text font-semibold">State / Region</span>
                            <input wire:model="billing_state" type="text" class="input input-bordered">
                            @error('billing_state') <span class="text-sm text-error mt-1">{{ $message }}</span> @enderror
                        </label>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="flex flex-col gap-1">
                            <span class="label-text font-semibold">Postal code</span>
                            <input wire:model="billing_postal_code" type="text" class="input input-bordered">
                            @error('billing_postal_code') <span class="text-sm text-error mt-1">{{ $message }}</span> @enderror
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="label-text font-semibold">Country</span>
                            <input wire:model="billing_country" type="text" class="input input-bordered">
                            @error('billing_country') <span class="text-sm text-error mt-1">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    @if ($billing_same && ! $this->billingMatches)
                        <p class="text-sm text-warning">
                            We detected differences between home and billing details. Uncheck “Same as home” to edit separately.
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="btn btn-primary" data-test="update-profile-button">
                Save profile
            </button>
            <x-action-message on="profile-updated" class="text-success">
                Saved.
            </x-action-message>
            <span wire:loading wire:target="saveProfile" class="loading loading-dots loading-md text-primary"></span>
        </div>
    </form>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card bg-base-100 shadow-sm border border-base-300/60">
            <div class="card-body space-y-3">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4.75 8.75A2.75 2.75 0 0 1 7.5 6h9a2.75 2.75 0 0 1 2.75 2.75v6.5A2.75 2.75 0 0 1 16.5 18h-9A2.75 2.75 0 0 1 4.75 15.25z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.5 9.5h5M9.5 12h3"/></svg>
                    <h3 class="font-semibold text-base-content">Payment fingerprint</h3>
                </div>
                @if ($customer?->card_brand && $customer?->card_last_four)
                    <p class="text-sm text-base-content/80">We’ll use your saved {{ $customer->card_brand }} ending in {{ $customer->card_last_four }} at checkout.</p>
                    <p class="text-xs text-base-content/60">Expires {{ $customer->card_exp_month }}/{{ $customer->card_exp_year }}</p>
                @else
                    <p class="text-sm text-base-content/70">No card on file yet. We’ll prompt you for payment details during checkout.</p>
                @endif
            </div>
        </div>

        <div class="card bg-base-100 shadow-sm border border-base-300/60">
            <div class="card-body space-y-3">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-error" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 2.82 17a1.6 1.6 0 0 0 1.38 2.4h15.6A1.6 1.6 0 0 0 21.18 17L13.71 3.86a1.6 1.6 0 0 0-2.82 0z"/></svg>
                    <h3 class="font-semibold text-base-content">Account housekeeping</h3>
                </div>
                <p class="text-sm text-base-content/70">Need to leave? You can delete your account and purge your customer profile.</p>
                <livewire:settings.delete-user-form />
            </div>
        </div>
    </div>
</x-settings.layout>
