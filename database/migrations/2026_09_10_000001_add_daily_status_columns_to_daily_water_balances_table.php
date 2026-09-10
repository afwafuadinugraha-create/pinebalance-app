<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_water_balances', function (Blueprint $table) {
            $table->string('status_harian')->nullable()->after('status_zone');
            $table->text('status_keterangan')->nullable()->after('status_harian');
        });
    }

    public function down(): void
    {
        Schema::table('daily_water_balances', function (Blueprint $table) {
            $table->dropColumn(['status_harian', 'status_keterangan']);
        });
    }
};
