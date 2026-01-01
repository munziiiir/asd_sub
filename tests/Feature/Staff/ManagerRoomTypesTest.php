<?php

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerRoomTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_room_types(): void
    {
        $hotel = $this->createHotel();
        $roomType = $this->createRoomType($hotel, ['name' => 'Manager Suite']);
        $manager = $this->createStaff('manager', $hotel);

        $this->actingAs($manager, 'staff')
            ->get(route('staff.manager.room-types.index'))
            ->assertOk()
            ->assertSee('Manager Suite');
    }

    public function test_manager_can_create_edit_delete_room_type(): void
    {
        $hotel = $this->createHotel();
        $manager = $this->createStaff('manager', $hotel);

        $createResponse = $this->actingAs($manager, 'staff')
            ->post(route('staff.manager.room-types.store'), [
                'name' => 'Test Type',
                'max_adults' => 2,
                'max_children' => 1,
                'base_occupancy' => 2,
                'price_off_peak' => 120,
                'price_peak' => 200,
                'active_rate' => 'off_peak',
            ]);

        $createResponse->assertRedirect();

        $roomType = $hotel->roomTypes()->where('name', 'Test Type')->first();
        $this->assertNotNull($roomType);

        $this->actingAs($manager, 'staff')
            ->patch(route('staff.manager.room-types.update', $roomType), [
                'name' => 'Updated Type',
                'max_adults' => 3,
                'max_children' => 1,
                'base_occupancy' => 2,
                'price_off_peak' => 150,
                'price_peak' => 230,
                'active_rate' => 'peak',
            ])
            ->assertRedirect();

        $this->assertEquals('peak', $roomType->fresh()->active_rate);
    }
}
