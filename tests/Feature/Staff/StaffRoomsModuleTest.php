<?php

namespace Tests\Feature\Staff;

use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffRoomsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function makeRoomForStaff(string $status = 'Available'): array
    {
        $hotel = $this->createHotel();
        $roomType = $this->createRoomType($hotel);
        $room = $this->createRoom($hotel, $roomType, ['status' => $status]);
        $staff = $this->createStaff('frontdesk', $hotel);

        return [$staff, $room];
    }

    public function test_staff_can_view_rooms(): void
    {
        [$staff, $room] = $this->makeRoomForStaff();

        $this->actingAs($staff, 'staff')
            ->get(route('staff.rooms.index'))
            ->assertOk()
            ->assertSee($room->number);
    }

    public function test_staff_can_mark_room_cleaning_with_assignee(): void
    {
        [$staff, $room] = $this->makeRoomForStaff('Available');
        $assignee = $this->createStaff('frontdesk', $staff->hotel);

        $this->actingAs($staff, 'staff')
            ->patch(route('staff.rooms.update', $room), [
                'status_action' => 'cleaning',
                'assigned_staff_id' => $assignee->id,
                'note' => 'Test cleaning',
            ])
            ->assertRedirect();

        $this->assertEquals('Cleaning', Room::find($room->id)->status);
    }

    public function test_staff_cannot_mark_occupied_room_cleaning(): void
    {
        [$staff, $room] = $this->makeRoomForStaff('Occupied');
        $assignee = $this->createStaff('frontdesk', $staff->hotel);

        $this->actingAs($staff, 'staff')
            ->patch(route('staff.rooms.update', $room), [
                'status_action' => 'cleaning',
                'assigned_staff_id' => $assignee->id,
            ])
            ->assertSessionHasErrors();
    }

    public function test_staff_can_mark_room_out_of_service_when_available(): void
    {
        [$staff, $room] = $this->makeRoomForStaff('Available');

        $this->actingAs($staff, 'staff')
            ->patch(route('staff.rooms.update', $room), [
                'status_action' => 'out_of_service',
                'note' => 'Broken AC',
            ])
            ->assertRedirect();

        $this->assertEquals('Out of Service', Room::find($room->id)->status);
    }

    public function test_staff_cannot_mark_out_of_service_when_occupied(): void
    {
        [$staff, $room] = $this->makeRoomForStaff('Occupied');

        $this->actingAs($staff, 'staff')
            ->patch(route('staff.rooms.update', $room), [
                'status_action' => 'out_of_service',
            ])
            ->assertSessionHasErrors();
    }
}
