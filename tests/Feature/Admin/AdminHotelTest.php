<?php

namespace Tests\Feature\Admin;

use App\Models\Country;
use App\Models\Timezone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminHotelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function seedCountryAndTimezone(): Timezone
    {
        Country::create(['code' => 'US', 'name' => 'United States']);

        return Timezone::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'timezone' => 'America/New_York',
        ]);
    }

    public function test_admin_can_create_and_update_hotel_with_timezone_validation(): void
    {
        $admin = $this->createAdmin();
        $tz = $this->seedCountryAndTimezone();

        // Create
        $create = $this->actingAs($admin, 'admin')->post(route('admin.hotels.store'), [
            'name' => 'New Hotel',
            'code' => 'NH01',
            'country_code' => 'US',
            'timezone_id' => $tz->id,
        ]);

        $create->assertRedirect();
        $this->assertDatabaseHas('hotels', [
            'name' => 'New Hotel',
            'code' => 'NH01',
            'timezone_id' => $tz->id,
        ]);

        $hotelId = $this->app['db']->table('hotels')->where('code', 'NH01')->value('id');

        // Update
        $this->actingAs($admin, 'admin')
            ->patch(route('admin.hotels.update', $hotelId), [
                'name' => 'Renamed Hotel',
                'code' => 'NH01',
                'country_code' => 'US',
                'timezone_id' => $tz->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('hotels', [
            'id' => $hotelId,
            'name' => 'Renamed Hotel',
        ]);
    }

    public function test_admin_cannot_use_timezone_outside_country(): void
    {
        $admin = $this->createAdmin();
        $this->seedCountryAndTimezone();
        // Mismatch timezone (country_code doesn't match)
        $otherTz = Timezone::create([
            'country_code' => 'GB',
            'country_name' => 'United Kingdom',
            'timezone' => 'Europe/London',
        ]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.hotels.store'), [
            'name' => 'Mismatch Hotel',
            'code' => 'MIS1',
            'country_code' => 'US',
            'timezone_id' => $otherTz->id,
        ]);

        $response->assertSessionHasErrors('timezone_id');
    }
}
