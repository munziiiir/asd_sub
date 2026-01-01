<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StaffUserSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $managerPassword = 'Mngr#2025!';
        $frontdeskPassword = 'Front#2025!';

        $managerNames = [
            'Amelia Patel', 'Luca Rossi', 'Nia Thompson', 'Sofia Nguyen', 'Ethan Walker',
            'Priya Desai', 'Noah Fischer', 'Maya Lopez', 'Jonas Keller', 'Olivia Chen',
            'Marcus Silva', 'Elena Popov', 'Daniel Kim', 'Sara Costa', 'Victor Hughes',
            'Aisha Khan', 'Leo Martin', 'Chloe Dubois', 'Yara Haddad', 'Felix Bauer',
        ];

        $frontdeskNames = [
            'Isla Turner', 'Mateo Alvarez', 'Grace Morgan', 'Hugo Laurent', 'Zara Ahmed',
            'Omar Farouk', 'Ivy Collins', 'Diego Ramirez', 'Lily Bennett', 'Akira Sato',
            'Camila Torres', 'Noel Fischer', 'Ruby Carter', 'Jasper Lin', 'Mila Novak',
            'Tariq Qureshi', 'Nora Weiss', 'Emil Johansson', 'Poppy Clarke', 'Kian Murphy',
        ];

        $hotels = DB::table('hotels')->orderBy('id')->take(20)->get(['id']);

        foreach ($hotels as $index => $hotel) {
            $managerName = $managerNames[$index] ?? "Manager {$index}";
            $frontdeskName = $frontdeskNames[$index] ?? "Frontdesk {$index}";

            $this->upsertStaff($hotel->id, $managerName, 'manager', 'Hotel Manager', 'Front Office', $managerPassword, $now);
            $this->upsertStaff($hotel->id, $frontdeskName, 'frontdesk', 'Front Desk Agent', 'Front Office', $frontdeskPassword, $now);
        }
    }

    private function upsertStaff(int $hotelId, string $name, string $role, ?string $title, ?string $department, string $password, $now): void
    {
        $email = Str::of($name)->slug('.') . '@lexiqa.com';

        $existingId = DB::table('staff_users')->where('email', $email)->value('id');

        $payload = [
            'hotel_id' => $hotelId,
            'name' => $name,
            'email' => $email,
            'email_verified_at' => $now,
            'password' => Hash::make($password),
            'remember_token' => Str::random(10),
            'role' => $role,
            'title' => $title,
            'department' => $department,
            'employment_status' => 'active',
            'avatar' => null,
            'phone' => null,
            'address_line1' => null,
            'address_line2' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'country' => null,
            'last_login_at' => null,
            'last_password_changed_at' => $now,
            'updated_at' => $now,
        ];

        if ($existingId) {
            DB::table('staff_users')->where('id', $existingId)->update($payload);
        } else {
            $payload['created_at'] = $now;
            DB::table('staff_users')->insert($payload);
        }
    }
}
