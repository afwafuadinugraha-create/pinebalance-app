<?php

namespace Tests\Feature;

use App\Models\DailyWaterBalance;
use Tests\TestCase;

class DailyWaterBalanceMonthlyZoneSummaryTest extends TestCase
{
    public function test_monthly_zone_percentages_include_weighted_all_pg_totals(): void
    {
        foreach ([
            ['pg' => '01', 'tanggal' => '2026-09-01', 'zone' => 'At WP'],
            ['pg' => '01', 'tanggal' => '2026-09-02', 'zone' => 'FC - MAD 50%'],
            ['pg' => '01', 'tanggal' => '2026-09-03', 'zone' => 'At FC'],
            ['pg' => '02', 'tanggal' => '2026-09-01', 'zone' => 'At WP'],
        ] as $record) {
            DailyWaterBalance::create([
                'pg' => $record['pg'],
                'lokasi' => 'A',
                'tanggal' => $record['tanggal'],
                'water_balance_mm' => 50,
                'status_zone' => $record['zone'],
            ]);
        }

        $response = $this->getJson('/api/monthly-zone-summary');

        $response->assertOk()
            ->assertJsonCount(3, 'rows')
            ->assertJsonPath('rows.0.pg', '01')
            ->assertJsonPath('rows.0.percentage_wp', 33.3)
            ->assertJsonPath('rows.1.pg', '02')
            ->assertJsonPath('rows.1.percentage_wp', 100)
            ->assertJsonPath('rows.2.pg', 'ALL PG')
            ->assertJsonPath('rows.2.total_days', 4)
            ->assertJsonPath('rows.2.percentage_wp', 50)
            ->assertJsonPath('rows.2.percentage_fc', 25)
            ->assertJsonPath('rows.2.percentage_fc_mad', 25);
    }
}
