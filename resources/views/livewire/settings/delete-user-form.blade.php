<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="space-y-4">
    <div class="relative mb-2">
        <h3 class="text-lg font-semibold text-base-content">{{ __('Delete account') }}</h3>
        <p class="text-sm text-base-content/70">{{ __('Delete your account and all customer profile data.') }}</p>
    </div>

    <div class="rounded-2xl border border-base-300/70 bg-base-100 p-4 space-y-3">
        <div class="flex items-start gap-3">
            <div class="mt-1 text-error">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 2.82 17a1.6 1.6 0 0 0 1.38 2.4h15.6A1.6 1.6 0 0 0 21.18 17L13.71 3.86a1.6 1.6 0 0 0-2.82 0z"/></svg>
            </div>
            <div class="space-y-1">
                <h4 class="font-semibold text-base-content">{{ __('Delete account') }}</h4>
                <p class="text-sm text-base-content/70">
                    {{ __('Deleting removes your customer profile, bookings, and saved details. This cannot be undone.') }}
                </p>
            </div>
        </div>
        <div x-data="{ open: false }">
            <button type="button" class="btn btn-error btn-outline btn-sm" @click="open = true" data-test="delete-user-button">
                {{ __('Delete account') }}
            </button>

            <div
                x-show="open"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center px-4"
            >
                <div class="absolute inset-0 bg-black/60" @click="open = false"></div>
                <div class="relative w-full max-w-lg bg-base-100 border border-base-300/70 rounded-2xl shadow-2xl p-5 space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-xl font-semibold text-base-content">{{ __('Confirm deletion') }}</h3>
                            <p class="text-sm text-base-content/70">{{ __('Enter your password to permanently delete your account and customer record.') }}</p>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm" @click="open = false">✕</button>
                    </div>

                    <form method="POST" wire:submit="deleteUser" class="space-y-4">
                        <label class="flex flex-col gap-1">
                            <span class="label-text font-semibold">{{ __('Password') }}</span>
                            <input type="password" wire:model="password" class="input input-bordered" autocomplete="current-password">
                            @error('password') <span class="text-sm text-error mt-1">{{ $message }}</span> @enderror
                        </label>

                        <div class="flex items-center justify-end gap-2">
                            <button type="button" class="btn" @click="open = false">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-error" data-test="confirm-delete-user-button">
                                {{ __('Delete') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
