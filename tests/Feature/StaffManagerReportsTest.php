<?php

use App\Models\Hotel;
use App\Models\StaffUser;

beforeEach(function () {
    $this->hotel = Hotel::create([
        'name' => 'Test Hotel',
        'code' => 'TST',
    ]);

    $this->makeStaffUser = function (string $role, string $email): StaffUser {
        $user = new StaffUser();
        $user->forceFill([
            'hotel_id' => $this->hotel->id,
            'name' => ucfirst($role),
            'email' => $email,
            'password' => bcrypt('Passw0rd!'),
            'role' => $role,
            'employment_status' => 'active',
            'last_password_changed_at' => now(),
        ]);
        $user->save();

        return $user->fresh();
    };
});

test('non-manager staff cannot access manager reports', function () {
    $staff = ($this->makeStaffUser)('frontdesk', 'frontdesk@example.com');

    $this->actingAs($staff, 'staff');
    $this->get(route('staff.manager.reports.index'))->assertForbidden();
});

test('manager can view reports page', function () {
    $manager = ($this->makeStaffUser)('manager', 'manager@example.com');

    $this->actingAs($manager, 'staff');
    $this->get(route('staff.manager.reports.index'))->assertOk();
});

test('manager can download reports as csv', function () {
    $manager = ($this->makeStaffUser)('manager', 'manager2@example.com');

    $this->actingAs($manager, 'staff');
    $response = $this->get(route('staff.manager.reports.export.csv', [
        'range' => 'custom',
        'start' => now()->subDays(7)->toDateString(),
        'end' => now()->toDateString(),
        'section' => 'all',
    ]));

    $response->assertOk();
    $response->assertHeader('content-type');
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

test('manager can download reports as pdf', function () {
    $manager = ($this->makeStaffUser)('manager', 'manager3@example.com');

    $this->actingAs($manager, 'staff');
    $response = $this->get(route('staff.manager.reports.export.pdf', [
        'range' => 'month',
        'section' => 'summary',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});
