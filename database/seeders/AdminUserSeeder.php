<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $username = env('ADMIN_BOOT_USERNAME', 'admin');
        $password = env('ADMIN_BOOT_PASSWORD', 'Adm1n#2025!');
        $name = env('ADMIN_BOOT_NAME', 'System Administrator');
        $now = Carbon::now();

        if (! $username || ! $password) {
            return;
        }

        AdminUser::updateOrCreate(
            ['username' => $username],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'is_active' => true,
                'last_password_changed_at' => $now,
            ],
        );
    }
}
