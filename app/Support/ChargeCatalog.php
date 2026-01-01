<?php

namespace App\Support;

class ChargeCatalog
{
    /**
     * Shared list of ancillary services/charges.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function options(): array
    {
        return [
            ['code' => 'airport_transfer', 'label' => 'Airport Transfer (One-way)', 'amount' => 50],
            ['code' => 'breakfast', 'label' => 'Full English Breakfast (Per Person, Per Day)', 'amount' => 20],
            ['code' => 'spa_access', 'label' => 'Spa Access (Per Person, Per Day)', 'amount' => 35],
            ['code' => 'late_checkout', 'label' => 'Late Check-out (until 2 PM)', 'amount' => 40],
            ['code' => 'custom', 'label' => 'Custom', 'amount' => 0],
        ];
    }
}
