<?php

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('whoami', function (Request $request) {
        $webUser = $request->user();
        $staffUser = $request->user('staff');
        $session = $request->hasSession() ? $request->session() : null;

        $guards = collect();

        if ($webUser) {
            $guards->push([
                'guard' => 'web',
                'driver' => config('auth.guards.web.driver', 'N/A'),
                'provider' => config('auth.guards.web.provider', 'N/A'),
                'session_key' => optional(Auth::guard('web'))->getName() ?? 'N/A',
                'user' => [
                    'id' => $webUser->id,
                    'name' => $webUser->name,
                    'email' => $webUser->email,
                    'email_verified_at' => optional($webUser->email_verified_at)->toIso8601String() ?? 'N/A',
                    'two_factor_confirmed_at' => optional(data_get($webUser, 'two_factor_confirmed_at'))->toIso8601String() ?? 'N/A',
                ],
            ]);
        }

        if ($staffUser) {
            $guards->push([
                'guard' => 'staff',
                'driver' => config('auth.guards.staff.driver', 'N/A'),
                'provider' => config('auth.guards.staff.provider', 'N/A'),
                'session_key' => optional(Auth::guard('staff'))->getName() ?? 'N/A',
                'user' => [
                    'id' => $staffUser->id,
                    'name' => $staffUser->name,
                    'email' => $staffUser->email,
                    'role' => $staffUser->role ?? 'N/A',
                    'hotel_id' => $staffUser->hotel_id ?? 'N/A',
                    'hotel' => $staffUser->hotel
                        ? Arr::only($staffUser->hotel->toArray(), ['id', 'name', 'code', 'timezone'])
                        : 'N/A',
                    'employment_status' => $staffUser->employment_status ?? 'N/A',
                    'email_verified_at' => optional($staffUser->email_verified_at)->toIso8601String() ?? 'N/A',
                    'last_login_at' => optional($staffUser->last_login_at)->toIso8601String() ?? 'N/A',
                ],
            ]);
        }

        return response()->json([
            'guards' => $guards->values(),
            'guest' => $guards->isEmpty(),
            'defaults' => [
                'guard' => config('auth.defaults.guard', 'N/A'),
                'passwords' => config('auth.defaults.passwords', 'N/A'),
            ],
            'session' => [
                'id' => $session?->getId() ?? 'N/A',
                'name' => config('session.cookie', 'N/A'),
                'driver' => config('session.driver', 'N/A'),
                'lifetime_minutes' => config('session.lifetime', 'N/A'),
                'active' => $session?->isStarted() ?? false,
            ],
            'cookies' => collect($request->cookies?->all() ?? [])
                ->map(fn ($value, $name) => [
                    'name' => $name,
                    'value' => is_scalar($value) ? $value : json_encode($value),
                ])
                ->values(),
            'active_guard' => Auth::getDefaultDriver() ?? 'N/A',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    })->name('api.whoami');
});
