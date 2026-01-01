<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_filter_admin_users(): void
    {
        $admin = $this->createAdmin();

        $payload = [
            'name' => 'Second Admin',
            'username' => 'admin_' . Str::random(4),
            'password' => $password = Str::random(12) . '#A1',
            'password_confirmation' => $password,
            'is_active' => true,
        ];

        $this->actingAs($admin, 'admin')
            ->post(route('admin.users.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('admin_users', [
            'username' => $payload['username'],
            'is_active' => 1,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.users.index', ['status' => 'active', 'search' => 'Second']))
            ->assertOk()
            ->assertSee('Second Admin');
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->patch(route('admin.users.update', $admin), [
                'name' => $admin->name,
                'username' => $admin->username,
                'password' => '',
                'password_confirmation' => '',
                'is_active' => false,
            ]);

        $response->assertSessionHasErrors('is_active');
        $this->assertTrue($admin->fresh()->is_active);
    }
}
