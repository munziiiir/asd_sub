<?php

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerStaffAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_and_filter_staff(): void
    {
        $hotel = $this->createHotel();
        $manager = $this->createStaff('manager', $hotel);
        $active = $this->createStaff('frontdesk', $hotel);
        $inactive = $this->createStaff('frontdesk', $hotel);
        $inactive->update(['employment_status' => 'inactive']);

        $this->actingAs($manager, 'staff')
            ->get(route('staff.manager.frontdesk-staff.index', ['employment_status' => 'inactive']))
            ->assertOk()
            ->assertSee('inactive');
    }

    public function test_manager_can_edit_non_manager_staff(): void
    {
        $hotel = $this->createHotel();
        $manager = $this->createStaff('manager', $hotel);
        $staff = $this->createStaff('frontdesk', $hotel);

        $this->actingAs($manager, 'staff')
            ->patch(route('staff.manager.frontdesk-staff.update', $staff), [
                'name' => 'Updated Name',
                'email' => $staff->email,
                'employment_status' => 'active',
            ])
            ->assertRedirect();

        $this->assertEquals('Updated Name', $staff->fresh()->name);
    }
}
