<?php

namespace Tests\Feature;

use App\Models\DailyWaterBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyWaterBalanceExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_route_returns_successful_response(): void
    {
        DailyWaterBalance::create([
            'pg' => 'PG-01',
            'lokasi' => 'Lokasi A',
            'tanggal' => '2026-09-01',
            'rainfall_mm' => 12.5,
            'luas_siram_rencana_ha' => 10,
            'luas_siram_real_ha' => 8,
            'irigasi_mm' => 40,
            'irigasi_efektif_mm' => 32,
            'evapotranspirasi_mm' => 25,
            'water_balance_mm' => 80.5,
            'status_zone' => 'FC - MAD 50%',
        ]);

        $response = $this->get('/api/water-balance/export?pg=PG-01');

        $response->assertStatus(200);
    }

    public function test_all_pg_summary_groups_locations_and_statuses(): void
    {
        DailyWaterBalance::create([
            'pg' => 'PG-01',
            'lokasi' => 'Lokasi A',
            'tanggal' => '2026-09-01',
            'water_balance_mm' => 105,
            'status_zone' => 'At FC',
        ]);

        DailyWaterBalance::create([
            'pg' => 'PG-01',
            'lokasi' => 'Lokasi B',
            'tanggal' => '2026-09-01',
            'water_balance_mm' => 54,
            'status_zone' => 'At WP',
        ]);

        $response = $this->getJson('/api/pg-summary-all');

        $response
            ->assertStatus(200)
            ->assertJsonPath('0.pg', 'PG-01')
            ->assertJsonPath('0.total_lokasi', 2)
            ->assertJsonPath('0.total_hari', 2)
            ->assertJsonPath('0.count_fc', 1)
            ->assertJsonPath('0.count_wp', 1);
    }

    public function test_all_pg_irrigation_summary_groups_frequency_by_pg_and_month(): void
    {
        DailyWaterBalance::create([
            'pg' => 'PG-01',
            'lokasi' => 'Lokasi A',
            'tanggal' => '2026-09-01',
            'irigasi_mm' => 20,
            'water_balance_mm' => 80,
            'status_zone' => 'FC - MAD 50%',
        ]);

        DailyWaterBalance::create([
            'pg' => 'PG-01',
            'lokasi' => 'Lokasi A',
            'tanggal' => '2026-09-02',
            'irigasi_mm' => 0,
            'water_balance_mm' => 75,
            'status_zone' => 'MAD 50% - WP',
        ]);

        $response = $this->getJson('/api/pg-irrigation-monthly-all');

        $response
            ->assertStatus(200)
            ->assertJsonPath('months.0', '2026-09')
            ->assertJsonPath('report.PG-01.2026-09', 1);
    }
}
