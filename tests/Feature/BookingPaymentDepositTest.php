<?php

use App\Livewire\BookingPayment;
use App\Models\Charge;
use App\Models\CustomerUser;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Support\ReservationFolioService;
use Livewire\Livewire;

test('payment page charges only the remaining deposit due', function () {
    $hotel = Hotel::create([
        'name' => 'Test Hotel',
        'code' => 'TST',
    ]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $customer = CustomerUser::create([
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'card_brand' => 'Visa',
        'card_last_four' => '4242',
        'card_exp_month' => 12,
        'card_exp_year' => 2030,
    ]);

    $reservation = Reservation::create([
        'hotel_id' => $hotel->id,
        'customer_id' => $customer->id,
        'status' => 'Pending',
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(5)->toDateString(), // 5 nights
        'adults' => 3,
        'children' => 0,
        'nightly_rate' => 300.00,
    ]);

    $folioService = app(ReservationFolioService::class);
    $folioService->syncRoomCharges($reservation, 'initial booking');
    $folio = $folioService->ensureOpenFolio($reservation);

    Payment::create([
        'folio_id' => $folio->id,
        'method' => 'Card',
        'amount' => 200.00,
        'txn_ref' => 'TEST-PARTIAL',
        'paid_at' => now(),
    ]);

    Livewire::test(BookingPayment::class, ['reservation' => $reservation])
        ->set('cardOption', 'saved')
        ->call('pay')
        ->assertSet('success', true);

    expect((float) Payment::query()->where('folio_id', $folio->id)->sum('amount'))->toBe(300.00);

    $roomChargeTotal = (float) Charge::query()
        ->where('folio_id', $folio->id)
        ->where('description', 'like', 'Online booking: Room charges%')
        ->sum('total_amount');

    expect($roomChargeTotal)->toBe(1500.00);
    expect($reservation->fresh()->status)->toBe('Confirmed');
});

