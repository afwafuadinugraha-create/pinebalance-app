<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_water_balances', function (Blueprint $table) {
            $table->string('status_lokasi')->nullable()->after('lokasi');
        });
    }

    public function down(): void
    {
        Schema::table('daily_water_balances', function (Blueprint $table) {
            $table->dropColumn('status_lokasi');
        });
    }
};
