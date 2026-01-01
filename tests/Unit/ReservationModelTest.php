<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_code_and_incremental_number_are_generated_per_hotel(): void
    {
        $hotel = $this->createHotel();
        $roomType = $this->createRoomType($hotel);
        $room = $this->createRoom($hotel, $roomType);
        $customer = $this->createCustomerUser();

        $first = $this->createReservationWithRoom($hotel, $customer, $room);
        $second = $this->createReservationWithRoom($hotel, $customer, $room);

        $this->assertStringStartsWith($hotel->code . '-', $first->code);
        $this->assertEquals($first->incremental_no + 1, $second->incremental_no);
    }

    public function test_nightly_rate_total_falls_back_to_room_type_rate_when_not_set(): void
    {
        $hotel = $this->createHotel();
        $roomType = $this->createRoomType($hotel, ['price_off_peak' => 175, 'active_rate' => 'off_peak']);
        $room = $this->createRoom($hotel, $roomType);
        $customer = $this->createCustomerUser();

        $reservation = $this->createReservationWithRoom($hotel, $customer, $room, [
            'nightly_rate' => null,
        ]);

        $this->assertEquals(175.0, $reservation->nightlyRateTotal());
    }
}
