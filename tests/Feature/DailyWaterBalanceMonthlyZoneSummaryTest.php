<?php

namespace Tests\Feature;

use App\Models\DailyWaterBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyWaterBalanceMonthlyZoneSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_zone_percentages_include_weighted_all_pg_totals(): void
    {
        foreach ([
            ['pg' => '01', 'tanggal' => '2026-08-01', 'zone' => 'At FC'],
            ['pg' => '01', 'tanggal' => '2026-09-01', 'zone' => 'At WP'],
            ['pg' => '01', 'tanggal' => '2026-09-02', 'zone' => 'FC - MAD 50%'],
            ['pg' => '01', 'tanggal' => '2026-09-03', 'zone' => 'At FC'],
            ['pg' => '02', 'tanggal' => '2026-08-01', 'zone' => 'MAD 50% - WP'],
            ['pg' => '02', 'tanggal' => '2026-08-02', 'zone' => 'At WP'],
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
            ->assertJsonCount(6, 'rows')
            ->assertJsonPath('rows.0.pg', '01')
            ->assertJsonPath('rows.0.month', '2026-08')
            ->assertJsonPath('rows.1.pg', '01')
            ->assertJsonPath('rows.1.month', '2026-09')
            ->assertJsonPath('rows.2.pg', '02')
            ->assertJsonPath('rows.2.month', '2026-08')
            ->assertJsonPath('rows.4.pg', 'ALL PG')
            ->assertJsonPath('rows.4.month', '2026-08')
            ->assertJsonPath('rows.4.total_days', 3)
            ->assertJsonPath('rows.5.pg', 'ALL PG')
            ->assertJsonPath('rows.5.month', '2026-09')
            ->assertJsonPath('rows.5.total_days', 4)
            ->assertJsonPath('rows.5.percentage_wp', 50)
            ->assertJsonPath('rows.5.percentage_fc', 25)
            ->assertJsonPath('rows.5.percentage_fc_mad', 25);
    }

    public function test_all_pg_ranking_and_irrigation_keep_same_named_locations_separate(): void
    {
        foreach ([
            ['pg' => '01', 'zone' => 'At FC'],
            ['pg' => '02', 'zone' => 'At WP'],
        ] as $record) {
            DailyWaterBalance::create([
                'pg' => $record['pg'],
                'lokasi' => 'A',
                'tanggal' => '2026-08-01',
                'irigasi_mm' => 10,
                'water_balance_mm' => 50,
                'status_zone' => $record['zone'],
            ]);
        }

        $this->getJson('/api/pg-summary?pg=all')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.pg', '02')
            ->assertJsonPath('1.pg', '01');

        $this->getJson('/api/pg-irrigation-monthly?pg=all')
            ->assertOk()
            ->assertJsonCount(2, 'report')
            ->assertJsonPath('report.0.pg', '01')
            ->assertJsonPath('report.0.lokasi', 'A')
            ->assertJsonPath('report.1.pg', '02')
            ->assertJsonPath('report.1.lokasi', 'A')
            ->assertJsonPath('report.0.months.2026-08.count', 1)
            ->assertJsonPath('report.1.months.2026-08.count', 1);
    }
}
