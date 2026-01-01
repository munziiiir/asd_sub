<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Laravel') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    {{ $head ?? '' }} {{-- optional per-page head stuff --}}
    <script>
        (function() {
            const toDaisyTheme = (mode) => mode === 'dark' ? 'dim' : 'silk';

            const getStoredTheme = () => {
                return localStorage.getItem('theme_preference')
                    || document.cookie.split('; ').find((row) => row.startsWith('theme_preference='))?.split('=')[1]
                    || 'system';
            };

            const computeTheme = (stored) => {
                if (stored === 'system') {
                    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                return stored || 'light';
            };

            const applyStoredTheme = () => {
                try {
                    const stored = getStoredTheme();
                    const mode = computeTheme(stored);
                    document.documentElement.setAttribute('data-theme', toDaisyTheme(mode));
                } catch (e) {
                    document.documentElement.setAttribute('data-theme', 'silk');
                }
            };

            window.applyStoredTheme = applyStoredTheme;
            applyStoredTheme();

            const media = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)');
            if (media && media.addEventListener) {
                media.addEventListener('change', () => {
                    if (getStoredTheme() === 'system') {
                        applyStoredTheme();
                    }
                });
            }

            document.addEventListener('livewire:navigated', applyStoredTheme);
        })();
    </script>
</head>

<body class="min-h-[100dvh] font-sans antialiased">

    @if (! request()->is('settings*') && ! request()->routeIs('bookings.pay'))
        {{ $header ?? '' }}
        @empty($header)
            <x-layouts.app.navbar />
        @endempty
    @endif

    <x-layouts.app.toast />

    <main>
        {{ $slot }}
    </main>

    @unless (
        request()->routeIs('staff.*')
        || request()->routeIs('admin.*')
        || request()->routeIs('bookings.pay')
        || request()->is('settings*')
        || request()->is('user/confirm-password')
    )
        <x-layouts.app.footer />
    @endunless

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const navToggle = document.getElementById('mobile-nav-toggle');

            const closeNav = () => {
                if (navToggle) {
                    navToggle.checked = false;
                }
                document.querySelectorAll('header details[open]').forEach((details) => {
                    details.open = false;
                });
            };

            document.querySelectorAll('header a[href*="#"]').forEach((link) => {
                link.addEventListener('click', closeNav);
            });

            document.querySelectorAll('[data-home-link]').forEach((link) => {
                link.addEventListener('click', (event) => {
                    const targetUrl = new URL(link.getAttribute('href'), window.location.origin);
                    const isSamePage = window.location.pathname === targetUrl.pathname;
                    if (isSamePage && (targetUrl.hash === '' || targetUrl.hash === '#')) {
                        event.preventDefault();
                        closeNav();
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                });
            });
        });
    </script>
    <script>
        (function() {
            try {
                var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;

                if (!tz || tz === 'undefined' || tz === 'null') {
                    document.cookie = 'viewer_timezone=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
                    return;
                }
                var tokenMeta = document.querySelector('meta[name="csrf-token"]');
                var csrf = tokenMeta ? tokenMeta.getAttribute('content') : null;

                console.debug('viewer-timezone: posting tz', tz);

                fetch('/viewer-timezone', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf ?? '',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ timezone: tz })
                }).then(function(response) {
                    if (!response.ok) {
                        return response.json().catch(function() { return {}; }).then(function(data) {
                            console.warn('viewer-timezone post failed', { status: response.status, data: data });
                        });
                    }

                    if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
                        window.Livewire.dispatch('viewer-timezone-detected', { timezone: tz });
                    }
                }).catch(function() {
                    // swallow errors
                });
            } catch (e) {
                // ignore client timezone failures
            }
        })();
    </script>
    @livewireScripts
    @stack('toast-scripts')
    {{ $scripts ?? '' }}
</body>
</html>
