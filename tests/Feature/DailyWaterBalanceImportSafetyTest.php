<?php

namespace Tests\Feature;

use App\Models\DailyWaterBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyWaterBalanceImportSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_daily_record_is_updated_in_place_instead_of_created_twice(): void
    {
        DailyWaterBalance::updateOrCreate(
            [
                'pg' => 'PG-01',
                'lokasi' => 'Lokasi A',
                'tanggal' => '2026-09-01',
            ],
            [
                'rainfall_mm' => 12.5,
                'luas_siram_rencana_ha' => 10,
                'luas_siram_real_ha' => 8,
                'irigasi_mm' => 40,
                'irigasi_efektif_mm' => 32,
                'evapotranspirasi_mm' => 25,
                'water_balance_mm' => 80.5,
                'status_zone' => 'FC - MAD 50%',
            ]
        );

        DailyWaterBalance::updateOrCreate(
            [
                'pg' => 'PG-01',
                'lokasi' => 'Lokasi A',
                'tanggal' => '2026-09-01',
            ],
            [
                'rainfall_mm' => 20,
                'luas_siram_rencana_ha' => 11,
                'luas_siram_real_ha' => 9,
                'irigasi_mm' => 45,
                'irigasi_efektif_mm' => 36,
                'evapotranspirasi_mm' => 30,
                'water_balance_mm' => 90,
                'status_zone' => 'FC - MAD 50%',
            ]
        );

        $this->assertDatabaseCount('daily_water_balances', 1);
        $this->assertDatabaseHas('daily_water_balances', [
            'pg' => 'PG-01',
            'lokasi' => 'Lokasi A',
            'tanggal' => '2026-09-01',
            'water_balance_mm' => '90.00',
        ]);
    }
}
