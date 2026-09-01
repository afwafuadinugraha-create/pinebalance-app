<?php

namespace App\Imports;

use App\Models\DailyWaterBalance;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Carbon\Carbon;

class WaterBalanceImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public array $requiredColumns = [
        'pg',
        'lokasi',
        'tanggal',
        'rainfall_mm',
        'irigasi_mm',
        'evapotranspirasi_mm',
    ];

    public function collection(Collection $rows): void
    {
        $headers = [];
        foreach ($rows as $row) {
            if ($row === null || !is_array($row) && !($row instanceof \ArrayAccess)) {
                continue;
            }

            foreach ($row->keys() as $key) {
                $headers[] = $key;
            }

            break;
        }

        $headerSet = array_map(fn ($header) => $this->normalizeKey($header), $headers);
        $missing = [];
        foreach ($this->requiredColumns as $column) {
            if (!in_array($this->normalizeKey($column), $headerSet, true) && !in_array($this->normalizeKey(str_replace('_mm', '', $column)), $headerSet, true)) {
                $missing[] = $column;
            }
        }

        if ($missing !== []) {
            throw new \InvalidArgumentException('Kolom Excel yang dibutuhkan tidak ditemukan: ' . implode(', ', $missing));
        }

        $grouped = $rows->groupBy(function ($item) {
            $rawPg = $this->getValue($item, ['pg', 'PG', 'pabrik_gula']);
            $rawLokasi = $this->getValue($item, ['lokasi', 'Lokasi', 'lokasi_blok', 'blok']);

            $cleanPg = trim(preg_replace('/^pg\s*/i', '', $rawPg));
            $cleanLokasi = trim(preg_replace('/^lokasi\s*/i', '', $rawLokasi));

            return $cleanPg . '_' . $cleanLokasi;
        });

        foreach ($grouped as $key => $groupRows) {
            $sortedRows = $groupRows->sortBy(function ($item) {
                return $this->transformDate($this->getValue($item, ['tanggal', 'Tanggal', 'date', 'tgl']));
            });

            $previousWB = 105.00;

            foreach ($sortedRows as $row) {
                $tanggal = $this->transformDate($this->getValue($row, ['tanggal', 'Tanggal', 'date', 'tgl']));
                $rawPg = $this->getValue($row, ['pg', 'PG', 'pabrik_gula']);
                $rawLokasi = $this->getValue($row, ['lokasi', 'Lokasi', 'lokasi_blok', 'blok']);

                if (!$tanggal || !$rawPg || !$rawLokasi) {
                    $this->skipped++;
                    continue;
                }

                $pg = trim(preg_replace('/^pg\s*/i', '', $rawPg));
                $lokasi = trim(preg_replace('/^lokasi\s*/i', '', $rawLokasi));

                if ($pg === '' || $lokasi === '') {
                    $this->skipped++;
                    continue;
                }

                $rainfall = floatval($this->getValue($row, ['rainfall_mm', 'rainfall', 'curah_hujan', 'hujan_mm', 'hujan', 'rf_mm', 'rf']));
                $irigasi = floatval($this->getValue($row, ['irigasi_mm', 'irigasi', 'siram_mm', 'siram', 'irrigation_mm', 'irrigation']));
                $luasRencana = $this->parseAreaValue($this->getValue($row, [
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
                    'eto',
                ]));

                if ($tanggal === null || $this->getValue($row, ['tanggal', 'Tanggal', 'date', 'tgl']) === null) {
                    $this->skipped++;
                    continue;
                }

                $irigasiEfektif = 0;
                if ($luasRencana > 0 && $luasReal > 0) {
                    $irigasiEfektif = ($luasReal / $luasRencana) * $irigasi;
                } else {
                    $irigasiEfektif = $irigasi;
                }

                $currentWB = $previousWB + $rainfall + $irigasiEfektif - $evapotranspirasi;

                if ($currentWB > 105.00) {
                    $currentWB = 105.00;
                }
                if ($currentWB < 54.00) {
                    $currentWB = 54.00;
                }

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

                $exists = DailyWaterBalance::where('pg', $pg)
                    ->where('lokasi', $lokasi)
                    ->where('tanggal', $tanggal)
                    ->exists();

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

                if ($exists) {
                    $this->updated++;
                } else {
                    $this->created++;
                }

                $previousWB = $currentWB;
            }
        }
    }

    public function getSummary(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
        ];
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