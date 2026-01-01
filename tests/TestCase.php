<?php

namespace Tests;

use App\Models\AdminUser;
use App\Models\CustomerUser;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\StaffUser;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use WithFaker;

    /**
     * Boots the application for the test runner (Laravel 11+).
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function createAdmin(string $password = 'Password1!'): AdminUser
    {
        $admin = AdminUser::create([
            'username' => 'admin_' . Str::random(6),
            'name' => 'Admin User',
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        $admin->forceFill(['last_password_changed_at' => now()])->save();

        return $admin->fresh();
    }

    protected function createHotel(): Hotel
    {
        $timezone = \App\Models\Timezone::firstOrCreate(
            ['timezone' => 'UTC'],
            [
                'country_code' => 'US',
                'country_name' => 'United States',
                'timezone' => 'UTC',
            ]
        );

        return Hotel::create([
            'name' => 'Test Hotel ' . Str::random(4),
            'code' => strtoupper(Str::random(5)),
            'timezone_id' => $timezone->id,
        ]);
    }

    protected function createStaff(string $role = 'manager', ?Hotel $hotel = null, string $password = 'Password1!'): StaffUser
    {
        $hotel = $hotel ?: $this->createHotel();

        $staff = new StaffUser();
        $staff->forceFill([
            'hotel_id' => $hotel->id,
            'name' => 'Staff ' . Str::random(4),
            'email' => Str::random(6) . '@example.com',
            'password' => Hash::make($password),
            'role' => $role,
            'employment_status' => 'active',
            'last_password_changed_at' => now(),
        ])->save();

        return $staff->fresh();
    }

    protected function createGuestUser(?string $password = null): User
    {
        $password = $password ?: 'Password1!';

        return User::factory()->create([
            'password' => Hash::make($password),
        ]);
    }

    protected function createCustomerUser(?User $user = null): CustomerUser
    {
        $user ??= $this->createGuestUser();

        return CustomerUser::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    protected function createRoomType(Hotel $hotel, array $overrides = []): RoomType
    {
        return RoomType::create(array_merge([
            'hotel_id' => $hotel->id,
            'name' => 'Deluxe King',
            'max_adults' => 2,
            'max_children' => 1,
            'base_occupancy' => 2,
            'price_off_peak' => 150,
            'price_peak' => 220,
            'active_rate' => 'off_peak',
        ], $overrides));
    }

    protected function createRoom(Hotel $hotel, RoomType $roomType, array $overrides = []): Room
    {
        return Room::create(array_merge([
            'hotel_id' => $hotel->id,
            'room_type_id' => $roomType->id,
            'number' => (string) random_int(100, 999),
            'floor' => '1',
            'status' => 'Available',
        ], $overrides));
    }

    protected function createReservationWithRoom(Hotel $hotel, CustomerUser $customer, Room $room, array $overrides = []): Reservation
    {
        $reservation = Reservation::create(array_merge([
            'hotel_id' => $hotel->id,
            'customer_id' => $customer->id,
            'status' => 'Pending',
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'nightly_rate' => 180.00,
        ], $overrides));

        $reservation->rooms()->attach($room->id, [
            'hotel_id' => $hotel->id,
            'from_date' => $reservation->check_in_date,
            'to_date' => $reservation->check_out_date,
        ]);

        return $reservation->fresh();
    }
}
