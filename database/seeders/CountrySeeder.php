<?php

namespace Database\Seeders;

use Database\Seeders\Support\CountryList;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [];

        foreach (CountryList::all() as $code => $name) {
            $rows[] = [
                'code' => $code,
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('countries')->upsert($rows, ['code'], ['name', 'updated_at']);
    }
}
