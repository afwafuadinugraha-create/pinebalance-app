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
            ['pg' => '01', 'tanggal' => '2026-08-01', 'zone' => 'At FC', 'area' => 8],
            ['pg' => '01', 'tanggal' => '2026-09-01', 'zone' => 'At WP', 'area' => 10],
            ['pg' => '01', 'tanggal' => '2026-09-02', 'zone' => 'FC - MAD 50%', 'area' => 11],
            ['pg' => '01', 'tanggal' => '2026-09-03', 'zone' => 'At FC', 'area' => 12],
            ['pg' => '02', 'tanggal' => '2026-08-01', 'zone' => 'MAD 50% - WP', 'area' => 4],
            ['pg' => '02', 'tanggal' => '2026-08-02', 'zone' => 'At WP', 'area' => 5],
            ['pg' => '02', 'tanggal' => '2026-09-01', 'zone' => 'At WP', 'area' => 5],
        ] as $record) {
            DailyWaterBalance::create([
                'pg' => $record['pg'],
                'lokasi' => 'A',
                'tanggal' => $record['tanggal'],
                'luas_siram_rencana_ha' => $record['area'],
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
            ->assertJsonPath('rows.4.total_area_ha', 13)
            ->assertJsonPath('rows.4.location_count', 2)
            ->assertJsonPath('rows.4.area_location_product', 26)
            ->assertJsonPath('rows.5.pg', 'ALL PG')
            ->assertJsonPath('rows.5.month', '2026-09')
            ->assertJsonPath('rows.5.total_days', 4)
            ->assertJsonPath('rows.5.percentage_wp', 50)
            ->assertJsonPath('rows.5.percentage_fc', 25)
            ->assertJsonPath('rows.5.percentage_fc_mad', 25)
            ->assertJsonPath('rows.5.total_area_ha', 17)
            ->assertJsonPath('rows.5.location_count', 2)
            ->assertJsonPath('rows.5.area_location_product', 34)
            ->assertJsonPath('rows.5.area_wp', 9)
            ->assertJsonPath('rows.5.area_percentage_wp', 52.9)
            ->assertJsonPath('rows.5.area_fc', 4)
            ->assertJsonPath('rows.5.area_fc_mad', 4);
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

    public function test_selected_daily_status_filters_all_summary_aggregations(): void
    {
        foreach ([
            ['tanggal' => '2026-08-01', 'status' => 'P1', 'zone' => 'At WP', 'irigasi' => 20],
            ['tanggal' => '2026-08-02', 'status' => 'P2', 'zone' => 'At FC', 'irigasi' => 30],
        ] as $record) {
            DailyWaterBalance::create([
                'pg' => '01',
                'lokasi' => 'A',
                'tanggal' => $record['tanggal'],
                'irigasi_mm' => $record['irigasi'],
                'water_balance_mm' => 50,
                'status_zone' => $record['zone'],
                'status_harian' => $record['status'],
            ]);
        }

        $this->getJson('/api/monthly-zone-summary?status_harian=P1')
            ->assertOk()
            ->assertJsonCount(2, 'rows')
            ->assertJsonPath('rows.0.total_days', 1)
            ->assertJsonPath('rows.0.percentage_wp', 100)
            ->assertJsonPath('rows.1.pg', 'ALL PG')
            ->assertJsonPath('rows.1.total_days', 1);

        $this->getJson('/api/pg-summary?pg=01&status_harian=P1')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.total_hari', 1)
            ->assertJsonPath('0.count_wp', 1);

        $this->getJson('/api/pg-irrigation-monthly?pg=01&status_harian=P1')
            ->assertOk()
            ->assertJsonPath('report.0.months.2026-08.count', 1);
    }

    public function test_monthly_zone_details_return_matching_records_with_latest_location_area(): void
    {
        foreach ([
            ['pg' => '01', 'lokasi' => 'A', 'tanggal' => '2026-07-01', 'area' => 20, 'zone' => 'At WP', 'status' => 'P1'],
            ['pg' => '01', 'lokasi' => 'A', 'tanggal' => '2026-07-02', 'area' => 15, 'zone' => 'At FC', 'status' => 'P2'],
            ['pg' => '01', 'lokasi' => 'B', 'tanggal' => '2026-07-02', 'area' => 5, 'zone' => 'At WP', 'status' => 'P1'],
            ['pg' => '02', 'lokasi' => 'A', 'tanggal' => '2026-07-01', 'area' => 30, 'zone' => 'At WP', 'status' => 'P1'],
        ] as $record) {
            DailyWaterBalance::create([
                'pg' => $record['pg'],
                'lokasi' => $record['lokasi'],
                'tanggal' => $record['tanggal'],
                'luas_siram_rencana_ha' => $record['area'],
                'water_balance_mm' => 50,
                'status_zone' => $record['zone'],
                'status_harian' => $record['status'],
            ]);
        }

        $this->getJson('/api/monthly-zone-details?month=2026-07&zone=At%20WP&pg=01')
            ->assertOk()
            ->assertJsonCount(2, 'rows')
            ->assertJsonPath('rows.0.tanggal', '2026-07-01')
            ->assertJsonPath('rows.0.lokasi', 'A')
            ->assertJsonPath('rows.0.luas_tanaman_estimasi_ha', 15)
            ->assertJsonPath('rows.0.status_harian', 'P1')
            ->assertJsonPath('rows.1.pg', '01')
            ->assertJsonPath('rows.1.lokasi', 'B')
            ->assertJsonPath('rows.1.luas_tanaman_estimasi_ha', 5);

        $this->getJson('/api/monthly-zone-details?month=2026-07&zone=At%20WP&pg=all&status_harian=P1')
            ->assertOk()
            ->assertJsonCount(3, 'rows');
    }
}
