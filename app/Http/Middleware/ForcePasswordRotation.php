<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordRotation
{
    private int $maxAgeDays = 180;

    public function handle(Request $request, Closure $next): Response
    {
        // Admin guard: all admins must rotate.
        $adminUser = $request->user('admin') ?: Auth::guard('admin')->user();
        if ($adminUser) {
            $isExpired = $this->isExpired($adminUser->last_password_changed_at);
            if ($isExpired) {
                if (! $this->isAdminPasswordResetRoute($request)) {
                    $this->rememberIntended($request);

                    return redirect()
                        ->route('admin.password.expired')
                        ->withErrors(['username' => 'Your password has expired. Please reset it to continue.']);
                }
            }
        }

        // Staff guard: only managers must rotate per spec.
        $staff = $request->user('staff') ?: Auth::guard('staff')->user();
        if ($staff) {
            if (($staff->role ?? '') === 'manager' && $this->isExpired($staff->last_password_changed_at)) {
                if (! $this->isStaffPasswordResetRoute($request)) {
                    $this->rememberIntended($request);

                    return redirect()
                        ->route('staff.password.expired')
                        ->withErrors(['email' => 'Your password has expired. Please reset it to continue.']);
                }
            }
        }

        return $next($request);
    }

    private function isExpired($lastChangedAt): bool
    {
        if (! $lastChangedAt) {
            return true;
        }

        $daysDiff = abs(now()->diffInDays($lastChangedAt));
        return $daysDiff >= $this->maxAgeDays;
    }

    private function isAdminPasswordResetRoute(Request $request): bool
    {
        return $request->routeIs('admin.password.expired', 'admin.password.expired.update');
    }

    private function rememberIntended(Request $request): void
    {
        if ($request->hasSession() && ! $request->session()->has('url.intended')) {
            redirect()->setIntendedUrl($request->fullUrl());
        }
    }

    private function isStaffPasswordResetRoute(Request $request): bool
    {
        return $request->routeIs('staff.password.expired', 'staff.password.expired.update');
    }
}
