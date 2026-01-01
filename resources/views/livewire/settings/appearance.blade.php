<?php

use App\Models\CustomerUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app.base')] class extends Component {
    public ?CustomerUser $customer = null;

    public string $theme = 'system';

    public function mount(): void
    {
        $user = Auth::user();

        $this->customer = CustomerUser::firstOrCreate(
            ['user_id' => $user->id],
            ['name' => $user->name, 'email' => $user->email]
        );

        $storedTheme = request()->cookie('theme_preference') ?? session('theme_preference');
        $this->theme = in_array($storedTheme, ['light', 'dark', 'system'], true) ? $storedTheme : 'system';
    }

    public function saveAppearance(?string $choice = null): void
    {
        if ($choice !== null) {
            $this->theme = $choice;
        }

        $validated = $this->validate([
            'theme' => ['required', Rule::in(['light', 'dark', 'system'])],
        ]);

        $user = Auth::user();
        $this->customer ??= CustomerUser::firstOrCreate(
            ['user_id' => $user->id],
            ['name' => $user->name, 'email' => $user->email]
        );

        session(['theme_preference' => $validated['theme']]);
        cookie()->queue(cookie()->forever('theme_preference', $validated['theme']));

        $this->dispatch('appearance-updated', theme: $validated['theme']);
    }

    private function nullable(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}; ?>

<x-settings.layout
    :customer="$customer"
    :heading="__('Appearance & identity')"
    :subheading="__('Tune the UI theme and how your guest profile looks around bookings')"
>
    <div class="grid gap-6 lg:grid-cols-[2fr,1fr]">
        <div class="bg-base-100 border border-base-300/60 rounded-2xl p-4 space-y-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-base-content">Theme</h2>
                    <p class="text-sm text-base-content/70">Pick your look. Applies instantly across the app.</p>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <x-action-message on="appearance-updated" class="text-success">Saved.</x-action-message>
                    <span wire:loading wire:target="saveAppearance" class="loading loading-dots loading-sm text-primary"></span>
                </div>
            </div>

            <form class="space-y-6">
                <div class="grid gap-4 md:grid-cols-3">
                    @foreach (['light' => 'Bright', 'dark' => 'Midnight', 'system' => 'Match system'] as $value => $label)
                        <label class="card border border-base-300/70 shadow-sm hover:border-primary cursor-pointer">
                            <div class="card-body gap-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <input
                                            type="radio"
                                            class="radio radio-primary"
                                            name="theme"
                                            value="{{ $value }}"
                                            wire:click="saveAppearance('{{ $value }}')"
                                            x-on:change="window.applyTheme && window.applyTheme('{{ $value }}')"
                                        >
                                        <span class="font-semibold text-base-content">{{ $label }}</span>
                                    </div>
                                    @if ($theme === $value)
                                        <span class="badge badge-success badge-sm">Active</span>
                                    @endif
                                </div>
                                <p class="text-xs text-base-content/70">
                                    @if ($value === 'light')
                                        Crisp whites with soft shadows for daytime browsing.
                                    @elseif ($value === 'dark')
                                        Low-glare interface for night checks and travel.
                                    @else
                                        Follows your device setting automatically.
                                    @endif
                                </p>
                            </div>
                        </label>
                    @endforeach
                </div>
            </form>
        </div>

    </div>

    <script>
        (function() {
            const toDaisyTheme = (mode) => mode === 'dark' ? 'dim' : 'silk';
            const applyTheme = (choice) => {
                let mode = choice;
                if (choice === 'system') {
                    mode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-theme', toDaisyTheme(mode));
                localStorage.setItem('theme_preference', choice);
            };

            window.applyTheme = applyTheme;

            // initial load
            const stored = localStorage.getItem('theme_preference') || '{{ $theme }}';
            applyTheme(stored);

            document.addEventListener('livewire:init', () => {
                Livewire.on('appearance-updated', ({ theme }) => applyTheme(theme));
            });
        })();
    </script>
</x-settings.layout>
