<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised(),
            ],
        ]);

        event(new Registered(($user = User::create($validated))));

        Auth::login($user);

        Session::regenerate();

        $this->redirectIntended(route('home', absolute: false), navigate: true);
    }
}; ?>

<section class="min-h-[100vh] flex items-center justify-center bg-base-200">
    <div class="card w-full max-w-md bg-base-100 shadow-2xl p-8 space-y-6 lg:max-w-2xl lg:flex-row lg:gap-8 lg:items-center">
        <div class="text-center lg:text-left space-y-2 lg:space-y-4 w-full lg:max-w-sm">
            <p class="text-sm uppercase tracking-widest text-primary font-semibold">Create Account</p>
            <h1 class="text-xl md:text-3xl font-bold">Join Lexiqa Guest Portal</h1>
            <p class="text-base-content/70">Book rooms faster, store traveller details, and keep every stay organized.</p>
            <div class="text-sm text-base-content/70 hidden lg:block">
                Already have an account?
                <a href="{{ route('login') }}" class="link link-primary">Sign in</a>
            </div>
        </div>

        <div class="divider lg:divider-horizontal hidden lg:flex"></div>

        <form wire:submit="register" class="space-y-4 lg:space-y-6 w-full">
            <div>
                <label class="input w-full">
                    <svg class="h-[1em] opacity-50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><g stroke-linejoin="round" stroke-linecap="round" stroke-width="2.5" fill="none" stroke="currentColor"><path d="M21 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></g></svg>
                    <input
                        wire:model="name"
                        type="text"
                        placeholder="Full name"
                        required
                        autofocus
                    />
                </label>
                @error('name')
                    <p class="mt-1 text-sm text-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="input w-full">
                    <svg class="h-[1em] opacity-50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><g stroke-linejoin="round" stroke-linecap="round" stroke-width="2.5" fill="none" stroke="currentColor"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></g></svg>
                    <input
                        wire:model="email"
                        type="email"
                        placeholder="Email address"
                        required
                    />
                </label>
                @error('email')
                    <p class="mt-1 text-sm text-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="input w-full">
                    <svg class="h-[1em] opacity-50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><g stroke-linejoin="round" stroke-linecap="round" stroke-width="2.5" fill="none" stroke="currentColor"><path d="M12 17v4m-3 0h6M6 9V7a6 6 0 0 1 12 0v2"/><rect width="16" height="10" x="4" y="9" rx="2"></rect></g></svg>
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
            </div>

            <div>
                <label class="input w-full">
                    <svg class="h-[1em] opacity-50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><g stroke-linejoin="round" stroke-linecap="round" stroke-width="2.5" fill="none" stroke="currentColor"><path d="M12 17v4m-3 0h6M6 9V7a6 6 0 0 1 12 0v2"/><rect width="16" height="10" x="4" y="9" rx="2"></rect></g></svg>
                    <input
                        wire:model="password_confirmation"
                        type="password"
                        placeholder="Confirm password"
                        required
                    />
                </label>
                @error('password_confirmation')
                    <p class="mt-1 text-sm text-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:max-lg:flex md:justify-end">
                <button type="submit" class="btn btn-primary w-full md:max-lg:w-auto">
                    Create account
                </button>
            </div>
        </form>

        <div class="text-center text-sm text-base-content/70 lg:hidden w-full">
            Already have an account?
            <a href="{{ route('login') }}" class="link link-primary">Sign in</a>
        </div>
    </div>
</section>
