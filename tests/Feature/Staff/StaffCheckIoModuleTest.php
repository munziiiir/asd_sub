<?php

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffCheckIoModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_arrivals_and_departures_dashboard(): void
    {
        $staff = $this->createStaff('frontdesk');

        $this->actingAs($staff, 'staff')
            ->get(route('staff.check-io.index'))
            ->assertOk()
            ->assertSee('arrivals');
    }
}
