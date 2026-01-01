<?php

namespace Tests\Feature\Admin;

use App\Models\Country;
use App\Models\Hotel;
use App\Models\StaffUser;
use App\Models\Timezone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminStaffUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function seedHotel(): Hotel
    {
        Country::create(['code' => 'US', 'name' => 'United States']);
        $tz = Timezone::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'timezone' => 'America/New_York',
        ]);

        return Hotel::create([
            'name' => 'Admin Hotel',
            'code' => 'ADM1',
            'country_code' => 'US',
            'timezone_id' => $tz->id,
        ]);
    }

    public function test_admin_can_create_and_update_staff_user(): void
    {
        $admin = $this->createAdmin();
        $hotel = $this->seedHotel();
        $password = Str::random(12) . '#A1';

        $payload = [
            'hotel_id' => $hotel->id,
            'name' => 'Front Desk One',
            'email' => 'frontdesk@example.com',
            'password' => $password,
            'password_confirmation' => $password,
            'role' => 'frontdesk',
            'employment_status' => 'active',
        ];

        $this->actingAs($admin, 'admin')
            ->post(route('admin.staffusers.store'), $payload)
            ->assertRedirect();

        $staff = StaffUser::where('email', 'frontdesk@example.com')->first();
        $this->assertNotNull($staff);
        $this->assertEquals($hotel->id, $staff->hotel_id);

        // Update without changing password
        $this->actingAs($admin, 'admin')
            ->patch(route('admin.staffusers.update', $staff), [
                'hotel_id' => $hotel->id,
                'name' => 'Front Desk Updated',
                'email' => $staff->email,
                'password' => '',
                'password_confirmation' => '',
                'role' => 'frontdesk',
                'employment_status' => 'inactive',
            ])
            ->assertRedirect();

        $this->assertEquals('inactive', $staff->fresh()->employment_status);
    }

    public function test_admin_cannot_duplicate_email_within_same_hotel(): void
    {
        $admin = $this->createAdmin();
        $hotel = $this->seedHotel();

        (new StaffUser())->forceFill([
            'hotel_id' => $hotel->id,
            'name' => 'Existing Staff',
            'email' => 'duplicate@example.com',
            'password' => bcrypt('Password1!'),
            'role' => 'frontdesk',
            'employment_status' => 'active',
        ])->save();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.staffusers.store'), [
                'hotel_id' => $hotel->id,
                'name' => 'Another Staff',
                'email' => 'duplicate@example.com',
                'password' => $dupPassword = Str::random(12) . '#A1',
                'password_confirmation' => $dupPassword,
                'role' => 'manager',
                'employment_status' => 'active',
            ]);

        $response->assertSessionHasErrors('email');
    }
}
