<?php

use App\Models\CustomerUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app.base')] class extends Component {
    public ?CustomerUser $customer = null;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->customer = CustomerUser::firstWhere('user_id', Auth::id());
    }

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<x-settings.layout
    :customer="$customer"
    :heading="__('Security')"
    :subheading="__('Keep sign-in credentials strong and aligned with your booking contact details')"
>
    <div class="grid gap-6 lg:grid-cols-[2fr,1fr]">
        <form wire:submit.prevent="updatePassword" class="card bg-base-100 shadow-sm border border-base-300/60">
            <div class="card-body space-y-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold text-base-content">Change password</h2>
                        <p class="text-sm text-base-content/70">Update your password and keep your recovery details fresh.</p>
                    </div>
                    <div class="badge badge-outline gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M4.75 8.75A2.75 2.75 0 0 1 7.5 6h9a2.75 2.75 0 0 1 2.75 2.75v6.5A2.75 2.75 0 0 1 16.5 18h-9A2.75 2.75 0 0 1 4.75 15.25z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.5 9.5h5M9.5 12h3"/></svg>
                        Secure sign-in
                    </div>
                </div>

                <div class="space-y-4">
                    <label class="flex flex-col gap-1">
                        <span class="label-text font-semibold">Current password</span>
                        <input
                            wire:model="current_password"
                            type="password"
                            class="input input-bordered"
                            autocomplete="current-password"
                            required
                        >
                        @error('current_password') <span class="text-sm text-error mt-1">{{ $message }}</span> @enderror
                    </label>
                    <label class="flex flex-col gap-1">
                        <span class="label-text font-semibold">New password</span>
                        <input
                            wire:model="password"
                            type="password"
                            class="input input-bordered"
                            autocomplete="new-password"
                            required
                        >
                        @error('password') <span class="text-sm text-error mt-1">{{ $message }}</span> @enderror
                    </label>
                    <label class="flex flex-col gap-1">
                        <span class="label-text font-semibold">Confirm new password</span>
                        <input
                            wire:model="password_confirmation"
                            type="password"
                            class="input input-bordered"
                            autocomplete="new-password"
                            required
                        >
                        @error('password_confirmation') <span class="text-sm text-error mt-1">{{ $message }}</span> @enderror
                    </label>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="btn btn-primary" data-test="update-password-button">
                        Save password
                    </button>
                    <x-action-message on="password-updated" class="text-success">
                        Saved.
                    </x-action-message>
                    <span wire:loading wire:target="updatePassword" class="loading loading-dots loading-md text-primary"></span>
                </div>

                <ul class="list-disc pl-5 text-sm text-base-content/70 space-y-1">
                    <li>Use at least 12 characters with a mix of letters, numbers, and symbols.</li>
                    <li>Skip names or phone numbers tied to your profile.</li>
                    <li>We never display your password anywhere in the app.</li>
                </ul>
            </div>
        </form>

        <div class="space-y-4">
            <div class="card bg-base-100 shadow-sm border border-base-300/60">
                <div class="card-body space-y-3">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l3 3m5-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                        <h3 class="font-semibold text-base-content">Alerts</h3>
                    </div>
                    <p class="text-sm text-base-content/70">
                        We’ll send sign-in alerts to {{ $customer?->email ?? auth()->user()->email }} and use {{ $customer?->phone ?: 'your saved phone' }} for urgent booking updates.
                    </p>
                    <a href="{{ route('profile.edit') }}" wire:navigate class="link link-primary text-sm">Review contact details</a>
                </div>
            </div>

            <div class="card bg-gradient-to-br from-primary/10 via-base-100 to-secondary/10 border border-base-300/60 shadow-sm">
                <div class="card-body space-y-3">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4 5 6.5v5.38C5 15.59 6.91 18.45 12 20c5.09-1.55 7-4.41 7-8.12V6.5z"></path><path stroke-linecap="round" stroke-linejoin="round" d="m9.5 12.5 1.75 1.75L15 10.5"></path></svg>
                        <h3 class="font-semibold text-base-content">Add extra protection</h3>
                    </div>
                    <p class="text-sm text-base-content/70">Pair a strong password with two-factor authentication for sensitive bookings and receipts.</p>
                    <a href="{{ route('two-factor.show') }}" wire:navigate class="btn btn-outline btn-sm">Go to two-factor</a>
                </div>
            </div>
        </div>
    </div>
</x-settings.layout>
