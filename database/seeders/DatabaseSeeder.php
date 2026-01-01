<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
            TimezoneSeeder::class,
            AdminUserSeeder::class,
            mysqlSeeder::class,
            StaffUserSeeder::class,
            ReservationsSeeder::class,
        ]);
    }
}
