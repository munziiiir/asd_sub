<?php

use App\Http\Controllers\Admin\AdminHotelController;
use App\Http\Controllers\Admin\AdminRoomController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController as AdminAuthenticatedSessionController;
use App\Http\Controllers\Admin\Auth\ExpiredPasswordController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\AdminStaffUserController;
use App\Http\Controllers\Staff\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Staff\Auth\ExpiredPasswordController as StaffExpiredPasswordController;
use App\Http\Controllers\Staff\ReservationController as StaffReservationController;
use App\Http\Controllers\Staff\Manager\FrontdeskStaffController;
use App\Http\Controllers\Staff\Manager\ReportsController;
use App\Http\Controllers\Staff\Manager\RoomTypeController as ManagerRoomTypeController;
use App\Http\Controllers\UserBookingController;
use App\Models\Country;
use App\Models\Hotel;
use App\Http\Controllers\Staff\CheckInOutController;
use App\Http\Controllers\Staff\RoomStatusController;
use App\Http\Controllers\Staff\BillingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    $locations = Country::whereHas('hotels')
        ->orderBy('name')
        ->pluck('name')
        ->filter()
        ->values();

    return view('index', [
        'locations' => $locations,
    ]);
})->name('home');

Route::get('/locations', function () {
    $locations = Country::whereHas('hotels')
        ->orderBy('name')
        ->pluck('name')
        ->filter()
        ->values();

    return view('locations', ['locations' => $locations]);
})->name('locations.index');

Route::middleware(['auth'])->prefix('bookings')->name('bookings.')->group(function () {
    Route::get('/', [UserBookingController::class, 'index'])->name('index');
    Route::get('{reservation}/pay', [UserBookingController::class, 'pay'])->name('pay');
    Route::get('{reservation}', [UserBookingController::class, 'show'])->name('show');
    Route::patch('{reservation}', [UserBookingController::class, 'update'])->name('update');
    Route::patch('{reservation}/cancel', [UserBookingController::class, 'cancel'])->name('cancel');
});
Route::middleware(['auth'])->group(function () {
    Route::view('/book', 'booking')->middleware('noshow.block')->name('booking.start');
});
Route::post('/viewer-timezone', function (Request $request) {
    $tzRaw = $request->input('timezone');
    $tz = is_string($tzRaw) ? trim($tzRaw) : '';

    if ($tz === '') {
        return response()->json(['error' => 'Missing timezone'], 422);
    }

    $request->session()->put('viewer_timezone', $tz);

    return response()
        ->json(['status' => 'ok', 'timezone' => $tz])
        ->withCookie(cookie()->make('viewer_timezone', $tz, 0)); // session cookie
})->name('viewer-timezone');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/account');

    Volt::route('settings/account', 'settings.account')->name('account.edit');
    Volt::route('settings/profile', 'settings.account')->name('profile.edit'); // legacy alias
    Volt::route('settings/password', 'settings.account')->name('password.edit'); // legacy alias
    Volt::route('settings/address', 'settings.address')->name('address.edit');
    Volt::route('settings/payment', 'settings.payment')->name('payment.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AdminAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AdminAuthenticatedSessionController::class, 'store'])->name('login.store');
    });

    Route::middleware(['auth:admin', 'admin.active', 'password.rotation'])->group(function () {
        Route::get('password/expired', [ExpiredPasswordController::class, 'edit'])->name('password.expired');
        Route::post('password/expired', [ExpiredPasswordController::class, 'update'])->name('password.expired.update');
    });

    Route::middleware(['auth:admin', 'admin.active', 'password.rotation'])->group(function () {
        Route::redirect('/', '/admin/dashboard');
        Route::get('dashboard', AdminDashboardController::class)->name('dashboard');
        Route::resource('users', AdminUserController::class)
            ->parameters(['users' => 'admin_user'])
            ->except('show');
        Route::resource('hotels', AdminHotelController::class)->except('show');
        Route::resource('hotels.rooms', AdminRoomController::class)->except('show');
        Route::resource('staffusers', AdminStaffUserController::class)->except('show');
        Route::post('logout', [AdminAuthenticatedSessionController::class, 'destroy'])->name('logout');
    });
});

Route::get('staff', function () {
    return auth('staff')->check()
        ? redirect()->route('staff.dashboard')
        : redirect()->route('staff.login');
})->name('staff.home');

Route::prefix('staff')->name('staff.')->group(function () {
    Route::middleware('guest:staff')->group(function () {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedSessionController::class, 'store']);
    });

    Route::middleware(['auth:staff', 'password.rotation'])->group(function () {
        Route::get('password/expired', [StaffExpiredPasswordController::class, 'edit'])->name('password.expired');
        Route::post('password/expired', [StaffExpiredPasswordController::class, 'update'])->name('password.expired.update');
    });

    Route::middleware(['auth:staff', 'password.rotation'])->group(function () {
        Route::get('/', \App\Http\Controllers\Staff\DashboardController::class)->name('dashboard');
        Route::get('dashboard', fn () => redirect()->route('staff.dashboard'));
        Route::view('frontdesk', 'staff.frontdesk')->name('frontdesk');

        // reservations
        Route::prefix('reservations')->name('reservations.')->group(function () {
            Route::get('create', [StaffReservationController::class, 'create'])->name('create');
            Route::get('/', [StaffReservationController::class, 'index'])->name('index');
            Route::get('{reservation}', [StaffReservationController::class, 'show'])->name('show');
            Route::patch('{reservation}', [StaffReservationController::class, 'update'])->name('update');
            Route::patch('{reservation}/cancel', [StaffReservationController::class, 'cancel'])->name('cancel');
        });

        // check-in/out hub
        Route::get('check-io', [CheckInOutController::class, 'index'])->name('check-io.index');
        Route::get('check-io/check-in', [CheckInOutController::class, 'createCheckIn'])->name('check-io.check-in');
        Route::get('check-io/check-out', [CheckInOutController::class, 'createCheckOut'])->name('check-io.check-out');

        // room status & housekeeping
        Route::prefix('rooms')->name('rooms.')->group(function () {
            Route::get('/', [RoomStatusController::class, 'index'])->name('index');
            Route::get('{room}', [RoomStatusController::class, 'show'])->name('show');
            Route::patch('{room}', [RoomStatusController::class, 'update'])->name('update');
        });

        // payments & billing
        Route::prefix('billing')->name('billing.')->group(function () {
            Route::get('/', [BillingController::class, 'index'])->name('index');
            Route::get('{reservation}', [BillingController::class, 'show'])->name('show');
            Route::post('{reservation}/charges', [BillingController::class, 'storeCharge'])->name('charges.store');
        });

        Route::middleware('staff.manager')->prefix('manager')->name('manager.')->group(function () {
            Route::resource('frontdesk-staff', FrontdeskStaffController::class)
                ->parameters(['frontdesk-staff' => 'staff'])
                ->except(['show', 'destroy']);

            Route::resource('room-types', ManagerRoomTypeController::class)
                ->parameters(['room-types' => 'roomType'])
                ->except(['show', 'destroy']);

            Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
            Route::get('reports/export.csv', [ReportsController::class, 'exportCsv'])->name('reports.export.csv');
            Route::get('reports/export.pdf', [ReportsController::class, 'exportPdf'])->name('reports.export.pdf');
        });

        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    });
});

require __DIR__.'/auth.php';

// alert toast test routes: delete later
if (app()->environment('local')) {
    Route::get('dev/toast/success', function () {
        return redirect()->route('staff.frontdesk')->with('status', 'Sample success toast — everything worked!');
    })->name('dev.toast.success');

    Route::get('dev/toast/error', function () {
        return redirect()->route('staff.frontdesk')->withErrors(['status' => 'Sample error toast — something went wrong.']);
    })->name('dev.toast.error');
}
