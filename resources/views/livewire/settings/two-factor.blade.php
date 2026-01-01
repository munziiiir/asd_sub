<?php

use App\Models\CustomerUser;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Symfony\Component\HttpFoundation\Response;

new #[Layout('components.layouts.app.base')] class extends Component {
    public ?CustomerUser $customer = null;

    #[Locked]
    public bool $twoFactorEnabled;

    #[Locked]
    public bool $requiresConfirmation;

    #[Locked]
    public string $qrCodeSvg = '';

    #[Locked]
    public string $manualSetupKey = '';

    public bool $showModal = false;

    public bool $showVerificationStep = false;

    #[Validate('required|string|size:6', onUpdate: false)]
    public string $code = '';

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        abort_unless(Features::enabled(Features::twoFactorAuthentication()), Response::HTTP_FORBIDDEN);

        if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
            $disableTwoFactorAuthentication(auth()->user());
        }

        $this->customer = CustomerUser::firstWhere('user_id', auth()->id());
        $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
        $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
    }

    /**
     * Enable two-factor authentication for the user.
     */
    public function enable(EnableTwoFactorAuthentication $enableTwoFactorAuthentication): void
    {
        $enableTwoFactorAuthentication(auth()->user());

        if (! $this->requiresConfirmation) {
            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
        }

        $this->loadSetupData();

        $this->showModal = true;
    }

    /**
     * Load the two-factor authentication setup data for the user.
     */
    private function loadSetupData(): void
    {
        $user = auth()->user();

        try {
            $this->qrCodeSvg = $user?->twoFactorQrCodeSvg();
            $this->manualSetupKey = decrypt($user->two_factor_secret);
        } catch (Exception) {
            $this->addError('setupData', 'Failed to fetch setup data.');

            $this->reset('qrCodeSvg', 'manualSetupKey');
        }
    }

    /**
     * Show the two-factor verification step if necessary.
     */
    public function showVerificationIfNecessary(): void
    {
        if ($this->requiresConfirmation) {
            $this->showVerificationStep = true;

            $this->resetErrorBag();

            return;
        }

        $this->closeModal();
    }

    /**
     * Confirm two-factor authentication for the user.
     */
    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication): void
    {
        $this->validate();

        $confirmTwoFactorAuthentication(auth()->user(), $this->code);

        $this->closeModal();

        $this->twoFactorEnabled = true;
    }

    /**
     * Reset two-factor verification state.
     */
    public function resetVerification(): void
    {
        $this->reset('code', 'showVerificationStep');

        $this->resetErrorBag();
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;
    }

    /**
     * Close the two-factor authentication modal.
     */
    public function closeModal(): void
    {
        $this->reset(
            'code',
            'manualSetupKey',
            'qrCodeSvg',
            'showModal',
            'showVerificationStep',
        );

        $this->resetErrorBag();

        if (! $this->requiresConfirmation) {
            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
        }
    }

    /**
     * Get the current modal configuration state.
     */
    public function getModalConfigProperty(): array
    {
        if ($this->twoFactorEnabled) {
            return [
                'title' => __('Two-Factor Authentication Enabled'),
                'description' => __('Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.'),
                'buttonText' => __('Close'),
            ];
        }

        if ($this->showVerificationStep) {
            return [
                'title' => __('Verify Authentication Code'),
                'description' => __('Enter the 6-digit code from your authenticator app.'),
                'buttonText' => __('Continue'),
            ];
        }

        return [
            'title' => __('Enable Two-Factor Authentication'),
            'description' => __('Scan the QR code or enter the setup key in your authenticator app to finish.'),
            'buttonText' => __('Continue'),
        ];
    }
} ?>

<x-settings.layout
    :customer="$customer"
    :heading="__('Two-Factor Authentication')"
    :subheading="__('Lock down logins with a rotating code from your authenticator app')"
>
    <div class="grid gap-6 lg:grid-cols-[2fr,1fr]" wire:cloak>
        <div class="bg-base-100 border border-base-300/60 rounded-2xl p-4 space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-base-content">Two-factor status</h2>
                    <p class="text-sm text-base-content/70">We’ll prompt for a 6-digit code after your password when this is on.</p>
                </div>
                    <span class="badge {{ $twoFactorEnabled ? 'badge-success' : 'badge-ghost' }}">
                        {{ $twoFactorEnabled ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>

                <div class="flex flex-wrap gap-3">
                    @if ($twoFactorEnabled)
                        <button type="button" class="btn btn-error btn-outline" wire:click="disable">
                            Disable 2FA
                        </button>
                        <button type="button" class="btn btn-primary" wire:click="enable">
                            Reset setup
                        </button>
                    @else
                        <button type="button" class="btn btn-primary" wire:click="enable">
                            Enable two-factor
                        </button>
                    @endif
                </div>

                @if ($twoFactorEnabled)
                    <div class="alert alert-success">
                        <div>
                            <h3 class="font-semibold">Backup your codes</h3>
                            <p class="text-sm">Store recovery codes safely. They’re tied to your customer profile: {{ $customer?->email ?? auth()->user()->email }}.</p>
                        </div>
                    </div>
                    <livewire:settings.two-factor.recovery-codes :$requiresConfirmation/>
                @else
                    <p class="text-sm text-base-content/70">
                        Turn this on to protect bookings tied to {{ $customer?->email ?? auth()->user()->email }}. You’ll still get booking updates at {{ $customer?->phone ?: 'your saved phone number' }}.
                    </p>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-base-100 border border-base-300/60 rounded-2xl p-4 space-y-3">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l3 3m5-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                    <h3 class="font-semibold text-base-content">Where alerts go</h3>
                </div>
                <p class="text-sm text-base-content/70">
                    Codes and recovery reminders go to {{ $customer?->email ?? auth()->user()->email }}. We’ll nudge {{ $customer?->phone ?: 'your saved phone' }} if sign-ins look risky.
                </p>
                <a href="{{ route('profile.edit') }}" wire:navigate class="link link-primary text-sm">Update contact info</a>
            </div>

            <div class="bg-gradient-to-br from-primary/10 via-base-100 to-secondary/10 border border-base-300/60 rounded-2xl p-4 space-y-2">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4 5 6.5v5.38C5 15.59 6.91 18.45 12 20c5.09-1.55 7-4.41 7-8.12V6.5z"></path><path stroke-linecap="round" stroke-linejoin="round" d="m9.5 12.5 1.75 1.75L15 10.5"></path></svg>
                    <h3 class="font-semibold text-base-content">What you’ll need</h3>
                </div>
                <ul class="list-disc pl-5 text-sm text-base-content/70 space-y-1">
                    <li>A TOTP app (e.g., 1Password, Authy, Google Authenticator).</li>
                    <li>Access to {{ $customer?->phone ?: 'your saved phone' }} in case you regenerate codes.</li>
                    <li>A safe place to store recovery codes.</li>
                </ul>
            </div>
        </div>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center px-4">
            <div class="absolute inset-0 bg-black/60" wire:click="closeModal"></div>
            <div class="relative w-full max-w-lg bg-base-100 border border-base-300/70 rounded-2xl shadow-2xl">
                <div class="p-5 space-y-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-xl font-semibold text-base-content">{{ $this->modalConfig['title'] }}</h3>
                            <p class="text-sm text-base-content/70">{{ $this->modalConfig['description'] }}</p>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm" wire:click="closeModal">✕</button>
                    </div>

                    @if ($showVerificationStep)
                        <div class="space-y-4">
                            <label class="flex flex-col gap-1">
                                <span class="label-text font-semibold">Authenticator code</span>
                                <x-input-otp
                                    :digits="6"
                                    name="code"
                                    wire:model="code"
                                    autocomplete="one-time-code"
                                />
                                @error('code') <span class="text-sm text-error mt-1">{{ $message }}</span> @enderror
                            </label>
                            <div class="flex items-center gap-3">
                                <button type="button" class="btn btn-ghost flex-1" wire:click="resetVerification">Back</button>
                                <button type="button" class="btn btn-primary flex-1" wire:click="confirmTwoFactor" @disabled(strlen($code) < 6 || $errors->has('code'))>
                                    Confirm
                                </button>
                            </div>
                        </div>
                    @else
                        @error('setupData')
                            <div class="alert alert-error text-sm">{{ $message }}</div>
                        @enderror

                        <div class="flex justify-center">
                            <div class="relative w-64 aspect-square overflow-hidden rounded-2xl border border-base-300/60 bg-base-200">
                                @empty($qrCodeSvg)
                                    <div class="absolute inset-0 flex items-center justify-center animate-pulse">Loading…</div>
                                @else
                                    <div class="flex items-center justify-center h-full p-4">
                                        {!! $qrCodeSvg !!}
                                    </div>
                                @endempty
                            </div>
                        </div>

                        <button
                            type="button"
                            class="btn btn-primary w-full"
                            wire:click="showVerificationIfNecessary"
                            @disabled($errors->has('setupData'))
                        >
                            {{ $this->modalConfig['buttonText'] }}
                        </button>

                        <div class="space-y-3">
                            <p class="text-xs text-base-content/70 uppercase tracking-[0.2em] text-center">Manual key</p>
                            @if ($manualSetupKey)
                                <div
                                    class="flex items-center gap-2"
                                    x-data="{
                                        copied: false,
                                        async copy() {
                                            try {
                                                await navigator.clipboard.writeText('{{ $manualSetupKey }}');
                                                this.copied = true;
                                                setTimeout(() => this.copied = false, 1200);
                                            } catch (_) {}
                                        }
                                    }"
                                >
                                    <input type="text" readonly value="{{ $manualSetupKey }}" class="input input-bordered flex-1">
                                    <button type="button" class="btn btn-outline btn-sm" @click="copy()">
                                        <span x-show="!copied">Copy</span>
                                        <span x-show="copied">Copied</span>
                                    </button>
                                </div>
                            @else
                                <p class="text-sm text-base-content/70 text-center">Generating setup key…</p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-settings.layout>
