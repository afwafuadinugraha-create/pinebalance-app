<?php

namespace App\Http\Controllers;

use App\Imports\WaterBalanceImport;
use App\Models\DailyWaterBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class WaterBalanceController extends Controller
{
    public function index()
    {
        $pgList = DailyWaterBalance::select('pg')
            ->distinct()
            ->orderBy('pg', 'asc')
            ->pluck('pg');

        $statusList = DailyWaterBalance::query()
            ->whereNotNull('status_harian')
            ->where('status_harian', '<>', '')
            ->select('status_harian')
            ->distinct()
            ->pluck('status_harian')
            ->all();
        usort($statusList, 'strnatcasecmp');

        return view('dashboard', compact('pgList', 'statusList'));
    }

    public function getLokasiByPG(Request $request)
    {
        $pg = $request->query('pg');

        $lokasiList = DailyWaterBalance::where('pg', $pg)
            ->select('lokasi')
            ->distinct()
            ->orderBy('lokasi', 'asc')
            ->pluck('lokasi');

        return response()->json($lokasiList);
    }

    public function getDataByLokasi(Request $request)
    {
        $pg = $request->query('pg');
        $lokasi = $request->query('lokasi');

        $data = DailyWaterBalance::where('pg', $pg)
            ->where('lokasi', $lokasi)
            ->orderBy('tanggal', 'asc')
            ->get();

        return response()->json($data);
    }

    public function getSummaryByPG(Request $request)
    {
        $pg = $request->query('pg');
        $query = DailyWaterBalance::query();

        if ($pg && strtolower($pg) !== 'all') {
            $query->where('pg', $pg);
        }

        if ($request->filled('status_harian')) {
            $query->where('status_harian', $request->query('status_harian'));
        }

        $summary = $query
            ->select(
                'pg',
                'lokasi',
                DB::raw('COUNT(*) as total_hari'),
                DB::raw('SUM(COALESCE(luas_siram_real_ha, 0)) as total_area_ha'),
                DB::raw('COUNT(DISTINCT lokasi) as location_count'),
                DB::raw("SUM(CASE WHEN status_zone = 'At FC' THEN 1 ELSE 0 END) as count_fc"),
                DB::raw("SUM(CASE WHEN status_zone = 'FC - MAD 50%' THEN 1 ELSE 0 END) as count_fc_mad"),
                DB::raw("SUM(CASE WHEN status_zone = 'MAD 50% - WP' THEN 1 ELSE 0 END) as count_mad_wp"),
                DB::raw("SUM(CASE WHEN status_zone = 'At WP' THEN 1 ELSE 0 END) as count_wp")
            )
            ->groupBy('pg', 'lokasi')
            ->orderBy('count_wp', 'desc')
            ->orderBy('count_mad_wp', 'desc')
            ->get();

        return response()->json($summary);
    }

    public function getMonthlyZoneSummary(Request $request): JsonResponse
    {
        $monthExpression = match (DB::connection()->getDriverName()) {
            'pgsql' => "TO_CHAR(tanggal, 'YYYY-MM')",
            'mysql', 'mariadb' => "DATE_FORMAT(tanggal, '%Y-%m')",
            default => "strftime('%Y-%m', tanggal)",
        };

        $query = DailyWaterBalance::query();

        if ($request->filled('status_harian')) {
            $query->where('status_harian', $request->query('status_harian'));
        }

        $latestAreaDates = (clone $query)
            ->select(
                'pg',
                'lokasi',
                DB::raw("{$monthExpression} as month"),
                DB::raw('MAX(tanggal) as latest_date')
            )
            ->groupBy('pg', 'lokasi')
            ->groupByRaw($monthExpression);
        $latestMonthExpression = str_replace('tanggal', 'balances.tanggal', $monthExpression);

        $latestAreas = DB::table('daily_water_balances as balances')
            ->joinSub($latestAreaDates, 'latest', function ($join) use ($latestMonthExpression) {
                $join->on('balances.pg', '=', 'latest.pg')
                    ->on('balances.lokasi', '=', 'latest.lokasi')
                    ->on('balances.tanggal', '=', 'latest.latest_date')
                    ->whereRaw("{$latestMonthExpression} = latest.month");
            })
            ->select('balances.pg', 'balances.lokasi', 'latest.month', 'balances.luas_siram_rencana_ha as area_ha')
            ->get()
            ->groupBy(['pg', 'month']);

        $locationSummaries = $query
            ->select(
                'pg',
                'lokasi',
                DB::raw("{$monthExpression} as month"),
                DB::raw('COUNT(*) as total_days'),
                DB::raw("SUM(CASE WHEN status_zone = 'At WP' THEN 1 ELSE 0 END) as count_wp"),
                DB::raw("SUM(CASE WHEN status_zone = 'MAD 50% - WP' THEN 1 ELSE 0 END) as count_mad_wp"),
                DB::raw("SUM(CASE WHEN status_zone = 'FC - MAD 50%' THEN 1 ELSE 0 END) as count_fc_mad"),
                DB::raw("SUM(CASE WHEN status_zone = 'At FC' THEN 1 ELSE 0 END) as count_fc")
            )
            ->groupBy('pg', 'lokasi')
            ->groupByRaw($monthExpression)
            ->orderBy('pg')
            ->orderBy('month')
            ->orderBy('lokasi')
            ->get();

        $monthlySummary = [];

        foreach ($locationSummaries as $locationSummary) {
            $month = $locationSummary->month;
            $pg = $locationSummary->pg;
            $areaRows = $latestAreas->get($pg, collect())->get($month, collect());
            $latestArea = (float) ($areaRows->firstWhere('lokasi', $locationSummary->lokasi)->area_ha ?? 0);
            $totalDays = (int) $locationSummary->total_days;
            $key = $pg.'|'.$month;

            if (! isset($monthlySummary[$key])) {
                $monthlySummary[$key] = [
                    'pg' => $pg,
                    'month' => $month,
                    'total_days' => 0,
                    'location_count' => 0,
                    'total_area_ha' => 0.0,
                    'count_wp' => 0,
                    'count_mad_wp' => 0,
                    'count_fc_mad' => 0,
                    'count_fc' => 0,
                    'area_wp' => 0.0,
                    'area_mad_wp' => 0.0,
                    'area_fc_mad' => 0.0,
                    'area_fc' => 0.0,
                ];
            }

            $monthlySummary[$key]['total_days'] += $totalDays;
            $monthlySummary[$key]['location_count']++;
            $monthlySummary[$key]['total_area_ha'] += $latestArea;

            foreach ([
                'wp' => 'count_wp',
                'mad_wp' => 'count_mad_wp',
                'fc_mad' => 'count_fc_mad',
                'fc' => 'count_fc',
            ] as $areaKey => $countKey) {
                $zoneDays = (int) $locationSummary->{$countKey};
                $monthlySummary[$key][$countKey] += $zoneDays;
                $monthlySummary[$key]['area_'.$areaKey] += $totalDays > 0 ? $latestArea * $zoneDays / $totalDays : 0;
            }
        }

        $rows = [];
        $allPgByMonth = [];
        $groupedMonthlySummary = collect($monthlySummary)
            ->groupBy('pg')
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($groupedMonthlySummary as $pgSummaries) {
            foreach ($pgSummaries->sortBy('month') as $summary) {
                $month = $summary['month'];
                $counts = $summary;
                unset($counts['pg'], $counts['month']);

                $rows[] = $this->formatMonthlyZoneSummaryRow($month, $summary['pg'], $counts, false);

                foreach ($counts as $key => $count) {
                    $allPgByMonth[$month][$key] = ($allPgByMonth[$month][$key] ?? 0) + $count;
                }
            }
        }

        ksort($allPgByMonth);
        foreach ($allPgByMonth as $month => $counts) {
            $rows[] = $this->formatMonthlyZoneSummaryRow($month, 'ALL PG', $counts, true);
        }

        return response()->json(['rows' => $rows]);
    }

    private function formatMonthlyZoneSummaryRow(string $month, string $pg, array $counts, bool $isAllPg): array
    {
        $totalDays = $counts['total_days'];
        $totalArea = (float) ($counts['total_area_ha'] ?? 0);

        return [
            'month' => $month,
            'pg' => $pg,
            'is_all_pg' => $isAllPg,
            ...$counts,
            'area_location_product' => $totalArea * ($counts['location_count'] ?? 0),
            'percentage_wp' => $totalDays > 0 ? round($counts['count_wp'] / $totalDays * 100, 1) : 0,
            'percentage_mad_wp' => $totalDays > 0 ? round($counts['count_mad_wp'] / $totalDays * 100, 1) : 0,
            'percentage_fc_mad' => $totalDays > 0 ? round($counts['count_fc_mad'] / $totalDays * 100, 1) : 0,
            'percentage_fc' => $totalDays > 0 ? round($counts['count_fc'] / $totalDays * 100, 1) : 0,
            'area_percentage_wp' => $totalArea > 0 ? round(($counts['area_wp'] ?? 0) / $totalArea * 100, 1) : 0,
            'area_percentage_mad_wp' => $totalArea > 0 ? round(($counts['area_mad_wp'] ?? 0) / $totalArea * 100, 1) : 0,
            'area_percentage_fc_mad' => $totalArea > 0 ? round(($counts['area_fc_mad'] ?? 0) / $totalArea * 100, 1) : 0,
            'area_percentage_fc' => $totalArea > 0 ? round(($counts['area_fc'] ?? 0) / $totalArea * 100, 1) : 0,
        ];
    }

    public function getWilayahAlerts(Request $request): JsonResponse
    {
        $query = DailyWaterBalance::query();

        if ($request->filled('pg')) {
            $query->where('pg', $request->query('pg'));
        }

        $alerts = $query
            ->select(
                'pg',
                'wilayah',
                'lokasi',
                DB::raw('COUNT(*) as total_hari'),
                DB::raw("SUM(CASE WHEN status_zone = 'At WP' THEN 1 ELSE 0 END) as total_hari_wp")
            )
            ->groupBy('pg', 'wilayah', 'lokasi')
            ->get()
            ->map(function ($alert) {
                $alert->persentase_wp = round(($alert->total_hari > 0 ? $alert->total_hari_wp / $alert->total_hari : 0) * 100, 1);

                return $alert;
            })
            ->filter(fn ($alert) => $alert->persentase_wp > 20)
            ->sortByDesc('persentase_wp')
            ->take(8)
            ->values();

        return response()->json($alerts);
    }

    public function getMonthlyIrrigationByPG(Request $request)
    {
        $pg = $request->query('pg');
        $isAllPgs = ! $pg || strtolower($pg) === 'all';
        $query = DailyWaterBalance::query();

        if (! $isAllPgs) {
            $query->where('pg', $pg);
        }

        if ($request->filled('status_harian')) {
            $query->where('status_harian', $request->query('status_harian'));
        }

        // Ambil seluruh data tanggal untuk mendeteksi rentang bulan secara menyeluruh (termasuk Mei)
        $allData = $query
            ->select('pg', 'lokasi', 'tanggal', 'irigasi_mm', 'status_harian')
            ->orderBy('pg')
            ->orderBy('lokasi')
            ->orderBy('tanggal')
            ->get();

        $grouped = [];
        $allMonths = [];

        foreach ($allData as $row) {
            $locationKey = $row->pg.'|'.$row->lokasi;
            $monthKey = substr($row->tanggal, 0, 7); // Format "YYYY-MM"

            $allMonths[$monthKey] = true;

            if (! isset($grouped[$locationKey])) {
                $grouped[$locationKey] = [
                    'pg' => $row->pg,
                    'lokasi' => $row->lokasi,
                    'months' => [],
                ];
            }

            if (! isset($grouped[$locationKey]['months'][$monthKey])) {
                $grouped[$locationKey]['months'][$monthKey] = [
                    'count' => 0,
                    'bongkar' => false,
                ];
            }

            if (floatval($row->irigasi_mm) > 0) {
                $grouped[$locationKey]['months'][$monthKey]['count']++;
            }

            if (strtolower((string) $row->status_harian) === 'bongkar') {
                $grouped[$locationKey]['months'][$monthKey]['bongkar'] = true;
            }
        }

        // Urutkan bulan secara kronologis dari awal (May, Jun, Jul, dst.)
        $monthsArray = array_keys($allMonths);
        sort($monthsArray);

        return response()->json([
            'months' => $monthsArray,
            'report' => array_values($grouped),
        ]);
    }

    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        try {
            $importer = new WaterBalanceImport;
            Excel::import($importer, $request->file('file'));

            $summary = $importer->getSummary();

            return response()->json([
                'success' => true,
                'message' => 'Excel data processed. '.$summary['created'].' new, '.$summary['updated'].' updated, '.$summary['skipped'].' skipped.',
                'summary' => $summary,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function downloadTemplate()
    {
        $path = storage_path('app/templates/water-balance-template.csv');

        if (! file_exists($path)) {
            abort(404, 'Import template not found.');
        }

        return response()->download($path, 'water-balance-template.csv');
    }

    public function exportExcel(Request $request)
    {
        $pg = $request->query('pg');

        $records = DailyWaterBalance::query();

        if ($pg) {
            $records->where('pg', $pg);
        }

        $data = $records
            ->orderBy('tanggal', 'asc')
            ->get()
            ->map(function ($row) {
                return [
                    'pg' => $row->pg,
                    'wilayah' => $row->wilayah,
                    'lokasi' => $row->lokasi,
                    'tanggal' => $row->tanggal,
                    'rainfall_mm' => $row->rainfall_mm,
                    'luas_siram_rencana_ha' => $row->luas_siram_rencana_ha,
                    'luas_siram_real_ha' => $row->luas_siram_real_ha,
                    'irigasi_mm' => $row->irigasi_mm,
                    'irigasi_efektif_mm' => $row->irigasi_efektif_mm,
                    'evapotranspirasi_mm' => $row->evapotranspirasi_mm,
                    'water_balance_mm' => $row->water_balance_mm,
                    'status_zone' => $row->status_zone,
                    'status_harian' => $row->status_harian,
                    'status_keterangan' => $row->status_keterangan,
                ];
            })
            ->toArray();

        $filename = $pg ? 'water-balance-'.preg_replace('/[^a-z0-9]+/i', '-', strtolower($pg)).'.csv' : 'water-balance-export.csv';

        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, [
            'pg',
            'wilayah',
            'lokasi',
            'tanggal',
            'rainfall_mm',
            'luas_siram_rencana_ha',
            'luas_siram_real_ha',
            'irigasi_mm',
            'irigasi_efektif_mm',
            'evapotranspirasi_mm',
            'water_balance_mm',
            'status_zone',
            'status_harian',
            'status_keterangan',
        ]);

        foreach ($data as $row) {
            fputcsv($handle, [
                $row['pg'],
                $row['wilayah'],
                $row['lokasi'],
                $row['tanggal'],
                $row['rainfall_mm'],
                $row['luas_siram_rencana_ha'],
                $row['luas_siram_real_ha'],
                $row['irigasi_mm'],
                $row['irigasi_efektif_mm'],
                $row['evapotranspirasi_mm'],
                $row['water_balance_mm'],
                $row['status_zone'],
                $row['status_harian'],
                $row['status_keterangan'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }
}
