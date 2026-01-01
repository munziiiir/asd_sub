@props([
    'heading' => '',
    'subheading' => '',
    'customer' => null,
    'backUrl' => null,
])

@php
    $user = auth()->user();
    $canVerifyEmail = $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail;
    $emailVerified = $canVerifyEmail ? $user?->hasVerifiedEmail() : (bool) $user?->email_verified_at;
    $twoFactorOn = $user?->hasEnabledTwoFactorAuthentication() ?? false;
    $city = trim(($customer?->city ?? '') . ' ' . ($customer?->country ?? ''));
    $nav = [
        ['label' => 'Account', 'route' => 'account.edit', 'icon' => 'user'],
        ['label' => 'Address', 'route' => 'address.edit', 'icon' => 'map'],
        ['label' => 'Payment', 'route' => 'payment.edit', 'icon' => 'card'],
    ];

    if (Laravel\Fortify\Features::canManageTwoFactorAuthentication()) {
        $nav[] = ['label' => 'Two Factor', 'route' => 'two-factor.show', 'icon' => 'lock'];
    }

    $nav[] = ['label' => 'Appearance', 'route' => 'appearance.edit', 'icon' => 'sun-moon'];

    $previous = url()->previous();
    if (! str_contains($previous, 'settings')) {
        session(['settings.return_to' => $previous]);
    }

    $storedBack = session('settings.return_to', route('home'));
    $backUrl = $backUrl ?? $storedBack;
@endphp

<section class="bg-base-200 min-h-screen px-3 sm:px-6 py-6">
    <div class="max-w-6xl mx-auto h-[calc(100vh-3rem)] lg:h-[calc(100vh-2rem)]">
        <div class="flex h-full gap-6 bg-base-100 border border-base-300/70 rounded-3xl shadow-sm overflow-hidden">
            <aside class="bg-base-200/80 border-r border-base-300/60 p-5 space-y-6 w-72 shrink-0 hidden lg:flex flex-col">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="btn btn-ghost btn-sm gap-2"
                            onclick="window.location='{{ $backUrl }}'"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                            Back
                        </button>
                    </div>
                    <span class="text-xs uppercase tracking-[0.2em] text-base-content/70">Settings</span>
                </div>

                <div class="flex items-center gap-3 rounded-xl border border-base-300/60 bg-base-200/70 px-3 py-3">
                    <div class="avatar">
                        <div class="w-12 rounded-full border border-base-300/70">
                            @php
                                $avatar = $customer?->avatar ?: "https://ui-avatars.com/api/?name=" . urlencode($customer?->name ?? $user?->name ?? '') . "&background=0D8ABC&color=fff";
                            @endphp
                            <img src="{{ $avatar }}" alt="Profile photo" class="object-cover">
                        </div>
                    </div>
                    <div class="space-y-0.5">
                        <p class="font-semibold text-base-content">{{ $customer?->name ?? $user?->name }}</p>
                        <p class="text-sm text-base-content/70">{{ $customer?->email ?? $user?->email }}</p>
                        @if ($city !== '')
                            <p class="text-xs text-base-content/60">{{ $city }}</p>
                        @endif
                    </div>
                </div>

                <nav class="space-y-1">
                    @foreach ($nav as $item)
                        @php
                            $isActive = request()->routeIs($item['route']);
                            $aliasRoutes = ['profile.edit', 'password.edit'];
                            $isActive = request()->routeIs($item['route']) || ($item['route'] === 'account.edit' && request()->routeIs($aliasRoutes));
                        @endphp
                        <a
                            href="{{ route($item['route']) }}"
                            wire:navigate
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition border border-transparent hover:border-base-300/80 {{ $isActive ? 'bg-primary/10 border-primary/40 text-primary font-semibold' : 'text-base-content/80' }}"
                        >
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-base-200 text-base-content/80">
                                @switch($item['icon'])
                                    @case('user')
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 0c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5z" /></svg>
                                        @break
                                    @case('map')
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m9 20-6-2V6l6 2 6-2 6 2v12l-6-2-6 2V8"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 6v14"/></svg>
                                        @break
                                    @case('card')
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h3"/></svg>
                                        @break
                                    @case('lock')
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect width="16" height="11" x="4" y="11" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M7 11V8a5 5 0 0 1 10 0v3"/></svg>
                                        @break
                                    @default
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75v16.5m-6-12h7.5M6 12h5m-5 3.75h7.5m1.5-9 4.5 4.5-4.5 4.5"/></svg>
                                @endswitch
                            </span>
                            <div class="flex-1 flex items-center justify-between">
                                <span>{{ $item['label'] }}</span>
                                @if ($item['route'] === 'two-factor.show')
                                    <span class="badge badge-outline badge-sm {{ $twoFactorOn ? 'badge-success' : '' }}">
                                        {{ $twoFactorOn ? 'On' : 'Off' }}
                                    </span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </nav>

                <div class="space-y-2 text-xs text-base-content/60">
                    <div class="flex items-center justify-between">
                        <span>Email verification</span>
                        <span class="badge badge-sm {{ $emailVerified ? 'badge-success' : 'badge-ghost' }}">
                            {{ $emailVerified ? 'Verified' : 'Pending' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Two-factor</span>
                        <span class="badge badge-sm {{ $twoFactorOn ? 'badge-success' : 'badge-ghost' }}">
                            {{ $twoFactorOn ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                </div>
            </aside>

            <aside class="bg-base-200/80 border-b border-base-300/60 p-4 space-y-4 w-full lg:hidden">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="btn btn-ghost btn-sm gap-2"
                            onclick="window.location='{{ $backUrl }}'"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                            Back
                        </button>
                    </div>
                    <span class="text-xs uppercase tracking-[0.2em] text-base-content/70">Settings</span>
                </div>

                <nav class="grid grid-cols-2 gap-2 text-sm">
                    @foreach ($nav as $item)
                        @php
                            $aliasRoutes = ['profile.edit', 'password.edit'];
                            $isActive = request()->routeIs($item['route']) || ($item['route'] === 'account.edit' && request()->routeIs($aliasRoutes));
                        @endphp
                        <a
                            href="{{ route($item['route']) }}"
                            wire:navigate
                            class="flex items-center gap-2 rounded-xl px-3 py-2 border text-base-content/80 hover:border-base-300/80 {{ $isActive ? 'bg-primary/10 border-primary/40 text-primary font-semibold' : 'border-transparent' }}"
                        >
                            @switch($item['icon'])
                                @case('user')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 0c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5z" /></svg>
                                    @break
                                @case('map')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m9 20-6-2V6l6 2 6-2 6 2v12l-6-2-6 2V8"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 6v14"/></svg>
                                    @break
                                @case('card')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h3"/></svg>
                                    @break
                                @case('lock')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect width="16" height="11" x="4" y="11" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M7 11V8a5 5 0 0 1 10 0v3"/></svg>
                                    @break
                                @default
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75v16.5m-6-12h7.5M6 12h5m-5 3.75h7.5m1.5-9 4.5 4.5-4.5 4.5"/></svg>
                            @endswitch
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>
            </aside>

            <div class="flex-1 min-h-0 overflow-y-auto p-6 lg:p-8 bg-base-50">
                <div class="space-y-4">
                    <div class="space-y-1">
                        <p class="text-sm uppercase tracking-[0.2em] text-base-content/70">Settings</p>
                        <h1 class="text-3xl md:text-4xl font-bold text-base-content">{{ $heading }}</h1>
                        <p class="text-base-content/70">{{ $subheading }}</p>
                    </div>

                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</section>
