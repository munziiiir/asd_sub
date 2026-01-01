<?php

namespace Tests\Feature\Admin;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminHotelDeleteGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_delete_hotel_with_related_records(): void
    {
        $admin = $this->createAdmin();
        $hotel = Hotel::create([
            'name' => 'Delete Guard Hotel',
            'code' => 'DEL1',
        ]);
        $roomType = RoomType::create([
            'hotel_id' => $hotel->id,
            'name' => 'Guard Type',
            'max_adults' => 2,
            'max_children' => 1,
            'base_occupancy' => 2,
            'price_off_peak' => 100,
            'price_peak' => 150,
            'active_rate' => 'off_peak',
        ]);
        Room::create([
            'hotel_id' => $hotel->id,
            'room_type_id' => $roomType->id,
            'number' => '101',
            'floor' => '1',
            'status' => 'Available',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->delete(route('admin.hotels.destroy', $hotel));

        $response->assertSessionHasErrors('hotel');
        $this->assertDatabaseHas('hotels', ['id' => $hotel->id]);
    }
}
