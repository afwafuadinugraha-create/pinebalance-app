<?php

namespace App\Http\Controllers;

use App\Imports\WaterBalanceImport;
use App\Models\DailyWaterBalance;
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

        return view('dashboard', compact('pgList'));
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

        $summary = DailyWaterBalance::where('pg', $pg)
            ->select(
                'lokasi',
                DB::raw("COUNT(*) as total_hari"),
                DB::raw("SUM(CASE WHEN status_zone = 'At FC' THEN 1 ELSE 0 END) as count_fc"),
                DB::raw("SUM(CASE WHEN status_zone = 'FC - MAD 50%' THEN 1 ELSE 0 END) as count_fc_mad"),
                DB::raw("SUM(CASE WHEN status_zone = 'MAD 50% - WP' THEN 1 ELSE 0 END) as count_mad_wp"),
                DB::raw("SUM(CASE WHEN status_zone = 'At WP' THEN 1 ELSE 0 END) as count_wp")
            )
            ->groupBy('lokasi')
            ->orderBy('count_wp', 'desc')
            ->orderBy('count_mad_wp', 'desc')
            ->get();

        return response()->json($summary);
    }

    public function getMonthlyIrrigationByPG(Request $request)
    {
        $pg = $request->query('pg');

        // Ambil seluruh data tanggal untuk mendeteksi rentang bulan secara menyeluruh (termasuk Mei)
        $allData = DailyWaterBalance::where('pg', $pg)
            ->select('lokasi', 'tanggal', 'irigasi_mm')
            ->get();

        $grouped = [];
        $allMonths = [];

        foreach ($allData as $row) {
            $lokasi = $row->lokasi;
            $monthKey = substr($row->tanggal, 0, 7); // Format "YYYY-MM"

            $allMonths[$monthKey] = true;

            if (!isset($grouped[$lokasi])) {
                $grouped[$lokasi] = [];
            }

            if (!isset($grouped[$lokasi][$monthKey])) {
                $grouped[$lokasi][$monthKey] = 0;
            }

            if (floatval($row->irigasi_mm) > 0) {
                $grouped[$lokasi][$monthKey]++;
            }
        }

        // Urutkan bulan secara kronologis dari awal (May, Jun, Jul, dst.)
        $monthsArray = array_keys($allMonths);
        sort($monthsArray);

        return response()->json([
            'months' => $monthsArray,
            'report' => $grouped
        ]);
    }

    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        try {
            $importer = new WaterBalanceImport();
            Excel::import($importer, $request->file('file'));

            $summary = $importer->getSummary();

            return response()->json([
                'success' => true,
                'message' => 'Data Excel berhasil diproses. ' . $summary['created'] . ' data baru, ' . $summary['updated'] . ' data diperbarui, ' . $summary['skipped'] . ' data dilewati.',
                'summary' => $summary,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import data gagal: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function downloadTemplate()
    {
        $path = storage_path('app/templates/water-balance-template.csv');

        if (!file_exists($path)) {
            abort(404, 'Template import tidak ditemukan.');
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
                ];
            })
            ->toArray();

        $filename = $pg ? 'water-balance-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($pg)) . '.csv' : 'water-balance-export.csv';

        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, [
            'pg',
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
        ]);

        foreach ($data as $row) {
            fputcsv($handle, [
                $row['pg'],
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
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}