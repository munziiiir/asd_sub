<?php

namespace Tests\Unit;

use App\Http\Middleware\ForcePasswordRotation;
use App\Models\AdminUser;
use App\Models\StaffUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ForcePasswordRotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_allows_when_password_recent_for_admin(): void
    {
        $admin = AdminUser::create([
            'username' => 'admin_recent',
            'name' => 'Admin Recent',
            'password' => bcrypt('Password1!'),
            'is_active' => true,
        ]);
        $admin->forceFill(['last_password_changed_at' => now()])->save();

        Route::middleware(ForcePasswordRotation::class)->get('/rotation-admin', fn () => 'ok');

        $response = $this->actingAs($admin, 'admin')->get('/rotation-admin');
        $response->assertOk();
    }

    public function test_blocks_expired_admin_password(): void
    {
        $admin = AdminUser::create([
            'username' => 'admin_old',
            'name' => 'Admin Old',
            'password' => bcrypt('Password1!'),
            'is_active' => true,
        ]);
        $admin->forceFill(['last_password_changed_at' => now()->subDays(365)])->save();

        Route::middleware(ForcePasswordRotation::class)->get('/rotation-admin-expired', fn () => 'ok');

        $response = $this->actingAs($admin, 'admin')->get('/rotation-admin-expired');
        $response->assertRedirect(route('admin.password.expired'));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_blocks_expired_manager_password_only(): void
    {
        $hotel = $this->createHotel();
        $manager = $this->createStaff('manager', $hotel);
        $manager->forceFill(['last_password_changed_at' => now()->subDays(365)])->save();

        Route::middleware(ForcePasswordRotation::class)->get('/rotation-staff', fn () => 'ok');

        Auth::guard('admin')->logout();
        $response = $this->actingAs($manager, 'staff')->get('/rotation-staff');
        $response->assertRedirect(route('staff.password.expired'));
        $this->assertAuthenticatedAs($manager, 'staff');

        $frontdesk = $this->createStaff('frontdesk', $hotel);
        $frontdesk->forceFill(['last_password_changed_at' => now()->subDays(365)])->save();

        Auth::guard('admin')->logout();
        $responseFd = $this->actingAs($frontdesk, 'staff')->get('/rotation-staff');
        $responseFd->assertOk();
    }
}
