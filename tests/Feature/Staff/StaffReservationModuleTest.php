<?php

namespace Tests\Feature\Staff;

use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffReservationModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function makeReservationForStaff(): array
    {
        $hotel = $this->createHotel();
        $roomType = $this->createRoomType($hotel);
        $room = $this->createRoom($hotel, $roomType);
        $customer = $this->createCustomerUser();
        $reservation = $this->createReservationWithRoom($hotel, $customer, $room, [
            'status' => 'Confirmed',
            'check_in_date' => now()->addDays(3)->toDateString(),
            'check_out_date' => now()->addDays(5)->toDateString(),
        ]);
        $staff = $this->createStaff('frontdesk', $hotel);

        return [$staff, $reservation, $room];
    }

    public function test_staff_can_view_reservations_index_and_show(): void
    {
        [$staff, $reservation] = $this->makeReservationForStaff();

        $this->actingAs($staff, 'staff')
            ->get(route('staff.reservations.index'))
            ->assertOk()
            ->assertSee($reservation->code);

        $this->actingAs($staff, 'staff')
            ->get(route('staff.reservations.show', $reservation))
            ->assertOk()
            ->assertSee($reservation->code);
    }

    public function test_staff_can_update_future_reservation_fields(): void
    {
        [$staff, $reservation] = $this->makeReservationForStaff();

        $payload = [
            'check_in_date' => now()->addDays(3)->toDateString(),
            'check_out_date' => now()->addDays(5)->toDateString(),
            'adults' => 3,
            'children' => 1,
        ];

        $this->actingAs($staff, 'staff')
            ->patch(route('staff.reservations.update', $reservation), $payload)
            ->assertRedirect();

        $reservation->refresh();
        $this->assertEquals(3, $reservation->adults);
        $this->assertEquals(1, $reservation->children);
    }

    public function test_checked_in_reservation_blocks_immutable_fields(): void
    {
        [$staff, $reservation] = $this->makeReservationForStaff();
        $reservation->update([
            'status' => 'CheckedIn',
            'check_in_date' => now()->subDay()->toDateString(),
        ]);

        $originalAdults = $reservation->adults;

        $this->actingAs($staff, 'staff')
            ->patch(route('staff.reservations.update', $reservation), [
                'check_out_date' => now()->addDays(2)->toDateString(),
                'check_in_date' => now()->addDays(1)->toDateString(),
                'adults' => $originalAdults + 2,
            ])
            ->assertRedirect();

        $reservation->refresh();
        $this->assertEquals($originalAdults, $reservation->adults, 'adults should remain immutable when checked in');
        $this->assertNotEquals(now()->addDays(1)->toDateString(), $reservation->check_in_date->toDateString());
    }

    public function test_other_hotel_reservation_is_forbidden(): void
    {
        [$staff, $reservation] = $this->makeReservationForStaff();
        $otherStaff = $this->createStaff('frontdesk'); // different hotel

        $this->actingAs($otherStaff, 'staff')
            ->get(route('staff.reservations.show', $reservation))
            ->assertForbidden();
    }
}
