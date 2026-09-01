<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $records = DB::table('daily_water_balances')
            ->select('id', 'pg', 'lokasi', 'tanggal')
            ->orderBy('id')
            ->get();

        $keepIds = [];
        $duplicateIds = [];

        foreach ($records as $record) {
            $key = $record->pg . '|' . $record->lokasi . '|' . $record->tanggal;

            if (isset($keepIds[$key])) {
                $duplicateIds[] = $record->id;
                continue;
            }

            $keepIds[$key] = $record->id;
        }

        if ($duplicateIds !== []) {
            DB::table('daily_water_balances')->whereIn('id', $duplicateIds)->delete();
        }

        Schema::table('daily_water_balances', function (Blueprint $table) {
            $table->unique(['pg', 'lokasi', 'tanggal'], 'daily_water_balances_pg_lokasi_tanggal_unique');
        });
    }

    public function down(): void
    {
        Schema::table('daily_water_balances', function (Blueprint $table) {
            $table->dropUnique('daily_water_balances_pg_lokasi_tanggal_unique');
        });
    }
};
