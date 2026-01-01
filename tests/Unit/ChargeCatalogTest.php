<?php

namespace Tests\Unit;

use App\Support\ChargeCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargeCatalogTest extends TestCase
{

    public function test_charge_catalog_contains_expected_codes_and_labels(): void
    {
        $options = collect(ChargeCatalog::options());
        $codes = $options->pluck('code')->all();

        $this->assertContains('spa_access', $codes);
        $this->assertContains('airport_transfer', $codes);
        $this->assertContains('custom', $codes);

        $spa = $options->firstWhere('code', 'spa_access');
        $this->assertEquals('Spa Access (Per Person, Per Day)', $spa['label']);
        $this->assertGreaterThan(0, $spa['amount']);
    }
}
