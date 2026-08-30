<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_water_balances', function (Blueprint $table) {
            $table->id();
            $table->string('pg');
            $table->string('lokasi');
            $table->date('tanggal');
            $table->decimal('rainfall_mm', 8, 2)->default(0);
            $table->decimal('luas_siram_rencana_ha', 8, 2)->default(0);
            $table->decimal('luas_siram_real_ha', 8, 2)->default(0);
            $table->decimal('irigasi_mm', 8, 2)->default(0);
            $table->decimal('irigasi_efektif_mm', 8, 2)->default(0);
            $table->decimal('evapotranspirasi_mm', 8, 2)->default(0);
            $table->decimal('water_balance_mm', 8, 2);
            $table->string('status_zone');
            $table->timestamps();

            $table->index(['pg', 'lokasi', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_water_balances');
    }
};