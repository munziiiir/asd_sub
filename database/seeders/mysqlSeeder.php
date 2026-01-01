<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class mysqlSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $hotels = [
            ['name' => 'Regal Waterfront Hotel', 'code' => 'RWH', 'timezone' => 'Europe/London', 'country_code' => 'GB'],
            ['name' => 'Highland Escape Lodge', 'code' => 'HEL', 'timezone' => 'Europe/London', 'country_code' => 'GB'],
            ['name' => 'Urban City Suites', 'code' => 'UCS', 'timezone' => 'Europe/London', 'country_code' => 'GB'],
            ['name' => 'Pacific Harbor Resort', 'code' => 'PHR', 'timezone' => 'America/Los_Angeles', 'country_code' => 'US'],
            ['name' => 'Midtown Skyline Hotel', 'code' => 'MSH', 'timezone' => 'America/New_York', 'country_code' => 'US'],
            ['name' => 'Riviera Sun Resort', 'code' => 'RSR', 'timezone' => 'Europe/Madrid', 'country_code' => 'ES'],
            ['name' => 'Alpine Peaks Lodge', 'code' => 'APL', 'timezone' => 'Europe/Zurich', 'country_code' => 'CH'],
            ['name' => 'Sakura Bay Hotel', 'code' => 'SBH', 'timezone' => 'Asia/Tokyo', 'country_code' => 'JP'],
            ['name' => 'Desert Star Hotel', 'code' => 'DSH', 'timezone' => 'Asia/Dubai', 'country_code' => 'AE'],
            ['name' => 'Maple Leaf Inn', 'code' => 'MLI', 'timezone' => 'America/Toronto', 'country_code' => 'CA'],
            ['name' => 'Harbor Lights Hotel', 'code' => 'HLH', 'timezone' => 'Europe/Dublin', 'country_code' => 'IE'],
            ['name' => 'Baltic Breeze Hotel', 'code' => 'BBH', 'timezone' => 'Europe/Tallinn', 'country_code' => 'EE'],
            ['name' => 'Cape Horizon Hotel', 'code' => 'CHH', 'timezone' => 'Africa/Johannesburg', 'country_code' => 'ZA'],
            ['name' => 'Andes Vista Lodge', 'code' => 'AVL', 'timezone' => 'America/Santiago', 'country_code' => 'CL'],
            ['name' => 'Coral Coast Retreat', 'code' => 'CCR', 'timezone' => 'Australia/Brisbane', 'country_code' => 'AU'],
            ['name' => 'Marina View Suites', 'code' => 'MVS', 'timezone' => 'Asia/Singapore', 'country_code' => 'SG'],
            ['name' => 'Nordic Aurora Hotel', 'code' => 'NAH', 'timezone' => 'Europe/Oslo', 'country_code' => 'NO'],
            ['name' => 'Amber Dunes Resort', 'code' => 'ADR', 'timezone' => 'Africa/Casablanca', 'country_code' => 'MA'],
            ['name' => 'Garden City Hotel', 'code' => 'GCH', 'timezone' => 'Asia/Kolkata', 'country_code' => 'IN'],
            ['name' => 'Island Breeze Hotel', 'code' => 'IBH', 'timezone' => 'Asia/Jakarta', 'country_code' => 'ID'],
        ];

        $roomTypes = [
            ['name' => 'Standard Double', 'capacity' => 2, 'off_peak' => 120, 'peak' => 180],
            ['name' => 'Deluxe King', 'capacity' => 2, 'off_peak' => 180, 'peak' => 250],
            ['name' => 'Family Suite', 'capacity' => 4, 'off_peak' => 240, 'peak' => 320],
            ['name' => 'Penthouse', 'capacity' => 4, 'off_peak' => 500, 'peak' => 750],
        ];

        $roomStatuses = ['Available', 'Occupied', 'Cleaning', 'Out of Service'];
        $hotelIds = [];

        foreach ($hotels as $index => $hotel) {
            $hotelId = DB::table('hotels')->where('code', $hotel['code'])->value('id');
            $countryCode = $hotel['country_code'] ?? null;
            $timezoneId = null;

            if ($countryCode && ! empty($hotel['timezone'])) {
                $timezoneId = DB::table('timezones')
                    ->where('country_code', $countryCode)
                    ->where('timezone', $hotel['timezone'])
                    ->value('id');
            }

            if ($hotelId) {
                DB::table('hotels')->where('id', $hotelId)->update([
                    'name' => $hotel['name'],
                    'country_code' => $countryCode,
                    'timezone_id' => $timezoneId,
                    'updated_at' => $now,
                ]);
            } else {
                $hotelId = DB::table('hotels')->insertGetId([
                    'name' => $hotel['name'],
                    'code' => $hotel['code'],
                    'country_code' => $countryCode,
                    'timezone_id' => $timezoneId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $hotelIds[$hotel['code']] = $hotelId;
            $roomTypeIds = [];

            foreach ($roomTypes as $type) {
                $roomTypeId = DB::table('room_types')
                    ->where('hotel_id', $hotelId)
                    ->where('name', $type['name'])
                    ->value('id');

                $payload = [
                    'hotel_id' => $hotelId,
                    'name' => $type['name'],
                    'max_adults' => $type['capacity'],
                    'max_children' => max(0, $type['capacity'] - 2),
                    'base_occupancy' => $type['capacity'],
                    'price_off_peak' => $type['off_peak'],
                    'price_peak' => $type['peak'],
                    'active_rate' => 'off_peak',
                    'updated_at' => $now,
                ];

                if ($roomTypeId) {
                    DB::table('room_types')->where('id', $roomTypeId)->update($payload);
                } else {
                    $payload['created_at'] = $now;
                    $roomTypeId = DB::table('room_types')->insertGetId($payload);
                }

                $roomTypeIds[] = $roomTypeId;
            }

            foreach ($roomTypeIds as $typeIndex => $roomTypeId) {
                foreach (range(1, 10) as $offset) {
                    $roomNumber = sprintf('%d%02d', $typeIndex + 1, $offset);
                    $existingRoomId = DB::table('rooms')
                        ->where('hotel_id', $hotelId)
                        ->where('number', $roomNumber)
                        ->value('id');

                    $statusIndex = ($offset <= 6) ? 0 : ($offset === 7 ? 2 : ($offset === 8 ? 3 : 0)); // bias to available with a little cleaning/OOS

                    $roomPayload = [
                        'hotel_id' => $hotelId,
                        'room_type_id' => $roomTypeId,
                        'number' => $roomNumber,
                        'floor' => (string)($typeIndex + 1),
                        'status' => $roomStatuses[$statusIndex],
                        'updated_at' => $now,
                    ];

                    if ($existingRoomId) {
                        DB::table('rooms')->where('id', $existingRoomId)->update($roomPayload);
                    } else {
                        $roomPayload['created_at'] = $now;
                        DB::table('rooms')->insert($roomPayload);
                    }
                }
            }
        }

        $appUsers = [
            ['name' => 'Alice Customer', 'email' => 'alice.customer@asd.test', 'password' => 'Alice#2025!'],
            ['name' => 'Bob Booker', 'email' => 'bob.booker@asd.test', 'password' => 'Bob#2025!'],
            ['name' => 'Catherine Planner', 'email' => 'cat.planner@asd.test', 'password' => 'Cat#2025!'],
        ];

        foreach ($appUsers as $user) {
            $userId = DB::table('users')->where('email', $user['email'])->value('id');
            $payload = [
                'name' => $user['name'],
                'password' => Hash::make($user['password']),
                'email_verified_at' => $now,
                'updated_at' => $now,
            ];

            if ($userId) {
                DB::table('users')->where('id', $userId)->update($payload);
            } else {
                $payload['email'] = $user['email'];
                $payload['created_at'] = $now;
                DB::table('users')->insert($payload);
            }
        }
    }
}
