<?php

namespace App\Imports;

use App\Models\DailyWaterBalance;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class WaterBalanceImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    private array $requiredColumns = [
        'pg' => ['pg', 'pabrik_gula'],
        'lokasi' => ['lokasi', 'lokasi_blok', 'blok'],
        'tanggal' => ['tanggal', 'date', 'tgl'],
        'rainfall_mm' => ['rainfall_mm', 'rainfall', 'curah_hujan', 'hujan_mm', 'hujan', 'rf_mm', 'rf'],
        'irigasi_mm' => ['irigasi_mm', 'irigasi', 'siram_mm', 'siram', 'irrigation_mm', 'irrigation'],
        'evapotranspirasi_mm' => [
            'evapotranspirasi_mm_day', 'evapotranspirasi_mm', 'evapotranspirasi',
            'evapotranspirasi_mmday', 'evapotranspirasi_day', 'et_mm_day', 'et_mm', 'et', 'etc', 'eto',
        ],
    ];

    public function collection(Collection $rows): void
    {
        $headers = [];
        foreach ($rows as $row) {
            if ($row === null || ! is_array($row) && ! ($row instanceof \ArrayAccess)) {
                continue;
            }

            foreach ($row->keys() as $key) {
                $headers[] = $key;
            }

            break;
        }

        $missing = [];
        foreach ($this->requiredColumns as $column => $aliases) {
            if (! $this->hasMatchingHeader($headers, $aliases)) {
                $missing[] = $column;
            }
        }

        if ($missing !== []) {
            $this->updateExistingRows($rows, $headers);

            return;
        }

        $grouped = $rows->groupBy(function ($item) {
            $rawPg = $this->getValue($item, ['pg', 'PG', 'pabrik_gula']);
            $rawLokasi = $this->getValue($item, ['lokasi', 'Lokasi', 'lokasi_blok', 'blok']);

            $cleanPg = $this->normalizePg($rawPg);
            $cleanLokasi = trim(preg_replace('/^lokasi\s*/i', '', $rawLokasi));

            return $cleanPg.'_'.$cleanLokasi;
        });

        foreach ($grouped as $key => $groupRows) {
            $firstRow = $groupRows->first();
            $groupPg = $this->normalizePg($this->getValue($firstRow, ['pg', 'PG', 'pabrik_gula']));
            $groupLokasi = trim(preg_replace('/^lokasi\s*/i', '', $this->getValue($firstRow, ['lokasi', 'Lokasi', 'lokasi_blok', 'blok'])));
            $this->normalizeExistingPgValues($groupPg, $groupLokasi);

            $sortedRows = $groupRows->sortBy(function ($item) {
                return $this->transformDate($this->getValue($item, ['tanggal', 'Tanggal', 'date', 'tgl']));
            });

            $previousWB = 105.00;

            foreach ($sortedRows as $row) {
                $tanggal = $this->transformDate($this->getValue($row, ['tanggal', 'Tanggal', 'date', 'tgl']));
                $rawPg = $this->getValue($row, ['pg', 'PG', 'pabrik_gula']);
                $rawLokasi = $this->getValue($row, ['lokasi', 'Lokasi', 'lokasi_blok', 'blok']);

                if (! $tanggal || ! $rawPg || ! $rawLokasi) {
                    $this->skipped++;

                    continue;
                }

                $pg = $this->normalizePg($rawPg);
                $wilayah = $this->nullableString($this->getExactValue($row, ['wilayah', 'region', 'area'])) ?? '';
                $lokasi = trim(preg_replace('/^lokasi\s*/i', '', $rawLokasi));
                $statusLokasi = $this->nullableString($this->getExactValue($row, [
                    'status_lokasi', 'status lokasi', 'location_status', 'crop_status',
                ]));

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
                $statusHarian = $this->normalizeDailyStatus($this->getExactValue($row, [
                    'status_harian', 'status_operasi', 'prioritas_siram', 'prioritas', 'status', 'bongkar',
                ]));
                $statusKeterangan = $this->nullableString($this->getExactValue($row, [
                    'status_keterangan', 'keterangan_status', 'alasan', 'catatan',
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

                $dailyAttributes = [
                    'wilayah' => $wilayah !== '' ? $wilayah : null,
                    'rainfall_mm' => $rainfall,
                    'luas_siram_rencana_ha' => $luasRencana,
                    'luas_siram_real_ha' => $luasReal,
                    'irigasi_mm' => $irigasi,
                    'irigasi_efektif_mm' => $irigasiEfektif,
                    'evapotranspirasi_mm' => $evapotranspirasi,
                    'water_balance_mm' => $currentWB,
                    'status_zone' => $statusZone,
                    'status_harian' => $statusHarian,
                    'status_keterangan' => $statusKeterangan,
                ];
                if ($statusLokasi !== null) {
                    $dailyAttributes['status_lokasi'] = strtoupper($statusLokasi);
                }

                DailyWaterBalance::updateOrCreate(
                    [
                        'pg' => $pg,
                        'lokasi' => $lokasi,
                        'tanggal' => $tanggal,
                    ],
                    $dailyAttributes
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

    private function normalizeExistingPgValues(string $pg, string $lokasi): void
    {
        $matchingRows = DailyWaterBalance::where('lokasi', $lokasi)
            ->get()
            ->filter(fn (DailyWaterBalance $record) => $this->normalizePg($record->pg) === $pg);

        $duplicateDates = $matchingRows
            ->groupBy(fn (DailyWaterBalance $record) => (string) $record->tanggal)
            ->filter(fn (Collection $records) => $records->count() > 1);

        if ($duplicateDates->isNotEmpty()) {
            throw new \RuntimeException("Cannot normalize PG {$pg} for {$lokasi}: duplicate daily records exist.");
        }

        $legacyIds = $matchingRows
            ->filter(fn (DailyWaterBalance $record) => $record->pg !== $pg)
            ->pluck('id');

        if ($legacyIds->isNotEmpty()) {
            DailyWaterBalance::whereIn('id', $legacyIds)->update(['pg' => $pg]);
        }
    }

    private function normalizePg($value): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return '';
        }

        $text = preg_replace('/^pg\s*[-\s]*/i', '', $text);

        return trim((string) $text, " \t\n\r\0\x0B-");
    }

    private function getValue($row, array $keys)
    {
        foreach ($row as $rowKey => $val) {
            foreach ($keys as $k) {
                if ($this->keysMatch($rowKey, $k)) {
                    return $val;
                }
            }
        }

        return null;
    }

    private function keysMatch($key, $target): bool
    {
        $normalizedKey = $this->normalizeKey($key);
        $normalizedTarget = $this->normalizeKey($target);

        return $normalizedKey === $normalizedTarget
            || str_contains($normalizedKey, $normalizedTarget)
            || str_contains($normalizedTarget, $normalizedKey);
    }

    private function hasMatchingHeader(array $headers, array $aliases): bool
    {
        foreach ($headers as $header) {
            foreach ($aliases as $alias) {
                if ($this->keysMatch($header, $alias)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasExactMatchingHeader(array $headers, array $aliases): bool
    {
        $normalizedAliases = array_map(fn ($alias) => $this->normalizeKey($alias), $aliases);

        foreach ($headers as $header) {
            if (in_array($this->normalizeKey($header), $normalizedAliases, true)) {
                return true;
            }
        }

        return false;
    }

    private function updateExistingRows(Collection $rows, array $headers): void
    {
        $locationAliases = ['lokasi', 'lokasi_blok', 'blok'];
        $pgAliases = ['pg', 'pabrik_gula'];
        $dateAliases = ['tanggal', 'date', 'tgl'];
        $fieldAliases = [
            'wilayah' => ['wilayah', 'region', 'area'],
            'status_lokasi' => ['status_lokasi', 'location_status', 'crop_status'],
            'rainfall_mm' => ['rainfall_mm', 'rainfall', 'curah_hujan', 'hujan_mm', 'hujan', 'rf_mm', 'rf'],
            'luas_siram_rencana_ha' => [
                'luas_siram_rencana_netto_ha', 'luas_siram_rencana_ha', 'luas_siram_rencana',
                'luas_rencana_netto_ha', 'luas_rencana_ha', 'luas_rencana', 'rencana_ha', 'rencana_netto_ha',
            ],
            'luas_siram_real_ha' => ['luas_siram_real_ha', 'luas_siram_real', 'luas_real_ha', 'luas_real', 'real_ha', 'real'],
            'irigasi_mm' => ['irigasi_mm', 'irigasi', 'siram_mm', 'siram', 'irrigation_mm', 'irrigation'],
            'irigasi_efektif_mm' => ['irigasi_efektif_mm', 'effective_irrigation_mm'],
            'evapotranspirasi_mm' => [
                'evapotranspirasi_mm_day', 'evapotranspirasi_mm', 'evapotranspirasi',
                'evapotranspirasi_mmday', 'evapotranspirasi_day', 'et_mm_day', 'et_mm', 'et', 'etc', 'eto',
            ],
            'water_balance_mm' => ['water_balance_mm', 'water_balance'],
            'status_zone' => ['status_zone', 'water_status'],
            'status_harian' => ['status_harian', 'status_operasi', 'prioritas_siram', 'prioritas', 'status', 'bongkar'],
            'status_keterangan' => ['status_keterangan', 'keterangan_status', 'alasan', 'catatan'],
        ];
        $dailyFields = array_diff(array_keys($fieldAliases), ['wilayah', 'status_lokasi']);
        $hasDailyFields = false;
        $matchedUpdateFields = [];

        foreach ($fieldAliases as $field => $aliases) {
            if ($this->hasExactMatchingHeader($headers, $aliases)) {
                $matchedUpdateFields[$field] = $aliases;
                $hasDailyFields = $hasDailyFields || in_array($field, $dailyFields, true);
            }
        }

        if (! $this->hasExactMatchingHeader($headers, $locationAliases) || $matchedUpdateFields === []) {
            throw new \InvalidArgumentException(
                'Partial upload requires a Lokasi column and at least one supported database field to update.'
            );
        }

        if ($hasDailyFields && ! $this->hasExactMatchingHeader($headers, $dateAliases)) {
            throw new \InvalidArgumentException(
                'Partial upload of daily data requires both Lokasi and Tanggal columns. Status Lokasi and Wilayah can be updated without Tanggal.'
            );
        }

        foreach ($rows as $row) {
            $rawLokasi = $this->nullableString($this->getValue($row, $locationAliases));

            if ($rawLokasi === null) {
                $this->skipped++;

                continue;
            }

            $lokasi = trim(preg_replace('/^lokasi\s*/i', '', $rawLokasi));
            if ($lokasi === '') {
                $this->skipped++;

                continue;
            }

            $query = DailyWaterBalance::query()
                ->whereRaw('LOWER(lokasi) = ?', [strtolower($lokasi)]);
            $rawPg = $this->getValue($row, $pgAliases);
            if ($rawPg !== null && trim((string) $rawPg) !== '') {
                $pg = $this->normalizePg($rawPg);
                $query->where(function ($subQuery) use ($pg) {
                    $subQuery->where('pg', $pg)
                        ->orWhere('pg', 'PG '.$pg)
                        ->orWhere('pg', 'PG-'.$pg)
                        ->orWhere('pg', 'PG_'.$pg);
                });
            }

            $rawDate = $this->getValue($row, $dateAliases);
            if ($rawDate !== null && trim((string) $rawDate) !== '') {
                $date = $this->transformDate($rawDate);
                if ($date === null) {
                    $this->skipped++;

                    continue;
                }

                $query->whereDate('tanggal', $date);
            } elseif ($hasDailyFields) {
                $this->skipped++;

                continue;
            }

            $updates = [];
            foreach ($matchedUpdateFields as $field => $aliases) {
                $value = $this->getExactValue($row, $aliases);
                if ($value === null || trim((string) $value) === '') {
                    continue;
                }

                $updates[$field] = match ($field) {
                    'status_lokasi' => strtoupper(trim((string) $value)),
                    'status_harian' => $this->normalizeDailyStatus($value),
                    'status_keterangan', 'wilayah' => $this->nullableString($value),
                    'luas_siram_rencana_ha', 'luas_siram_real_ha' => $this->parseAreaValue($value),
                    'rainfall_mm', 'irigasi_mm', 'irigasi_efektif_mm', 'evapotranspirasi_mm', 'water_balance_mm' => floatval($value),
                    'status_zone' => trim((string) $value),
                    default => $value,
                };
            }

            if ($updates === []) {
                $this->skipped++;

                continue;
            }

            $matchingCount = (clone $query)->count();
            if ($matchingCount > 0) {
                $query->update($updates);
                $this->updated += $matchingCount;
            } else {
                $this->skipped++;
            }
        }
    }

    private function getExactValue($row, array $keys)
    {
        $normalizedKeys = array_map(fn ($key) => $this->normalizeKey($key), $keys);

        foreach ($row as $rowKey => $value) {
            if (in_array($this->normalizeKey($rowKey), $normalizedKeys, true)) {
                return $value;
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

    private function normalizeDailyStatus($value): ?string
    {
        $status = trim((string) ($value ?? ''));

        if ($status === '') {
            return null;
        }

        if (preg_match('/^p\s*(\d+)$/i', $status, $matches)) {
            return 'P'.$matches[1];
        }

        if (preg_match('/^b(ongkar)?$/i', $status)) {
            return 'Bongkar';
        }

        return $status;
    }

    private function nullableString($value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    private function transformDate($value)
    {
        if (empty($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(Date::excelToDateTimeObject($value))->format('Y-m-d');
            }

            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
