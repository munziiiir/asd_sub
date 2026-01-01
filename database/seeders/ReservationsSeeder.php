<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ReservationsSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $today = $now->copy()->startOfDay();
        $windowStart = Carbon::create($today->year, 11, 1, 0, 0, 0, $today->timezone);
        $windowEnd = Carbon::create($today->year, 12, 31, 0, 0, 0, $today->timezone);
        $futureCutoff = Carbon::create($today->year, 12, 30, 0, 0, 0, $today->timezone);

        $this->resetTables();

        $customers = $this->seedCustomers($this->customerProfiles(), $now);
        if (empty($customers)) {
            return;
        }

        $hotels = DB::table('hotels')->select('id', 'code')->get();
        if ($hotels->isEmpty()) {
            return;
        }

        $rooms = DB::table('rooms')->get();
        $roomTypes = DB::table('room_types')->get()->keyBy('id');
        $roomsByHotel = $rooms->groupBy('hotel_id')->map(fn ($group) => $group->all());
        $staffByHotel = $this->staffByHotel();

        $auditLogs = [];

        foreach ($hotels as $hotel) {
            if (! isset($roomsByHotel[$hotel->id])) {
                continue;
            }

            $auditLogs = array_merge(
                $auditLogs,
                $this->generateHotelStays(
                    $hotel,
                    $roomsByHotel[$hotel->id],
                    $roomTypes,
                    $customers,
                    $staffByHotel[$hotel->id] ?? [],
                    $windowStart,
                    $windowEnd,
                    $futureCutoff,
                    $today,
                    $now
                )
            );
        }

        $auditLogs = array_merge($auditLogs, $this->seedLoginAuditSamples($now, $customers, $staffByHotel));

        if (! empty($auditLogs)) {
            DB::table('audit_logs')->insert($auditLogs);
        }
    }

    private function resetTables(): void
    {
        DB::table('payments')->delete();
        DB::table('charges')->delete();
        DB::table('folios')->delete();
        DB::table('check_outs')->delete();
        DB::table('check_ins')->delete();
        DB::table('reservation_occupants')->delete();
        DB::table('reservation_room')->delete();
        DB::table('reservations')->delete();
        DB::table('room_status_logs')->delete();
        DB::table('audit_logs')->delete();
    }

    /**
     * @param array<int,array<string,mixed>> $customers
     * @return array<string,int>
     */
    private function seedCustomers(array $customers, Carbon $now): array
    {
        $customerIds = [];

        foreach ($customers as $customer) {
            $userData = $customer['user'];
            $profile = $customer['profile'];

            $userId = DB::table('users')->where('email', $userData['email'])->value('id');

            $userPayload = [
                'name' => $userData['name'],
                'email' => $userData['email'],
                'email_verified_at' => $now,
                'password' => Hash::make($userData['password']),
                'updated_at' => $now,
            ];

            if ($userId) {
                DB::table('users')->where('id', $userId)->update($userPayload);
            } else {
                $userPayload['created_at'] = $now;
                $userPayload['remember_token'] = Str::random(10);
                $userId = DB::table('users')->insertGetId($userPayload);
            }

            $customerId = DB::table('customer_users')->where('user_id', $userId)->value('id');

            $profileFields = [
                'phone',
                'address_line1',
                'address_line2',
                'city',
                'state',
                'postal_code',
                'country',
                'billing_address_line1',
                'billing_address_line2',
                'billing_city',
                'billing_state',
                'billing_postal_code',
                'billing_country',
            ];

            $customerPayload = [
                'user_id' => $userId,
                'avatar' => $profile['avatar'] ?? null,
                'name' => $profile['name'] ?? $userData['name'],
                'email' => $profile['email'] ?? $userData['email'],
                'updated_at' => $now,
            ];

            foreach ($profileFields as $field) {
                $customerPayload[$field] = $profile[$field] ?? null;
            }

            if ($customerId) {
                DB::table('customer_users')->where('id', $customerId)->update($customerPayload);
            } else {
                $customerPayload['created_at'] = $now;
                $customerId = DB::table('customer_users')->insertGetId($customerPayload);
            }

            $customerIds[$userData['email']] = $customerId;
        }

        return $customerIds;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function customerProfiles(): array
    {
        return [
            [
                'user' => [
                    'name' => 'Alice Customer',
                    'email' => 'alice.customer@asd.test',
                    'password' => 'Alice#2025!',
                ],
                'profile' => [
                    'name' => 'Alice Customer',
                    'phone' => '+44 20 7946 0011',
                    'address_line1' => '221B Baker Street',
                    'city' => 'London',
                    'state' => 'Greater London',
                    'postal_code' => 'NW1 6XE',
                    'country' => 'United Kingdom',
                    'billing_address_line1' => '221B Baker Street',
                    'billing_city' => 'London',
                    'billing_state' => 'Greater London',
                    'billing_postal_code' => 'NW1 6XE',
                    'billing_country' => 'United Kingdom',
                ],
            ],
            [
                'user' => [
                    'name' => 'Bob Booker',
                    'email' => 'bob.booker@asd.test',
                    'password' => 'Bob#2025!',
                ],
                'profile' => [
                    'name' => 'Bob Booker',
                    'phone' => '+44 113 496 8844',
                    'address_line1' => '14 Briggate',
                    'city' => 'Leeds',
                    'state' => 'West Yorkshire',
                    'postal_code' => 'LS1 6HD',
                    'country' => 'United Kingdom',
                    'billing_address_line1' => '14 Briggate',
                    'billing_city' => 'Leeds',
                    'billing_state' => 'West Yorkshire',
                    'billing_postal_code' => 'LS1 6HD',
                    'billing_country' => 'United Kingdom',
                ],
            ],
            [
                'user' => [
                    'name' => 'Catherine Planner',
                    'email' => 'cat.planner@asd.test',
                    'password' => 'Cat#2025!',
                ],
                'profile' => [
                    'name' => 'Catherine Planner',
                    'phone' => '+44 141 496 7722',
                    'address_line1' => '88 Sauchiehall Street',
                    'city' => 'Glasgow',
                    'state' => 'Scotland',
                    'postal_code' => 'G2 3DE',
                    'country' => 'United Kingdom',
                    'billing_address_line1' => '88 Sauchiehall Street',
                    'billing_city' => 'Glasgow',
                    'billing_state' => 'Scotland',
                    'billing_postal_code' => 'G2 3DE',
                    'billing_country' => 'United Kingdom',
                ],
            ],
            [
                'user' => [
                    'name' => 'Diego Traveler',
                    'email' => 'diego.traveler@asd.test',
                    'password' => 'Diego#2025!',
                ],
                'profile' => [
                    'name' => 'Diego Traveler',
                    'phone' => '+44 161 555 9087',
                    'address_line1' => '12 Deansgate',
                    'city' => 'Manchester',
                    'state' => 'Greater Manchester',
                    'postal_code' => 'M3 2RJ',
                    'country' => 'United Kingdom',
                    'billing_address_line1' => '12 Deansgate',
                    'billing_city' => 'Manchester',
                    'billing_state' => 'Greater Manchester',
                    'billing_postal_code' => 'M3 2RJ',
                    'billing_country' => 'United Kingdom',
                ],
            ],
            [
                'user' => [
                    'name' => 'Evelyn Guest',
                    'email' => 'evelyn.guest@asd.test',
                    'password' => 'Evelyn#2025!',
                ],
                'profile' => [
                    'name' => 'Evelyn Guest',
                    'phone' => '+44 29 2010 4411',
                    'address_line1' => '5 Cathedral Road',
                    'city' => 'Cardiff',
                    'state' => 'Wales',
                    'postal_code' => 'CF11 9HA',
                    'country' => 'United Kingdom',
                    'billing_address_line1' => '5 Cathedral Road',
                    'billing_city' => 'Cardiff',
                    'billing_state' => 'Wales',
                    'billing_postal_code' => 'CF11 9HA',
                    'billing_country' => 'United Kingdom',
                ],
            ],
            [
                'user' => [
                    'name' => 'Farah Explorer',
                    'email' => 'farah.explorer@asd.test',
                    'password' => 'Farah#2025!',
                ],
                'profile' => [
                    'name' => 'Farah Explorer',
                    'phone' => '+44 131 467 2231',
                    'address_line1' => '30 Royal Mile',
                    'city' => 'Edinburgh',
                    'state' => 'Scotland',
                    'postal_code' => 'EH1 1SA',
                    'country' => 'United Kingdom',
                    'billing_address_line1' => '30 Royal Mile',
                    'billing_city' => 'Edinburgh',
                    'billing_state' => 'Scotland',
                    'billing_postal_code' => 'EH1 1SA',
                    'billing_country' => 'United Kingdom',
                ],
            ],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function staffByHotel(): array
    {
        $staff = DB::table('staff_users')
            ->select('id', 'hotel_id', 'role')
            ->get()
            ->groupBy('hotel_id')
            ->map(function ($group) {
                return [
                    'manager' => $group->firstWhere('role', 'manager')?->id,
                    'frontdesk' => $group->firstWhere('role', 'frontdesk')?->id,
                ];
            })
            ->toArray();

        return $staff;
    }

    /**
     * @param array<int,\stdClass> $rooms
     * @param array<string,int> $customers
     * @param array<string,int|null> $staff
     * @return array<int,array<string,mixed>>
     */
    private function generateHotelStays(
        object $hotel,
        array $rooms,
        \Illuminate\Support\Collection $roomTypes,
        array $customers,
        array $staff,
        Carbon $windowStart,
        Carbon $windowEnd,
        Carbon $futureCutoff,
        Carbon $today,
        Carbon $now
    ): array {
        $auditLogs = [];
        $roomSchedules = [];
        foreach ($rooms as $room) {
            $roomSchedules[$room->id] = [];
        }

        $incremental = 1;
        $checkedOutRooms = [];

        $windows = [
            'past' => [
                'from' => $windowStart,
                'to' => $futureCutoff->copy()->subDays(10)->max($windowStart),
                'count' => 12,
            ],
            'current' => [
                'from' => $today->copy()->subDays(2)->max($windowStart),
                'to' => $today->copy()->addDays(2)->min($futureCutoff),
                'count' => 8,
            ],
            'future' => [
                'from' => $today->copy()->addDays(3)->max($windowStart),
                'to' => $futureCutoff,
                'count' => 10,
            ],
        ];

        foreach ($windows as $windowKey => $window) {
            /** @var Carbon $start */
            $start = $window['from'];
            /** @var Carbon $end */
            $end = $window['to'];

            if ($start->gt($end)) {
                continue;
            }

            for ($i = 0; $i < $window['count']; $i++) {
                $checkInDate = $this->randomDateBetween($start, $end);
                $nights = $this->nightsForWindow($windowKey, $checkInDate);
                $checkOutDate = $checkInDate->copy()->addDays($nights);
                if ($checkOutDate->gt($windowEnd)) {
                    $checkOutDate = $windowEnd->copy();
                    $nights = max(1, $checkInDate->diffInDays($checkOutDate));
                }

                if ($checkOutDate->lte($checkInDate)) {
                    $checkOutDate = $checkInDate->copy()->addDay();
                    $nights = 1;
                }

                $roomCount = $this->roomCountForWindow($windowKey);
                $assignedRooms = $this->assignRooms($rooms, $roomSchedules, $checkInDate, $checkOutDate, $roomCount);

                if (empty($assignedRooms)) {
                    continue;
                }

                $roomTypeIds = array_map(fn ($room) => $room->room_type_id, $assignedRooms);
                $capacityAdults = array_sum(array_map(fn ($roomTypeId) => (int) ($roomTypes[$roomTypeId]?->max_adults ?? 2), $roomTypeIds));
                $capacityChildren = array_sum(array_map(fn ($roomTypeId) => (int) ($roomTypes[$roomTypeId]?->max_children ?? 0), $roomTypeIds));

                $adults = max(1, min($capacityAdults, random_int(1, $capacityAdults)));
                $children = ($capacityChildren > 0) ? random_int(0, min($capacityChildren, max(0, $capacityChildren - 1))) : 0;
                if ($adults + $children === 0) {
                    $adults = 1;
                }

                $status = $this->statusForWindow($windowKey, $checkInDate, $checkOutDate, $today);
                $nightlyRate = $this->calculateNightlyRate($roomTypeIds, $roomTypes, $checkInDate, $checkOutDate);

                $customerEmail = array_rand($customers);
                $customerId = $customers[$customerEmail];

                $reservationId = DB::table('reservations')->insertGetId([
                    'hotel_id' => $hotel->id,
                    'customer_id' => $customerId,
                    'incremental_no' => $incremental,
                    'code' => sprintf('%s-%04d', $hotel->code, $incremental),
                    'status' => $status,
                    'check_in_date' => $checkInDate->toDateString(),
                    'check_out_date' => $checkOutDate->toDateString(),
                    'adults' => $adults,
                    'children' => $children,
                    'nightly_rate' => $nightlyRate,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $incremental++;

                foreach ($assignedRooms as $room) {
                    DB::table('reservation_room')->insert([
                        'hotel_id' => $hotel->id,
                        'reservation_id' => $reservationId,
                        'room_id' => $room->id,
                        'from_date' => $checkInDate->toDateString(),
                        'to_date' => $checkOutDate->toDateString(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $roomStatus = match ($status) {
                        'CheckedIn' => 'Occupied',
                        'CheckedOut' => 'Cleaning',
                        default => 'Available',
                    };

                    DB::table('rooms')
                        ->where('id', $room->id)
                        ->update([
                            'status' => $roomStatus,
                            'updated_at' => $now,
                        ]);
                }

                $this->seedOccupants($reservationId, $adults, $children, $now);

                $ledger = $this->createFolioLedger(
                    $reservationId,
                    $hotel->code,
                    $status,
                    $checkInDate,
                    $checkOutDate,
                    $nights,
                    $roomCount,
                    $adults,
                    $children,
                    $nightlyRate,
                    $now
                );

                $ledgerLogs = (array) ($ledger['logs'] ?? []);
                if ($ledgerLogs) {
                    $auditLogs = array_merge($auditLogs, $ledgerLogs);
                }

                $auditLogs[] = $this->makeAudit(
                    'booking.created',
                    'system',
                    null,
                    'seeder',
                    'App\\Models\\Reservation',
                    $reservationId,
                    [
                        'code' => sprintf('%s-%04d', $hotel->code, $incremental - 1),
                        'status' => $status,
                        'check_in' => $checkInDate->toDateString(),
                        'check_out' => $checkOutDate->toDateString(),
                        'total' => $ledger['grand_total'],
                    ],
                    $now
                );

                if (in_array($status, ['CheckedIn', 'CheckedOut'], true)) {
                    $handler = $this->pickStaffHandler($staff);
                    $auditLogs[] = $this->createCheckIn($reservationId, $assignedRooms[0]->id, $handler, $checkInDate, $now);
                }

                if ($status === 'CheckedOut') {
                    $handler = $this->pickStaffHandler($staff);
                    $auditLogs[] = $this->createCheckOut(
                        $reservationId,
                        $assignedRooms[0]->id,
                        $handler,
                        $checkOutDate,
                        $ledger['room_total'],
                        $ledger['extras_total'],
                        $ledger['grand_total'],
                        $now
                    );

                    $checkedOutRooms[] = [
                        'room_id' => $assignedRooms[0]->id,
                        'reservation_id' => $reservationId,
                        'checkout_at' => $checkOutDate->copy(),
                    ];
                }
            }
        }

        $auditLogs = array_merge($auditLogs, $this->seedRoomStatusLogs($hotel->id, $checkedOutRooms, $staff, $now));

        return $auditLogs;
    }

    private function randomDateBetween(Carbon $start, Carbon $end): Carbon
    {
        if ($start->equalTo($end)) {
            return $start->copy();
        }

        $timestamp = random_int($start->timestamp, $end->timestamp);

        return Carbon::createFromTimestamp($timestamp, $start->timezone)->startOfDay();
    }

    private function statusForWindow(string $windowKey, Carbon $checkIn, Carbon $checkOut, Carbon $today): string
    {
        $roll = random_int(1, 100);

        return match ($windowKey) {
            'past' => $roll <= 70 ? 'CheckedOut' : ($roll <= 80 ? 'NoShow' : ($roll <= 90 ? 'Cancelled' : 'CheckedIn')),
            'current' => $roll <= 70 ? 'CheckedIn' : ($roll <= 90 ? 'Confirmed' : 'Pending'),
            default => $roll <= 60 ? 'Confirmed' : ($roll <= 85 ? 'Pending' : 'Cancelled'),
        };
    }

    private function nightsForWindow(string $windowKey, Carbon $checkInDate): int
    {
        $isWeekendArrival = $checkInDate->isWeekend();

        return match ($windowKey) {
            'past' => $isWeekendArrival ? random_int(2, 5) : random_int(2, 6),
            'current' => $isWeekendArrival ? random_int(2, 5) : random_int(2, 4),
            default => $isWeekendArrival ? random_int(2, 5) : random_int(2, 5),
        };
    }

    private function roomCountForWindow(string $windowKey): int
    {
        $roll = random_int(1, 100);

        return ($windowKey === 'past' && $roll > 80) || ($roll > 88) ? 2 : 1;
    }

    /**
     * @param array<int,\stdClass> $rooms
     * @param array<int,array<int,array{0:Carbon,1:Carbon}>> $roomSchedules
     * @return array<int,\stdClass>
     */
    private function assignRooms(array $rooms, array &$roomSchedules, Carbon $start, Carbon $end, int $roomCount): array
    {
        $available = [];

        foreach ($rooms as $room) {
            $schedule = $roomSchedules[$room->id] ?? [];
            if ($this->isRoomAvailable($schedule, $start, $end)) {
                $available[] = $room;
            }
        }

        if (count($available) < $roomCount) {
            return [];
        }

        shuffle($available);
        $selected = array_slice($available, 0, $roomCount);

        foreach ($selected as $room) {
            $roomSchedules[$room->id][] = [$start->copy(), $end->copy()];
        }

        return $selected;
    }

    /**
     * @param array<int,array{0:Carbon,1:Carbon}> $schedule
     */
    private function isRoomAvailable(array $schedule, Carbon $start, Carbon $end): bool
    {
        foreach ($schedule as [$from, $to]) {
            if ($start->lt($to) && $end->gt($from)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int,int> $roomTypeIds
     */
    private function calculateNightlyRate(array $roomTypeIds, \Illuminate\Support\Collection $roomTypes, Carbon $checkInDate, Carbon $checkOutDate): float
    {
        $touchesWeekend = $this->stayTouchesWeekend($checkInDate, $checkOutDate);
        $rateTotal = 0.0;

        foreach ($roomTypeIds as $typeId) {
            $type = $roomTypes[$typeId] ?? null;
            if (! $type) {
                continue;
            }

            $baseRate = ($checkInDate->month === 12 || $checkOutDate->month === 12)
                ? (float) $type->price_peak
                : (float) $type->price_off_peak;

            $rateTotal += $baseRate;
        }

        if ($touchesWeekend) {
            $rateTotal *= 1.08;
        }

        return round($rateTotal, 2);
    }

    private function stayTouchesWeekend(Carbon $start, Carbon $end): bool
    {
        $cursor = $start->copy();
        while ($cursor->lt($end)) {
            if ($cursor->isWeekend()) {
                return true;
            }
            $cursor->addDay();
        }

        return false;
    }

    private function seedOccupants(int $reservationId, int $adults, int $children, Carbon $now): void
    {
        $faker = fake();
        $rows = [];

        for ($i = 0; $i < $adults; $i++) {
            $rows[] = [
                'reservation_id' => $reservationId,
                'full_name' => $faker->name(),
                'age' => random_int(22, 68),
                'type' => 'Adult',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        for ($i = 0; $i < $children; $i++) {
            $rows[] = [
                'reservation_id' => $reservationId,
                'full_name' => $faker->firstName() . ' ' . $faker->lastName(),
                'age' => random_int(3, 15),
                'type' => 'Child',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($rows)) {
            DB::table('reservation_occupants')->insert($rows);
        }
    }

    /**
     * @return array<string,float|int|null>
     */
    private function createFolioLedger(
        int $reservationId,
        string $hotelCode,
        string $status,
        Carbon $checkInDate,
        Carbon $checkOutDate,
        int $nights,
        int $roomCount,
        int $adults,
        int $children,
        float $nightlyRate,
        Carbon $now
    ): array {
        $folioNo = sprintf('%s-F-%s', $hotelCode, str_pad((string) $reservationId, 4, '0', STR_PAD_LEFT));
        $folioStatus = $status === 'CheckedOut' ? 'Closed' : 'Open';
        $logs = [];

        $folioId = DB::table('folios')->insertGetId([
            'reservation_id' => $reservationId,
            'folio_no' => $folioNo,
            'status' => $folioStatus,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $roomTotal = round($nightlyRate * $nights, 2);

        $charges = [
            [
                'folio_id' => $folioId,
                'post_date' => $checkInDate->toDateString(),
                'description' => 'Room charges',
                'qty' => $nights,
                'unit_price' => $nightlyRate,
                'tax_amount' => 0,
                'total_amount' => $roomTotal,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        $extras = $this->buildExtras($folioId, $adults, $children, $nights, $roomCount, $status, $checkInDate, $checkOutDate, $now);
        $charges = array_merge($charges, $extras['charges']);

        DB::table('charges')->insert($charges);

        $chargeTotal = array_sum(array_map(fn ($charge) => (float) $charge['total_amount'], $charges));

        $payments = $this->buildPayments($folioId, $status, $chargeTotal, $roomTotal, $checkInDate, $checkOutDate, $now);
        $paymentsTotal = array_sum(array_map(fn ($payment) => (float) $payment['amount'], $payments));

        if (! empty($payments)) {
            DB::table('payments')->insert($payments);

            foreach ($payments as $payment) {
                $logs[] = $this->makeAudit(
                    'payment.processed',
                    'system',
                    null,
                    'billing',
                    'App\\Models\\Folio',
                    $folioId,
                    [
                        'amount' => $payment['amount'],
                        'method' => $payment['method'],
                        'paid_at' => $payment['paid_at']->toDateTimeString(),
                    ],
                    $now
                );
            }
        }

        return [
            'folio_id' => $folioId,
            'room_total' => $roomTotal,
            'extras_total' => $extras['total'],
            'grand_total' => $chargeTotal,
            'payments_total' => $paymentsTotal,
            'logs' => $logs,
        ];
    }

    /**
     * @return array{charges: array<int,array<string,mixed>>, total: float}
     */
    private function buildExtras(
        int $folioId,
        int $adults,
        int $children,
        int $nights,
        int $roomCount,
        string $status,
        Carbon $checkInDate,
        Carbon $checkOutDate,
        Carbon $now
    ): array {
        $charges = [];
        $extrasTotal = 0.0;
        $guestCount = $adults + $children;

        if (in_array($status, ['CheckedIn', 'CheckedOut'], true)) {
            $charges[] = [
                'folio_id' => $folioId,
                'post_date' => $checkInDate->toDateString(),
                'description' => 'Breakfast (per guest)',
                'qty' => $guestCount * $nights,
                'unit_price' => 15,
                'tax_amount' => 0,
                'total_amount' => round(15 * $guestCount * $nights, 2),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $charges[] = [
                'folio_id' => $folioId,
                'post_date' => $checkOutDate->toDateString(),
                'description' => 'City tax',
                'qty' => $roomCount * $nights,
                'unit_price' => 4.5,
                'tax_amount' => 0,
                'total_amount' => round(4.5 * $roomCount * $nights, 2),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (random_int(1, 100) <= 30) {
                $charges[] = [
                    'folio_id' => $folioId,
                    'post_date' => $checkOutDate->toDateString(),
                    'description' => 'Parking',
                    'qty' => $nights,
                    'unit_price' => 18,
                    'tax_amount' => 0,
                    'total_amount' => round(18 * $nights, 2),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach ($charges as $charge) {
            $extrasTotal += (float) $charge['total_amount'];
        }

        return ['charges' => $charges, 'total' => round($extrasTotal, 2)];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildPayments(
        int $folioId,
        string $status,
        float $chargeTotal,
        float $roomTotal,
        Carbon $checkInDate,
        Carbon $checkOutDate,
        Carbon $now
    ): array {
        $payments = [];

        $deposit = match ($status) {
            'CheckedOut' => $chargeTotal,
            'CheckedIn' => max(75, round($chargeTotal * 0.7, 2)),
            'Confirmed', 'Pending' => max(50, round($chargeTotal * 0.35, 2)),
            'NoShow' => max($roomTotal, round($chargeTotal * 0.5, 2)),
            'Cancelled' => random_int(1, 100) <= 20 ? round(min($chargeTotal, $roomTotal), 2) : 0,
            default => 0,
        };

        if ($deposit <= 0) {
            return [];
        }

        $payments[] = [
            'folio_id' => $folioId,
            'method' => $this->randomPaymentMethod(),
            'amount' => round($deposit, 2),
            'txn_ref' => 'SEED-' . Str::upper(Str::random(8)),
            'paid_at' => $this->resolvePaidAt($status, $checkInDate, $checkOutDate, $now),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        return $payments;
    }

    private function resolvePaidAt(string $status, Carbon $checkInDate, Carbon $checkOutDate, Carbon $now): Carbon
    {
        $paidAt = ($status === 'CheckedOut')
            ? $checkOutDate->copy()->addHours(random_int(8, 18))
            : $checkInDate->copy()->addHours(random_int(8, 18));

        if (in_array($status, ['Confirmed', 'Pending', 'NoShow', 'Cancelled'], true)) {
            $paidAt = $checkInDate->copy()->subDays(2)->addHours(random_int(7, 18));

            if ($paidAt->gt($now)) {
                $paidAt = $now->copy()->subHours(random_int(0, 12));
            }
        }

        return $paidAt;
    }

    private function randomPaymentMethod(): string
    {
        $methods = ['Card on file', 'POS Card', 'Cash', 'Bank Transfer'];

        return $methods[array_rand($methods)];
    }

    private function createCheckIn(int $reservationId, int $roomId, ?int $staffId, Carbon $checkInDate, Carbon $now): array
    {
        $checkedInAt = $checkInDate->copy()->addHours(15)->addMinutes(random_int(0, 45));

        DB::table('check_ins')->insert([
            'reservation_id' => $reservationId,
            'room_id' => $roomId,
            'handled_by' => $staffId,
            'checked_in_at' => $checkedInAt,
            'identity_verified' => true,
            'identity_document_type' => 'Passport',
            'identity_document_number' => 'AUTO-' . random_int(100000, 999999),
            'identity_notes' => null,
            'remarks' => 'Seeded check-in',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->makeAudit(
            'checkin.completed',
            $staffId ? 'staff' : 'system',
            $staffId,
            'frontdesk',
            'App\\Models\\Reservation',
            $reservationId,
            ['room_id' => $roomId, 'checked_in_at' => $checkedInAt->toDateTimeString()],
            $now
        );
    }

    private function createCheckOut(
        int $reservationId,
        int $roomId,
        ?int $staffId,
        Carbon $checkOutDate,
        float $roomTotal,
        float $extrasTotal,
        float $grandTotal,
        Carbon $now
    ): array {
        $checkedOutAt = $checkOutDate->copy()->addHours(11)->addMinutes(random_int(0, 50));

        DB::table('check_outs')->insert([
            'reservation_id' => $reservationId,
            'room_id' => $roomId,
            'handled_by' => $staffId,
            'checked_out_at' => $checkedOutAt,
            'room_charges_total' => $roomTotal,
            'extras_breakdown' => json_encode(['extras' => $extrasTotal]),
            'extras_total' => $extrasTotal,
            'grand_total' => $grandTotal,
            'final_payment_method' => 'Card on file',
            'final_payment_reference' => 'CHKOUT-' . Str::upper(Str::random(6)),
            'final_payment_status' => 'captured',
            'settled_at' => $checkedOutAt,
            'notes' => 'Seeded check-out',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->makeAudit(
            'checkout.completed',
            $staffId ? 'staff' : 'system',
            $staffId,
            'frontdesk',
            'App\\Models\\Reservation',
            $reservationId,
            ['room_id' => $roomId, 'checked_out_at' => $checkedOutAt->toDateTimeString(), 'grand_total' => $grandTotal],
            $now
        );
    }

    /**
     * @param array<string,int|null> $staff
     */
    private function pickStaffHandler(array $staff): ?int
    {
        return $staff['frontdesk'] ?? $staff['manager'] ?? null;
    }

    private function makeAudit(
        string $event,
        ?string $actorType,
        ?int $actorId,
        ?string $actorRole,
        ?string $subjectType,
        ?int $subjectId,
        array $meta,
        Carbon $timestamp
    ): array {
        return [
            'event' => $event,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'success' => true,
            'ip_address' => null,
            'user_agent' => null,
            'meta' => json_encode($meta),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $checkedOutRooms
     * @param array<string,int|null> $staff
     * @return array<int,array<string,mixed>>
     */
    private function seedRoomStatusLogs(int $hotelId, array $checkedOutRooms, array $staff, Carbon $now): array
    {
        $auditLogs = [];
        $slice = array_slice($checkedOutRooms, 0, 6);

        foreach ($slice as $entry) {
            $roomId = $entry['room_id'];
            $reservationId = $entry['reservation_id'];
            $start = $entry['checkout_at']->copy()->addMinutes(random_int(15, 60));
            $revert = $start->copy()->addHours(random_int(1, 3));
            $handler = $this->pickStaffHandler($staff);

            DB::table('room_status_logs')->insert([
                'hotel_id' => $hotelId,
                'room_id' => $roomId,
                'reservation_id' => $reservationId,
                'changed_by_staff_id' => $handler,
                'assigned_staff_id' => $handler,
                'context' => 'housekeeping',
                'previous_status' => 'Cleaning',
                'new_status' => 'Available',
                'revert_to_status' => null,
                'revert_at' => null,
                'reverted_at' => $revert,
                'note' => 'Auto-cleaned after checkout',
                'meta' => json_encode(['source' => 'seed']),
                'created_at' => $start,
                'updated_at' => $revert,
            ]);

            DB::table('rooms')->where('id', $roomId)->update([
                'status' => 'Available',
                'updated_at' => $revert,
            ]);

            $auditLogs[] = $this->makeAudit(
                'room.status.changed',
                $handler ? 'staff' : 'system',
                $handler,
                'housekeeping',
                'App\\Models\\Room',
                $roomId,
                ['reservation_id' => $reservationId, 'new_status' => 'Available'],
                $revert
            );
        }

        return $auditLogs;
    }

    /**
     * @param array<string,int> $customers
     * @param array<int,array<string,int|null>> $staffByHotel
     * @return array<int,array<string,mixed>>
     */
    private function seedLoginAuditSamples(Carbon $now, array $customers, array $staffByHotel): array
    {
        $logs = [];
        $firstCustomerId = reset($customers) ?: null;
        $firstHotelStaff = reset($staffByHotel) ?: ['manager' => null, 'frontdesk' => null];

        if ($firstCustomerId) {
            $logs[] = $this->makeAudit(
                'auth.login',
                'user',
                $firstCustomerId,
                'guest',
                'App\\Models\\User',
                $firstCustomerId,
                ['guard' => 'web'],
                $now
            );
        }

        if (! empty($firstHotelStaff['manager'])) {
            $logs[] = $this->makeAudit(
                'auth.login',
                'staff',
                $firstHotelStaff['manager'],
                'manager',
                'App\\Models\\StaffUser',
                $firstHotelStaff['manager'],
                ['guard' => 'staff'],
                $now
            );
        }

        if (! empty($firstHotelStaff['frontdesk'])) {
            $logs[] = $this->makeAudit(
                'auth.login',
                'staff',
                $firstHotelStaff['frontdesk'],
                'frontdesk',
                'App\\Models\\StaffUser',
                $firstHotelStaff['frontdesk'],
                ['guard' => 'staff'],
                $now
            );
        }

        $adminId = DB::table('admin_users')->value('id');
        if ($adminId) {
            $logs[] = $this->makeAudit(
                'auth.login',
                'admin',
                $adminId,
                'admin',
                'App\\Models\\AdminUser',
                $adminId,
                ['guard' => 'admin'],
                $now
            );
        }

        return $logs;
    }
}
