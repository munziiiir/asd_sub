<?php

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Features;
use App\Support\AuditLogger;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        $user = $this->validateCredentials();

        if (Features::canManageTwoFactorAuthentication() && $user->hasEnabledTwoFactorAuthentication()) {
            Session::put([
                'login.id' => $user->getKey(),
                'login.remember' => $this->remember,
            ]);

            $this->redirect(route('two-factor.login'), navigate: true);

            return;
        }

        Auth::login($user, $this->remember);

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $this->redirectIntended(default: route('home', absolute: false), navigate: true);
    }

    /**
     * Validate the user's credentials.
     */
    protected function validateCredentials(): User
    {
        $user = Auth::getProvider()->retrieveByCredentials(['email' => $this->email, 'password' => $this->password]);

        if (! $user || ! Auth::getProvider()->validateCredentials($user, ['password' => $this->password])) {
            RateLimiter::hit($this->throttleKey());
            AuditLogger::log('user.login.failed', ['email' => $this->email], false);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        AuditLogger::log('user.login.success', ['email' => $this->email], true, $user);

        return $user;
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}; ?>

<section class="min-h-[100vh] flex items-center justify-center bg-base-200">
    <div class="card w-full max-w-md bg-base-100 shadow-2xl p-8 space-y-6 lg:max-w-2xl lg:flex-row-reverse lg:h-auto lg:items-center lg:justify-between">
        <div class="text-center lg:text-left space-y-2 lg:space-y-4 w-full lg:max-w-sm">
            <p class="text-sm uppercase tracking-widest text-primary font-semibold">Sign In</p>
            <h1 class="text-xl md:text-3xl font-bold">Welcome back</h1>
            <p class="text-base-content/70">Sign in to manage reservations, invoices, and profile details.</p>
            <div class="text-sm text-base-content/70 hidden lg:block">
                Need an account?
                <a href="{{ route('register') }}" class="link link-primary">Create one</a>
            </div>
        </div>

        <div class="divider lg:divider-horizontal hidden lg:flex"></div>

        <form wire:submit="login" class="space-y-4 lg:space-y-6 w-full pl-0 lg:pl-5">
            <div>
                <label class="input w-full">
                    <svg class="h-[1em] opacity-50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><g stroke-linejoin="round" stroke-linecap="round" stroke-width="2.5" fill="none" stroke="currentColor"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></g></svg>
                    <input
                        wire:model="email"
                        type="email"
                        placeholder="Email address"
                        required
                        autofocus
                    />
                </label>
                @error('email')
                    <p class="mt-1 text-sm text-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="input w-full">
                    <svg class="h-[1em] opacity-50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><g stroke-linejoin="round" stroke-linecap="round" stroke-width="2.5" fill="none" stroke="currentColor"><path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"></path><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"></circle></g></svg>
                    <input
                        wire:model="password"
                        type="password"
                        placeholder="Password"
                        required
                    />
                </label>
                @error('password')
                    <p class="mt-1 text-sm text-error">{{ $message }}</p>
                @enderror

                @if (Route::has('password.request'))
                    <div class="text-right mt-2">
                        <a class="link link-hover text-sm" href="{{ route('password.request') }}">Forgot your password?</a>
                    </div>
                @endif
            </div>

            <div class="md:max-lg:flex md:justify-between">
                <label class="label cursor-pointer gap-3 mb-4 md:max-lg:mb-0">
                    <span class="label-text">Remember me</span>
                    <input type="checkbox" wire:model="remember" class="toggle toggle-primary">
                </label>
                <button type="submit" class="btn btn-primary w-full md:max-lg:w-auto">
                    Sign in
                </button>
            </div>
        </form>

        <div class="text-center text-sm text-base-content/70 lg:hidden w-full">
            Need an account?
            <a href="{{ route('register') }}" class="link link-primary">Create one</a>
        </div>
    </div>
</section>
