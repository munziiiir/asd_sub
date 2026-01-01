<?php

use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public array $recoveryCodes = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->loadRecoveryCodes();
    }

    /**
     * Generate new recovery codes for the user.
     */
    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generateNewRecoveryCodes): void
    {
        $generateNewRecoveryCodes(auth()->user());

        $this->loadRecoveryCodes();
    }

    /**
     * Load the recovery codes for the user.
     */
    private function loadRecoveryCodes(): void
    {
        $user = auth()->user();

        if ($user->hasEnabledTwoFactorAuthentication() && $user->two_factor_recovery_codes) {
            try {
                $this->recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
            } catch (Exception) {
                $this->addError('recoveryCodes', 'Failed to load recovery codes');

                $this->recoveryCodes = [];
            }
        }
    }
}; ?>

<div class="card bg-base-100 shadow-sm border border-base-300/60" x-data="{ open: false }" wire:cloak>
    <div class="card-body space-y-4">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v13H4z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 4V2h6v2M4 7h16"/></svg>
                <div>
                    <h3 class="font-semibold text-base-content">{{ __('Recovery codes') }}</h3>
                    <p class="text-sm text-base-content/70">{{ __('Use these if you lose access to your authenticator app. Store them offline.') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if (filled($recoveryCodes))
                    <button type="button" class="btn btn-outline btn-sm" wire:click="regenerateRecoveryCodes">
                        {{ __('Regenerate') }}
                    </button>
                @endif
                <button type="button" class="btn btn-ghost btn-sm" @click="open = !open">
                    <span x-show="!open">{{ __('Show') }}</span>
                    <span x-show="open">{{ __('Hide') }}</span>
                </button>
            </div>
        </div>

        <div x-show="open" x-transition>
            <div class="space-y-3">
                @error('recoveryCodes')
                    <div class="alert alert-error text-sm">{{ $message }}</div>
                @enderror

                @if (filled($recoveryCodes))
                    <div class="grid gap-2 font-mono text-sm bg-base-200/70 border border-base-300/60 rounded-xl p-4" role="list">
                        @foreach($recoveryCodes as $code)
                            <span
                                role="listitem"
                                class="px-3 py-2 rounded-lg bg-base-100 border border-base-300/60 select-text"
                                wire:loading.class="opacity-50"
                            >
                                {{ $code }}
                            </span>
                        @endforeach
                    </div>
                    <p class="text-xs text-base-content/70">
                        {{ __('Each code is single-use. Regenerate if you misplace them, and store the new set securely.') }}
                    </p>
                @else
                    <p class="text-sm text-base-content/70">{{ __('Codes will show here once two-factor is enabled.') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
