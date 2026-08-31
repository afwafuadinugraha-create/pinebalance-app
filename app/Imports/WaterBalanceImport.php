<?php

namespace App\Imports;

use App\Models\DailyWaterBalance;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Carbon\Carbon;

class WaterBalanceImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        // Kelompokkan data berdasarkan PG dan Lokasi yang dibersihkan dari awalan kata "PG" atau "Lokasi"
        $grouped = $rows->groupBy(function ($item) {
            $rawPg = $this->getValue($item, ['pg', 'PG', 'pabrik_gula']);
            $rawLokasi = $this->getValue($item, ['lokasi', 'Lokasi', 'lokasi_blok', 'blok']);
            
            $cleanPg = trim(preg_replace('/^pg\s*/i', '', $rawPg));
            $cleanLokasi = trim(preg_replace('/^lokasi\s*/i', '', $rawLokasi));

            return $cleanPg . '_' . $cleanLokasi;
        });

        foreach ($grouped as $key => $groupRows) {
            // Urutkan data berdasarkan tanggal secara kronologis
            $sortedRows = $groupRows->sortBy(function ($item) {
                return $this->transformDate($this->getValue($item, ['tanggal', 'Tanggal', 'date', 'tgl']));
            });

            // Standar awal neraca air dimulai dari Field Capacity (105.00 mm)
            $previousWB = 105.00;

            foreach ($sortedRows as $row) {
                $tanggal = $this->transformDate($this->getValue($row, ['tanggal', 'Tanggal', 'date', 'tgl']));
                $rawPg = $this->getValue($row, ['pg', 'PG', 'pabrik_gula']);
                $rawLokasi = $this->getValue($row, ['lokasi', 'Lokasi', 'lokasi_blok', 'blok']);

                if (!$tanggal || !$rawPg || !$rawLokasi) continue;

                // Pembersihan nilai agar tersimpan murni tanpa kata "PG" / "Lokasi"
                $pg = trim(preg_replace('/^pg\s*/i', '', $rawPg));
                $lokasi = trim(preg_replace('/^lokasi\s*/i', '', $rawLokasi));

                // 1. Pembacaan Parameter Input
                $rainfall = floatval($this->getValue($row, ['rainfall_mm', 'rainfall', 'curah_hujan', 'hujan_mm', 'hujan', 'rf_mm', 'rf']));
                $irigasi = floatval($this->getValue($row, ['irigasi_mm', 'irigasi', 'siram_mm', 'siram', 'irrigation_mm', 'irrigation']));
                $luasRencana = $this->parseAreaValue($this->getValue($row, [
                    'luas_siram_rencana_netto_ha',
                    'luas_siram_rencana_netto_ha',
                    'luas_siram_rencana_ha',
                    'luas_siram_rencana',
                    'luas_rencana_netto_ha',
                    'luas_rencana_ha',
                    'luas_rencana',
                    'rencana_ha',
                    'rencana_netto_ha',
                ]));
                $luasReal = $this->parseAreaValue($this->getValue($row, [
                    'luas_siram_real_ha',
                    'luas_siram_real',
                    'luas_real_ha',
                    'luas_real',
                    'real_ha',
                    'real',
                ]));
                
                // Evapotranspirasi (ET)
                $evapotranspirasi = floatval($this->getValue($row, [
                    'evapotranspirasi_mm_day', 
                    'evapotranspirasi_mm', 
                    'evapotranspirasi', 
                    'evapotranspirasi_mmday',
                    'evapotranspirasi_day',
                    'et_mm_day',
                    'et_mm', 
                    'et', 
                    'etc', 
                    'eto'
                ]));

                // 2. Kalkulasi Irigasi Efektif
                $irigasiEfektif = 0;
                if ($luasRencana > 0 && $luasReal > 0) {
                    $irigasiEfektif = ($luasReal / $luasRencana) * $irigasi;
                } else {
                    $irigasiEfektif = $irigasi;
                }

                // 3. Kalkulasi Neraca Air Harian
                // WB Hari Ini = WB Hari Sebelumnya + Hujan + Irigasi Efektif - Evapotranspirasi
                $currentWB = $previousWB + $rainfall + $irigasiEfektif - $evapotranspirasi;

                // 4. Penerapan Konsep Batas FC (105 mm) & WP (54 mm)
                if ($currentWB > 105.00) {
                    $currentWB = 105.00;
                }
                if ($currentWB < 54.00) {
                    $currentWB = 54.00;
                }

                // 5. Penentuan Status Zone
                $statusZone = 'FC - MAD 50%';
                if ($currentWB >= 105.00) {
                    $statusZone = 'At FC';
                } elseif ($currentWB >= 80.00) {
                    $statusZone = 'FC - MAD 50%';
                } elseif ($currentWB > 54.00) {
                    $statusZone = 'MAD 50% - WP';
                } else {
                    $statusZone = 'At WP';
                }

                // 6. Simpan atau Perbarui Data di MySQL
                DailyWaterBalance::updateOrCreate(
                    [
                        'pg' => $pg,
                        'lokasi' => $lokasi,
                        'tanggal' => $tanggal,
                    ],
                    [
                        'rainfall_mm' => $rainfall,
                        'luas_siram_rencana_ha' => $luasRencana,
                        'luas_siram_real_ha' => $luasReal,
                        'irigasi_mm' => $irigasi,
                        'irigasi_efektif_mm' => $irigasiEfektif,
                        'evapotranspirasi_mm' => $evapotranspirasi,
                        'water_balance_mm' => $currentWB,
                        'status_zone' => $statusZone,
                    ]
                );

                $previousWB = $currentWB;
            }
        }
    }

    private function getValue($row, array $keys)
    {
        foreach ($row as $rowKey => $val) {
            $cleanKey = $this->normalizeKey($rowKey);
            foreach ($keys as $k) {
                $cleanTarget = $this->normalizeKey($k);

                if ($cleanKey === $cleanTarget) {
                    return $val;
                }

                if (str_contains($cleanKey, $cleanTarget) || str_contains($cleanTarget, $cleanKey)) {
                    return $val;
                }
            }
        }
        return null;
    }

    private function normalizeKey($value)
    {
        if ($value === null) {
            return '';
        }

        return strtolower(trim(preg_replace('/[^a-z0-9]/i', '', (string) $value)));
    }

    private function parseAreaValue($value)
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_string($value)) {
            $value = trim($value);
            if (preg_match('/^(.+?)\s*\/\s*(.+)$/', $value, $matches)) {
                return floatval(trim($matches[1]));
            }
        }

        return floatval($value);
    }

    private function transformDate($value)
    {
        if (empty($value)) return null;

        try {
            if (is_numeric($value)) {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value))->format('Y-m-d');
            }
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}