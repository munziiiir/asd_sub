<?php

namespace Database\Seeders;

use Database\Seeders\Support\CountryList;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use DateTimeZone;

class TimezoneSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [];

        foreach (CountryList::all() as $code => $name) {
            $timezones = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $code);

            foreach ($timezones as $tz) {
                $rows[] = [
                    'country_code' => $code,
                    'country_name' => $name,
                    'timezone' => $tz,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (! empty($rows)) {
            DB::table('timezones')->upsert(
                $rows,
                ['country_code', 'timezone'],
                ['country_name', 'updated_at'],
            );
        }
    }
}
