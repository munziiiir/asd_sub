<?php

use App\Models\CustomerUser;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomStatusLog;
use App\Models\RoomType;
use App\Models\StaffUser;
use App\Models\User;
use App\Support\RoomStatusService;
use Carbon\Carbon;

function createStaffForHotel(Hotel $hotel, string $role, string $email): StaffUser
{
    return StaffUser::unguarded(function () use ($hotel, $role, $email) {
        return StaffUser::create([
            'hotel_id' => $hotel->id,
            'name' => ucfirst($role) . ' User',
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role,
            'employment_status' => 'active',
        ]);
    });
}

function createRoom(Hotel $hotel, RoomType $roomType, string $number, string $status): Room
{
    return Room::create([
        'hotel_id' => $hotel->id,
        'room_type_id' => $roomType->id,
        'number' => $number,
        'floor' => '1',
        'status' => $status,
    ]);
}

test('room can be marked for cleaning and logs revert details', function () {
    $hotel = Hotel::create(['name' => 'Test Hotel', 'code' => 'TST']);
    $roomType = RoomType::create([
        'hotel_id' => $hotel->id,
        'name' => 'Deluxe',
        'max_adults' => 2,
        'max_children' => 1,
        'base_occupancy' => 2,
        'price_off_peak' => 100,
        'price_peak' => 120,
    ]);

    $room = createRoom($hotel, $roomType, '101', 'Reserved');
    $actor = createStaffForHotel($hotel, 'manager', 'manager@test.com');
    $assignee = createStaffForHotel($hotel, 'frontdesk', 'front@test.com');

    $user = User::factory()->create();
    $customer = CustomerUser::create([
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ]);

    $reservation = Reservation::create([
        'hotel_id' => $hotel->id,
        'customer_id' => $customer->id,
        'status' => 'Confirmed',
        'check_in_date' => Carbon::now()->addDays(2)->toDateString(),
        'check_out_date' => Carbon::now()->addDays(4)->toDateString(),
        'adults' => 2,
        'children' => 0,
        'nightly_rate' => 150,
    ]);

    $reservation->reservationRooms()->create([
        'hotel_id' => $hotel->id,
        'room_id' => $room->id,
        'from_date' => $reservation->check_in_date,
        'to_date' => $reservation->check_out_date,
    ]);

    $service = app(RoomStatusService::class);
    $log = $service->markCleaning($room->fresh(), $actor, $assignee, 'Turnover clean');

    $room->refresh();
    expect($room->status)->toBe('Cleaning');
    expect($log->revert_to_status)->toBe('Reserved');
    expect($log->assigned_staff_id)->toBe($assignee->id);
    expect($log->revert_at)->not->toBeNull();
    expect($log->revert_at->greaterThan(now()))->toBeTrue();
});

test('marking out of service shifts reservation to another room when available', function () {
    $hotel = Hotel::create(['name' => 'Shift Hotel', 'code' => 'SHF']);
    $roomType = RoomType::create([
        'hotel_id' => $hotel->id,
        'name' => 'Standard',
        'max_adults' => 2,
        'max_children' => 0,
        'base_occupancy' => 2,
        'price_off_peak' => 90,
        'price_peak' => 110,
    ]);

    $roomA = createRoom($hotel, $roomType, '201', 'Reserved');
    $roomB = createRoom($hotel, $roomType, '202', 'Available');
    $staff = createStaffForHotel($hotel, 'manager', 'shift@test.com');

    $user = User::factory()->create();
    $customer = CustomerUser::create([
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ]);

    $reservation = Reservation::create([
        'hotel_id' => $hotel->id,
        'customer_id' => $customer->id,
        'status' => 'Confirmed',
        'check_in_date' => Carbon::now()->addDay()->toDateString(),
        'check_out_date' => Carbon::now()->addDays(3)->toDateString(),
        'adults' => 2,
        'children' => 0,
        'nightly_rate' => 120,
    ]);

    $reservation->reservationRooms()->create([
        'hotel_id' => $hotel->id,
        'room_id' => $roomA->id,
        'from_date' => $reservation->check_in_date,
        'to_date' => $reservation->check_out_date,
    ]);

    $service = app(RoomStatusService::class);
    $log = $service->markOutOfService($roomA->fresh(), $staff, 'AC maintenance');

    $roomA->refresh();
    $roomB->refresh();
    $reservation->refresh()->load('rooms');

    expect($roomA->status)->toBe('Out of Service');
    expect($roomB->status)->toBe('Reserved');
    expect($reservation->rooms->pluck('id'))->toContain($roomB->id);
    expect($log->meta['reservation_shifted_to_room_id'] ?? null)->toBe($roomB->id);
});

test('resetTemporaryStatuses reverts cleaning back to previous state', function () {
    $hotel = Hotel::create(['name' => 'Reset Hotel', 'code' => 'RST']);
    $roomType = RoomType::create([
        'hotel_id' => $hotel->id,
        'name' => 'Queen',
        'max_adults' => 2,
        'max_children' => 0,
        'base_occupancy' => 2,
        'price_off_peak' => 80,
        'price_peak' => 100,
    ]);

    $room = createRoom($hotel, $roomType, '301', 'Reserved');
    $staff = createStaffForHotel($hotel, 'manager', 'reset@test.com');
    $assignee = createStaffForHotel($hotel, 'frontdesk', 'reset-assignee@test.com');

    $service = app(RoomStatusService::class);
    $log = $service->markCleaning($room->fresh(), $staff, $assignee, 'Prep');

    $log->update(['revert_at' => now()->subMinute()]);
    $room->update(['status' => 'Cleaning']);

    $resetCount = $service->resetTemporaryStatuses(now());

    $room->refresh();
    expect($resetCount)->toBe(1);
    expect($room->status)->toBe('Reserved');
    expect(RoomStatusLog::where('context', 'auto-revert')->where('room_id', $room->id)->exists())->toBeTrue();
});
