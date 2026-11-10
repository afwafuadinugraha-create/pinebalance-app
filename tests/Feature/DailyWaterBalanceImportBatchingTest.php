<?php

namespace Tests\Feature;

use App\Imports\WaterBalanceImport;
use App\Models\DailyWaterBalance;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DailyWaterBalanceImportBatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_import_batches_database_writes_and_preserves_summary_and_location_status(): void
    {
        DailyWaterBalance::create([
            'pg' => '01',
            'lokasi' => 'A',
            'tanggal' => '2026-09-01',
            'rainfall_mm' => 1,
            'irigasi_mm' => 0,
            'evapotranspirasi_mm' => 1,
            'water_balance_mm' => 100,
            'status_zone' => 'FC - MAD 50%',
            'status_lokasi' => 'NSFC',
        ]);

        $rows = new Collection;
        $startDate = Carbon::parse('2026-09-01');

        foreach (range(0, 119) as $index) {
            $rows->push(new Collection([
                'pg' => 'PG 01',
                'lokasi' => 'Lokasi A',
                'tanggal' => $startDate->copy()->addDays($index)->toDateString(),
                'rainfall_mm' => 1,
                'irigasi_mm' => 0,
                'evapotranspirasi_mm' => 1,
                'status_lokasi' => $index === 2 ? 'sf' : null,
            ]));
        }

        $queryCount = 0;
        DB::listen(function (QueryExecuted $query) use (&$queryCount): void {
            $queryCount++;
        });

        $importer = new WaterBalanceImport;
        $importer->collection($rows);

        $this->assertSame([
            'created' => 119,
            'updated' => 1,
            'skipped' => 0,
        ], $importer->getSummary());
        $this->assertLessThanOrEqual(6, $queryCount);
        $this->assertDatabaseCount('daily_water_balances', 120);
        $this->assertDatabaseHas('daily_water_balances', [
            'pg' => '01',
            'lokasi' => 'A',
            'tanggal' => '2026-09-01',
            'status_lokasi' => 'NSFC',
        ]);
        $this->assertDatabaseHas('daily_water_balances', [
            'pg' => '01',
            'lokasi' => 'A',
            'tanggal' => '2026-09-03',
            'status_lokasi' => 'SF',
        ]);
    }

    public function test_duplicate_dates_are_upserted_once_and_keep_latest_non_empty_location_status(): void
    {
        $importer = new WaterBalanceImport;
        $importer->collection(new Collection([
            new Collection([
                'pg' => 'PG 01',
                'lokasi' => 'Lokasi A',
                'tanggal' => '2026-09-01',
                'rainfall_mm' => 1,
                'irigasi_mm' => 0,
                'evapotranspirasi_mm' => 1,
                'status_lokasi' => 'sf',
            ]),
            new Collection([
                'pg' => 'PG 01',
                'lokasi' => 'Lokasi A',
                'tanggal' => '2026-09-01',
                'rainfall_mm' => 2,
                'irigasi_mm' => 0,
                'evapotranspirasi_mm' => 1,
                'status_lokasi' => null,
            ]),
        ]));

        $this->assertSame([
            'created' => 1,
            'updated' => 1,
            'skipped' => 0,
        ], $importer->getSummary());
        $this->assertDatabaseCount('daily_water_balances', 1);
        $this->assertDatabaseHas('daily_water_balances', [
            'pg' => '01',
            'lokasi' => 'A',
            'tanggal' => '2026-09-01',
            'rainfall_mm' => '2.00',
            'status_lokasi' => 'SF',
        ]);
    }
}
