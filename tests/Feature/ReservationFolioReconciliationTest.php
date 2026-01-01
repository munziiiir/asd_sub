<?php

use App\Models\Charge;
use App\Models\CustomerUser;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Support\ReservationFolioService;

test('editing stay shorter creates a negative room charge adjustment', function () {
    $hotel = Hotel::create([
        'name' => 'Test Hotel',
        'code' => 'TST',
    ]);

    $user = User::factory()->create();
    $customer = CustomerUser::create([
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ]);

    $reservation = Reservation::create([
        'hotel_id' => $hotel->id,
        'customer_id' => $customer->id,
        'status' => 'Confirmed',
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(5)->toDateString(), // 5 nights
        'adults' => 2,
        'children' => 0,
        'nightly_rate' => 300.00,
    ]);

    $folioService = app(ReservationFolioService::class);
    $folioService->syncRoomCharges($reservation, 'initial booking');
    $folio = $folioService->ensureOpenFolio($reservation);

    Payment::create([
        'folio_id' => $folio->id,
        'method' => 'Card',
        'amount' => 300.00,
        'txn_ref' => 'TEST-DEP',
        'paid_at' => now(),
    ]);

    $folioService->enforceDepositStatus($reservation);

    $reservation->forceFill([
        'check_out_date' => now()->addDays(3)->toDateString(), // 3 nights
    ])->save();

    $folioService->syncRoomCharges($reservation, 'guest edit');

    $adjustment = Charge::query()
        ->where('folio_id', $folio->id)
        ->where('description', 'like', 'Reservation adjustment: Room charges%')
        ->orderByDesc('id')
        ->first();

    expect($adjustment)->not->toBeNull();
    expect((float) $adjustment->total_amount)->toBe(-600.00);
});

test('adding value to a confirmed booking returns it to pending when deposit is insufficient', function () {
    $hotel = Hotel::create([
        'name' => 'Test Hotel',
        'code' => 'TST',
    ]);

    $user = User::factory()->create();
    $customer = CustomerUser::create([
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ]);

    $reservation = Reservation::create([
        'hotel_id' => $hotel->id,
        'customer_id' => $customer->id,
        'status' => 'Confirmed',
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(2)->toDateString(), // 2 nights
        'adults' => 2,
        'children' => 0,
        'nightly_rate' => 300.00,
    ]);

    $folioService = app(ReservationFolioService::class);
    $folioService->syncRoomCharges($reservation, 'initial booking');
    $folio = $folioService->ensureOpenFolio($reservation);

    Payment::create([
        'folio_id' => $folio->id,
        'method' => 'Card',
        'amount' => 300.00,
        'txn_ref' => 'TEST-DEP',
        'paid_at' => now(),
    ]);

    $deposit = $folioService->enforceDepositStatus($reservation);
    expect($deposit['due'])->toBe(0);
    expect($reservation->refresh()->status)->toBe('Confirmed');

    $reservation->forceFill([
        'nightly_rate' => 500.00, // increased nightly total (e.g. added rooms)
    ])->save();

    $folioService->syncRoomCharges($reservation, 'added rooms');
    $folioService->normalizeOverpayment($folio, 'added rooms refund');
    $deposit = $folioService->enforceDepositStatus($reservation);

    expect($deposit['due'])->toBe(200.00);
    expect($reservation->refresh()->status)->toBe('Pending');
});

test('when edits would overpay the folio an automatic refund payment is recorded', function () {
    $hotel = Hotel::create([
        'name' => 'Test Hotel',
        'code' => 'TST',
    ]);

    $user = User::factory()->create();
    $customer = CustomerUser::create([
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ]);

    $reservation = Reservation::create([
        'hotel_id' => $hotel->id,
        'customer_id' => $customer->id,
        'status' => 'Confirmed',
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(5)->toDateString(),
        'adults' => 2,
        'children' => 0,
        'nightly_rate' => 300.00,
    ]);

    $folioService = app(ReservationFolioService::class);
    $folioService->syncRoomCharges($reservation, 'initial booking');
    $folio = $folioService->ensureOpenFolio($reservation);

    Payment::create([
        'folio_id' => $folio->id,
        'method' => 'Card',
        'amount' => 300.00,
        'txn_ref' => 'TEST-DEP',
        'paid_at' => now(),
    ]);

    $folioService->enforceDepositStatus($reservation);

    $reservation->forceFill([
        'check_out_date' => now()->addDay()->toDateString(), // 1 night
        'nightly_rate' => 200.00, // cheaper stay (total charges < deposit)
    ])->save();

    $folioService->syncRoomCharges($reservation, 'guest edit');
    $refunded = $folioService->normalizeOverpayment($folio, 'guest edit refund');

    expect($refunded)->toBe(100.00);

    $refundPayment = Payment::query()
        ->where('folio_id', $folio->id)
        ->where('method', 'Refund')
        ->first();

    expect($refundPayment)->not->toBeNull();
    expect((float) $refundPayment->amount)->toBe(-100.00);
});
