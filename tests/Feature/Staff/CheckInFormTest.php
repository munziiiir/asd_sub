<?php

namespace Tests\Feature\Staff;

use App\Http\Controllers\Staff\CheckInOutController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckInFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_in_form_is_accessible_for_staff(): void
    {
        $staff = $this->createStaff('frontdesk');

        $this->actingAs($staff, 'staff')
            ->get(route('staff.check-io.check-in'))
            ->assertOk()
            ->assertSee('Check-in');
    }

    public function test_check_out_form_is_accessible_for_staff(): void
    {
        $staff = $this->createStaff('frontdesk');

        $this->actingAs($staff, 'staff')
            ->get(route('staff.check-io.check-out'))
            ->assertOk()
            ->assertSee('Check a guest out');
    }
}
