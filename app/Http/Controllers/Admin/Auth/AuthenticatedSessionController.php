<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $this->ensureIsNotRateLimited($credentials['username'] ?? '');

        $attempt = [
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'is_active' => true,
        ];

        if (! Auth::guard('admin')->attempt($attempt, $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($credentials['username']));
            AuditLogger::log('admin.login.failed', ['username' => $credentials['username']], false);
            throw ValidationException::withMessages([
                'username' => 'Invalid credentials or inactive account.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($credentials['username']));
        $request->session()->regenerate();

        tap(Auth::guard('admin')->user(), fn ($user) => $user?->forceFill(['last_login_at' => now()])->save());
        AuditLogger::log('admin.login.success', ['username' => $credentials['username']], true);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    protected function ensureIsNotRateLimited(string $username): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($username), 5)) {
            return;
        }

        throw ValidationException::withMessages([
            'username' => __('auth.throttle', [
                'seconds' => RateLimiter::availableIn($this->throttleKey($username)),
                'minutes' => ceil(RateLimiter::availableIn($this->throttleKey($username)) / 60),
            ]),
        ]);
    }

    protected function throttleKey(string $username): string
    {
        return mb_strtolower($username) . '|' . request()->ip();
    }
}
