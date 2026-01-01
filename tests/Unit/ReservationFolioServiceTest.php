<?php

namespace Tests\Unit;

use App\Support\ReservationFolioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationFolioServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_open_folio_creates_and_reuses_existing(): void
    {
        $hotel = $this->createHotel();
        $roomType = $this->createRoomType($hotel);
        $room = $this->createRoom($hotel, $roomType);
        $customer = $this->createCustomerUser();
        $reservation = $this->createReservationWithRoom($hotel, $customer, $room, [
            'status' => 'Confirmed',
        ]);

        $service = app(ReservationFolioService::class);

        $folio = $service->ensureOpenFolio($reservation);
        $this->assertEquals('Open', $folio->status);

        $folioAgain = $service->ensureOpenFolio($reservation);
        $this->assertEquals($folio->id, $folioAgain->id, 'folio should be reused when open exists');
    }

    public function test_sync_room_charges_adds_expected_room_charge(): void
    {
        $hotel = $this->createHotel();
        $roomType = $this->createRoomType($hotel);
        $room = $this->createRoom($hotel, $roomType);
        $customer = $this->createCustomerUser();
        $reservation = $this->createReservationWithRoom($hotel, $customer, $room, [
            'status' => 'Confirmed',
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(), // 3 nights
            'nightly_rate' => 200,
        ]);

        $service = app(ReservationFolioService::class);

        $delta = $service->syncRoomCharges($reservation, 'test');
        $folio = $service->ensureOpenFolio($reservation);

        $this->assertEquals(600.0, $delta); // 200 * 3 nights
        $this->assertDatabaseHas('charges', [
            'folio_id' => $folio->id,
            'description' => 'Online booking: Room charges (3 nights)',
            'total_amount' => 600.0,
        ]);
    }

    public function test_required_deposit_matches_nightly_rate_total(): void
    {
        $hotel = $this->createHotel();
        $roomType = $this->createRoomType($hotel);
        $room = $this->createRoom($hotel, $roomType);
        $customer = $this->createCustomerUser();
        $reservation = $this->createReservationWithRoom($hotel, $customer, $room, [
            'nightly_rate' => 185.50,
        ]);

        $service = app(ReservationFolioService::class);

        $this->assertEquals(185.50, $service->requiredDeposit($reservation));
        $this->assertEquals(185.50, $reservation->nightlyRateTotal());
    }

    public function test_normalize_overpayment_creates_refund_payment(): void
    {
        $hotel = $this->createHotel();
        $roomType = $this->createRoomType($hotel);
        $room = $this->createRoom($hotel, $roomType);
        $customer = $this->createCustomerUser();
        $reservation = $this->createReservationWithRoom($hotel, $customer, $room, [
            'nightly_rate' => 100,
        ]);

        $service = app(ReservationFolioService::class);
        $folio = $service->ensureOpenFolio($reservation);

        // $100 charge, $150 paid -> refund $50
        \App\Models\Charge::create([
            'folio_id' => $folio->id,
            'post_date' => now()->toDateString(),
            'description' => 'Room',
            'qty' => 1,
            'unit_price' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
        ]);

        \App\Models\Payment::create([
            'folio_id' => $folio->id,
            'method' => 'Card',
            'amount' => 150,
            'txn_ref' => 'OVERPAY',
            'paid_at' => now(),
        ]);

        $refunded = $service->normalizeOverpayment($folio, 'test');
        $this->assertEquals(50.0, $refunded);
        $this->assertDatabaseHas('payments', [
            'folio_id' => $folio->id,
            'amount' => -50.0,
            'method' => 'Refund',
        ]);
    }

    public function test_enforce_deposit_status_toggles_pending_and_confirmed(): void
    {
        $hotel = $this->createHotel();
        $roomType = $this->createRoomType($hotel);
        $room = $this->createRoom($hotel, $roomType);
        $customer = $this->createCustomerUser();
        $reservation = $this->createReservationWithRoom($hotel, $customer, $room, [
            'status' => 'Confirmed',
            'nightly_rate' => 200,
        ]);

        $service = app(ReservationFolioService::class);
        $folio = $service->ensureOpenFolio($reservation);

        // No payments, should downgrade to Pending.
        $result = $service->enforceDepositStatus($reservation->fresh());
        $this->assertEquals('Pending', $reservation->fresh()->status);
        $this->assertEquals('Pending', $result['new_status']);

        // Pay required deposit, should promote to Confirmed.
        \App\Models\Payment::create([
            'folio_id' => $folio->id,
            'method' => 'Card',
            'amount' => 200,
            'txn_ref' => 'DEP',
            'paid_at' => now(),
        ]);

        $result2 = $service->enforceDepositStatus($reservation->fresh());
        $this->assertEquals('Confirmed', $reservation->fresh()->status);
        $this->assertEquals('Confirmed', $result2['new_status']);
    }
}
