<?php

namespace Tests\Feature\Guest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function makeBookingForUser()
    {
        $hotel = $this->createHotel();
        $roomType = $this->createRoomType($hotel);
        $room = $this->createRoom($hotel, $roomType);
        $customer = $this->createCustomerUser();

        return $this->createReservationWithRoom($hotel, $customer, $room, [
            'status' => 'Pending',
        ]);
    }

    public function test_guest_bookings_index_shows_only_their_reservations(): void
    {
        $reservation = $this->makeBookingForUser();
        $otherReservation = $this->makeBookingForUser();

        // Tie other reservation to a different user
        $otherUser = $this->createGuestUser();
        $otherReservation->customer->update(['user_id' => $otherUser->id]);

        $this->actingAs($reservation->customer->user)
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertSee($reservation->code)
            ->assertDontSee($otherReservation->code);
    }

    public function test_guest_cannot_view_another_users_reservation(): void
    {
        $reservation = $this->makeBookingForUser();
        $otherReservation = $this->makeBookingForUser();

        $this->actingAs($reservation->customer->user)
            ->get(route('bookings.show', $otherReservation))
            ->assertForbidden();
    }

    public function test_pay_route_redirects_when_booking_not_pending(): void
    {
        $reservation = $this->makeBookingForUser();
        $reservation->update(['status' => 'Confirmed']);

        // Fully paid so status stays Confirmed after enforceDepositStatus.
        $folio = app(\App\Support\ReservationFolioService::class)->ensureOpenFolio($reservation);
        \App\Models\Payment::create([
            'folio_id' => $folio->id,
            'method' => 'Card',
            'amount' => $reservation->nightly_rate,
            'txn_ref' => 'TESTPAY',
            'paid_at' => now(),
        ]);

        $this->actingAs($reservation->customer->user)
            ->get(route('bookings.pay', $reservation))
            ->assertRedirect(route('bookings.show', $reservation))
            ->assertSessionHas('status', 'Booking already finalized.');
    }
}
