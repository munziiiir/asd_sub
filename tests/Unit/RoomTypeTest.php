<?php

namespace Tests\Unit;

use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_rate_switches_between_peak_and_off_peak(): void
    {
        $hotel = $this->createHotel();
        $roomType = RoomType::create([
            'hotel_id' => $hotel->id,
            'name' => 'Test Type',
            'max_adults' => 2,
            'max_children' => 1,
            'base_occupancy' => 2,
            'price_off_peak' => 100,
            'price_peak' => 150,
            'active_rate' => 'off_peak',
        ]);

        $this->assertEquals(100.0, $roomType->activeRate());

        $roomType->update(['active_rate' => 'peak']);
        $this->assertEquals(150.0, $roomType->fresh()->activeRate());
    }
}
