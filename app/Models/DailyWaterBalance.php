<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyWaterBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'pg', 'lokasi', 'tanggal', 'rainfall_mm',
        'luas_siram_rencana_ha', 'luas_siram_real_ha',
        'irigasi_mm', 'irigasi_efektif_mm', 'evapotranspirasi_mm',
        'water_balance_mm', 'status_zone'
    ];
}
