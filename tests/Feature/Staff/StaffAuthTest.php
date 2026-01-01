<?php

use App\Models\Hotel;

test('staff can log in with valid credentials', function () {
    $staff = $this->createStaff(role: 'manager', password: 'Passw0rd!');

    $response = $this->post('/staff/login', [
        'email' => $staff->email,
        'password' => 'Passw0rd!',
    ]);

    $response->assertRedirect(route('staff.dashboard'));
    $this->assertAuthenticatedAs($staff, 'staff');
});

test('staff login fails with bad credentials', function () {
    $staff = $this->createStaff(role: 'manager', password: 'Passw0rd!');

    $response = $this->from(route('staff.login'))->post('/staff/login', [
        'email' => $staff->email,
        'password' => 'WrongPass1!',
    ]);

    $response->assertRedirect(route('staff.login'));
    $this->assertGuest('staff');
});

test('manager with expired password is redirected to password reset page', function () {
    $staff = $this->createStaff(role: 'manager', password: 'Passw0rd!');
    $staff->forceFill(['last_password_changed_at' => now()->subDays(365)])->save();

    $this->actingAs($staff, 'staff');

    $response = $this->get(route('staff.dashboard'));

    $response->assertRedirect(route('staff.password.expired'));
    $this->assertAuthenticatedAs($staff, 'staff');
});

test('expired manager must choose a different password', function () {
    $staff = $this->createStaff(role: 'manager', password: 'Passw0rd!');
    $staff->forceFill(['last_password_changed_at' => now()->subDays(365)])->save();

    $this->actingAs($staff, 'staff');
    $this->get(route('staff.dashboard'));

    $this->post(route('staff.password.expired.update'), [
        'password' => 'Passw0rd!',
        'password_confirmation' => 'Passw0rd!',
    ])->assertSessionHasErrors('password');

    $this->assertTrue(Hash::check('Passw0rd!', $staff->fresh()->password));
});
