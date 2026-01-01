<?php

namespace Tests\Unit;

use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\StaffUser;
use App\Support\RoomStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomStatusServiceHelpersTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_reservation_for_room_respects_hotel_today_and_status(): void
    {
        $hotel = $this->createHotel();
        $roomType = $this->createRoomType($hotel);
        $room = $this->createRoom($hotel, $roomType);
        $customer = $this->createCustomerUser();

        // Future reservation should be considered active reservation to hold the room.
        $reservationFuture = $this->createReservationWithRoom($hotel, $customer, $room, [
            'status' => 'Confirmed',
            'check_in_date' => now()->addDays(2)->toDateString(),
            'check_out_date' => now()->addDays(4)->toDateString(),
        ]);

        $service = app(RoomStatusService::class);
        $this->assertEquals($reservationFuture->id, $service->activeReservationForRoom($room)->id);

        // Current stay should be active.
        $reservationCurrent = $this->createReservationWithRoom($hotel, $customer, $room, [
            'status' => 'Confirmed',
            'check_in_date' => now()->subDay()->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
        ]);

        $active = $service->activeReservationForRoom($room->fresh());
        $this->assertEquals($reservationCurrent->id, $active->id);

        // CheckedOut should not be treated as active.
        $reservationCurrent->update(['status' => 'CheckedOut']);
        // Future booking should now be returned as the next active.
        $this->assertEquals($reservationFuture->id, $service->activeReservationForRoom($room->fresh())->id);
    }

    public function test_hotel_today_uses_timezone(): void
    {
        $hotel = $this->createHotel();
        $roomType = $this->createRoomType($hotel);
        $room = $this->createRoom($hotel, $roomType);

        $service = app(RoomStatusService::class);
        $today = $service->hotelToday($room);

        $this->assertEquals(now()->toDateString(), $today);
    }
}
