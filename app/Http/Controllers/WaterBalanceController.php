<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DailyWaterBalance;
use Illuminate\Support\Facades\DB;

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
}