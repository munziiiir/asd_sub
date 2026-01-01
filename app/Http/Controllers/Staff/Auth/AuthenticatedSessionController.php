<?php

namespace App\Http\Controllers\Staff\Auth;

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
        return view('staff.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email:strict'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $this->ensureIsNotRateLimited($credentials['email'] ?? '');

        $attempt = [
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'employment_status' => 'active',
        ];

        if (! Auth::guard('staff')->attempt($attempt, $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($credentials['email']));
            AuditLogger::log('staff.login.failed', ['email' => $credentials['email']], false);
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($credentials['email']));
        $request->session()->regenerate();

        tap(Auth::guard('staff')->user(), fn ($user) => $user?->forceFill(['last_login_at' => now()])->save());
        AuditLogger::log('staff.login.success', ['email' => $credentials['email']], true);

        return redirect()->intended(route('staff.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('staff')->logout();

        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('staff.login');
    }

    protected function ensureIsNotRateLimited(string $email): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($email), 5)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => RateLimiter::availableIn($this->throttleKey($email)),
                'minutes' => ceil(RateLimiter::availableIn($this->throttleKey($email)) / 60),
            ]),
        ]);
    }

    protected function throttleKey(string $email): string
    {
        return mb_strtolower($email) . '|' . request()->ip();
    }
}
