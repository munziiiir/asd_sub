<?php

use App\Models\CustomerUser;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Support\ReservationFolioService;

test('cancelling more than 14 days before check-in is free and refunds deposit', function () {
    $hotel = Hotel::create(['name' => 'Test Hotel', 'code' => 'TST']);
    $user = User::factory()->create();
    $this->actingAs($user);

    $customer = CustomerUser::create(['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email]);

    $reservation = Reservation::create([
        'hotel_id' => $hotel->id,
        'customer_id' => $customer->id,
        'status' => 'Confirmed',
        'check_in_date' => now()->addDays(20)->toDateString(),
        'check_out_date' => now()->addDays(25)->toDateString(),
        'adults' => 2,
        'children' => 0,
        'nightly_rate' => 300.00,
    ]);

    $folioService = app(ReservationFolioService::class);
    $folioService->syncRoomCharges($reservation, 'initial');
    $folio = $folioService->ensureOpenFolio($reservation);

    Payment::create([
        'folio_id' => $folio->id,
        'method' => 'Card',
        'amount' => 300.00,
        'txn_ref' => 'DEP',
        'paid_at' => now(),
    ]);

    $result = $folioService->applyCancellationPolicy($reservation, 'guest');

    expect($reservation->fresh()->status)->toBe('Cancelled');
    expect($result['fee'])->toBe(0.0);
    expect($result['refunded'])->toBe(300.0);
    expect(round($folioService->paymentsTotal($folio), 2))->toBe(0.0);
});

test('cancelling 3-14 days before check-in charges 50 percent of first night and refunds remainder', function () {
    $hotel = Hotel::create(['name' => 'Test Hotel', 'code' => 'TST']);
    $user = User::factory()->create();
    $this->actingAs($user);

    $customer = CustomerUser::create(['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email]);

    $reservation = Reservation::create([
        'hotel_id' => $hotel->id,
        'customer_id' => $customer->id,
        'status' => 'Confirmed',
        'check_in_date' => now()->addDays(10)->toDateString(),
        'check_out_date' => now()->addDays(15)->toDateString(),
        'adults' => 2,
        'children' => 0,
        'nightly_rate' => 300.00,
    ]);

    $folioService = app(ReservationFolioService::class);
    $folioService->syncRoomCharges($reservation, 'initial');
    $folio = $folioService->ensureOpenFolio($reservation);

    Payment::create([
        'folio_id' => $folio->id,
        'method' => 'Card',
        'amount' => 300.00,
        'txn_ref' => 'DEP',
        'paid_at' => now(),
    ]);

    $result = $folioService->applyCancellationPolicy($reservation, 'guest');

    expect($reservation->fresh()->status)->toBe('Cancelled');
    expect($result['fee'])->toBe(150.0);
    expect($result['refunded'])->toBe(150.0);
    expect(round($folioService->paymentsTotal($folio), 2))->toBe(150.0);
});

test('cancelling within 72 hours charges full first night and keeps deposit', function () {
    $hotel = Hotel::create(['name' => 'Test Hotel', 'code' => 'TST']);
    $user = User::factory()->create();
    $this->actingAs($user);

    $customer = CustomerUser::create(['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email]);

    $reservation = Reservation::create([
        'hotel_id' => $hotel->id,
        'customer_id' => $customer->id,
        'status' => 'Confirmed',
        'check_in_date' => now()->addHours(48)->toDateString(),
        'check_out_date' => now()->addDays(5)->toDateString(),
        'adults' => 2,
        'children' => 0,
        'nightly_rate' => 300.00,
    ]);

    $folioService = app(ReservationFolioService::class);
    $folioService->syncRoomCharges($reservation, 'initial');
    $folio = $folioService->ensureOpenFolio($reservation);

    Payment::create([
        'folio_id' => $folio->id,
        'method' => 'Card',
        'amount' => 300.00,
        'txn_ref' => 'DEP',
        'paid_at' => now(),
    ]);

    $result = $folioService->applyCancellationPolicy($reservation, 'guest');

    expect($reservation->fresh()->status)->toBe('Cancelled');
    expect($result['fee'])->toBe(300.0);
    expect($result['refunded'])->toBe(0.0);
    expect(round($folioService->paymentsTotal($folio), 2))->toBe(300.0);
});

test('unpaid no-show blocks starting a new booking until balance is settled', function () {
    $hotel = Hotel::create(['name' => 'Test Hotel', 'code' => 'TST']);
    $user = User::factory()->create();
    $this->actingAs($user);

    $customer = CustomerUser::create(['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email]);

    $reservation = Reservation::create([
        'hotel_id' => $hotel->id,
        'customer_id' => $customer->id,
        'status' => 'NoShow',
        'check_in_date' => now()->subDay()->toDateString(),
        'check_out_date' => now()->addDays(4)->toDateString(),
        'adults' => 2,
        'children' => 0,
        'nightly_rate' => 300.00,
    ]);

    $folioService = app(ReservationFolioService::class);
    $folioService->syncRoomCharges($reservation, 'no-show baseline');
    $folio = $folioService->ensureOpenFolio($reservation);

    Payment::create([
        'folio_id' => $folio->id,
        'method' => 'Card',
        'amount' => 300.00,
        'txn_ref' => 'DEP',
        'paid_at' => now(),
    ]);

    $response = $this->get(route('booking.start'));
    $response->assertRedirect(route('bookings.pay', $reservation));
});

