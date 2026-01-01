<?php

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('admin can log in with valid credentials', function () {
    $admin = $this->createAdmin('Passw0rd!');

    $response = $this->post(route('admin.login.store'), [
        'username' => $admin->username,
        'password' => 'Passw0rd!',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($admin, 'admin');
});

test('admin login fails with bad credentials', function () {
    $admin = $this->createAdmin('Passw0rd!');

    $response = $this->from(route('admin.login'))->post(route('admin.login.store'), [
        'username' => $admin->username,
        'password' => 'WrongPass1!',
    ]);

    $response->assertRedirect(route('admin.login'));
    $this->assertGuest('admin');
});

test('admin with expired password is redirected to password reset page', function () {
    $admin = $this->createAdmin('Passw0rd!');
    $admin->forceFill(['last_password_changed_at' => now()->subDays(365)])->save();

    $this->actingAs($admin, 'admin');

    $response = $this->get(route('admin.dashboard'));

    $response->assertRedirect(route('admin.password.expired'));
    $this->assertAuthenticatedAs($admin, 'admin');
});

test('expired admin must choose a different password', function () {
    $admin = $this->createAdmin('Passw0rd!');
    $admin->forceFill(['last_password_changed_at' => now()->subDays(365)])->save();

    $this->actingAs($admin, 'admin');
    $this->get(route('admin.dashboard'));

    $this->post(route('admin.password.expired.update'), [
        'password' => 'Passw0rd!',
        'password_confirmation' => 'Passw0rd!',
    ])->assertSessionHasErrors('password');

    $this->assertTrue(Hash::check('Passw0rd!', $admin->fresh()->password));
});
