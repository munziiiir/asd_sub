<x-layouts.auth>
    <div class="relative min-h-[100dvh] overflow-hidden bg-gradient-to-br from-base-300/50 via-base-200 to-base-100">
        <div class="absolute inset-0 opacity-40">
            <div class="pointer-events-none absolute -left-10 -top-10 h-48 w-48 rounded-full bg-primary/15 blur-3xl"></div>
            <div class="pointer-events-none absolute right-0 bottom-0 h-56 w-56 rounded-full bg-secondary/10 blur-3xl"></div>
        </div>

        <div class="relative mx-auto flex min-h-[100dvh] max-w-4xl items-center px-4 py-12">
            <div class="w-full rounded-3xl border border-base-300/70 bg-base-100 shadow-2xl shadow-base-300/40 p-6 sm:p-8 space-y-6">
                <div class="flex items-start gap-3 rounded-2xl border border-base-300/60 bg-base-200/60 p-4">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 11.5a2 2 0 1 0-2-2 2 2 0 0 0 2 2zm0 0c-3 0-5.5 1.5-5.5 3.5V17h11v-2c0-2-2.5-3.5-5.5-3.5zM16 7.5V6a4 4 0 1 0-8 0v1.5"/></svg>
                    </span>
                    <div class="space-y-1">
                        <p class="text-xs uppercase tracking-[0.2em] text-base-content/70">Security</p>
                        <h1 class="text-2xl font-semibold text-base-content">Confirm your password</h1>
                        <p class="text-sm text-base-content/70">
                            For your safety, please re-enter your password before we continue.
                        </p>
                        <p class="text-xs text-base-content/60">If you’re not sure why you’re seeing this, you can go back to the previous page.</p>
                    </div>
                </div>

                <x-auth-session-status class="text-left" :status="session('status')" />

                <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-6">
                    @csrf

                    <label class="flex flex-col gap-1 w-full space-y-2">
                        <span class="label-text font-semibold text-base-content/80 block">Password</span>
                        <input
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            placeholder="Enter your password"
                            class="input input-bordered input-lg w-full px-4 py-3"
                        >
                    </label>

                    <div class="grid grid-cols-2 gap-3 my-4">
                        <a
                            href="{{ url()->previous() }}"
                            class="btn btn-outline w-full"
                            onclick="event.preventDefault(); if (window.history.length > 1) { history.back(); } else { window.location='{{ url()->previous() }}'; }"
                        >
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary w-full" data-test="confirm-password-button">
                            {{ __('Confirm') }}
                        </button>
                    </div>
                </form>

                <p class="text-xs text-base-content/60">
                    Need help?
                    <a class="link link-primary" href="mailto:hello@loxixa.test">hello@loxixa.test</a>
                </p>
            </div>
        </div>
    </div>
</x-layouts.auth>
