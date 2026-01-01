<?php

use App\Console\Commands\MarkNoShowReservations;
use App\Console\Commands\ResetTemporaryRoomStatuses;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'capture.intended' => \App\Http\Middleware\CaptureIntendedUrl::class,
            'admin.active' => \App\Http\Middleware\EnsureAdminIsActive::class,
            'staff.manager' => \App\Http\Middleware\EnsureStaffIsManager::class,
            'noshow.block' => \App\Http\Middleware\EnsureNoOutstandingNoShowBalance::class,
            'secure.headers' => \App\Http\Middleware\ForceHttpsAndHsts::class,
            'password.rotation' => \App\Http\Middleware\ForcePasswordRotation::class,
        ]);

        // Enforce HTTPS + HSTS on web routes in production-like environments.
        $middleware->appendToGroup('web', \App\Http\Middleware\ForceHttpsAndHsts::class);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('rooms:reset-temporary-statuses')->dailyAt('23:50');
        $schedule->command('reservations:mark-noshow')->dailyAt('23:55');
    })
    ->withCommands([
        MarkNoShowReservations::class,
        ResetTemporaryRoomStatuses::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
