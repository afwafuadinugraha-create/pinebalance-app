const FC = 105;
const WP = 54;

export function normalizeKey(value: string | null | undefined) {
  return String(value ?? '')
    .trim()
    .toLowerCase()
    .replace(/[\s/_\-\(\)]/g, '');
}

export function getValue(row: Record<string, any>, keys: string[]) {
  for (const [rawKey, value] of Object.entries(row)) {
    const normalizedKey = normalizeKey(rawKey);

    for (const key of keys) {
      const normalizedTarget = normalizeKey(key);

      if (normalizedKey === normalizedTarget) {
        return value;
      }

      if (normalizedKey.includes(normalizedTarget) || normalizedTarget.includes(normalizedKey)) {
        return value;
      }
    }
  }

  return null;
}

export function parseAreaValue(value: any) {
  if (value === null || value === undefined || value === '') return 0;

  if (typeof value === 'string') {
    const trimmed = value.trim();
    const match = trimmed.match(/^(.+?)\s*\/\s*(.+)$/);
    if (match) {
      return Number(match[1].trim()) || 0;
    }
  }

  return Number(value) || 0;
}

export function toFloat(value: any) {
  if (value === null || value === undefined || value === '') return 0;

  const parsed = Number(String(value).replace(/,/g, '').replace(/[^0-9.-]/g, ''));
  return Number.isFinite(parsed) ? parsed : 0;
}

export function transformDate(value: any) {
  if (!value) return null;

  if (typeof value === 'number') {
    const date = new Date(Math.round((value - 25569) * 86400 * 1000));
    return date.toISOString().slice(0, 10);
  }

  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return null;

  return parsed.toISOString().slice(0, 10);
}

export function calculateWaterBalance(rows: Record<string, any>[]) {
  const grouped = new Map<string, Record<string, any>[]>();

  for (const row of rows) {
    const rawPg = getValue(row, ['pg', 'PG', 'pabrik_gula']);
    const rawLokasi = getValue(row, ['lokasi', 'Lokasi', 'lokasi_blok', 'blok']);
    const cleanPg = String(rawPg ?? '').trim().replace(/^pg\s*/i, '');
    const cleanLokasi = String(rawLokasi ?? '').trim().replace(/^lokasi\s*/i, '');

    if (!cleanPg || !cleanLokasi) continue;

    const key = `${cleanPg}_${cleanLokasi}`;
    if (!grouped.has(key)) grouped.set(key, []);
    grouped.get(key)!.push(row);
  }

  const result: any[] = [];

  for (const [, groupRows] of grouped.entries()) {
    groupRows.sort((a, b) => {
      const da = transformDate(getValue(a, ['tanggal', 'Tanggal', 'date', 'tgl']));
      const db = transformDate(getValue(b, ['tanggal', 'Tanggal', 'date', 'tgl']));
      return (da ?? '').localeCompare(db ?? '');
    });

    let previousWB = 105;

    for (const row of groupRows) {
      const tanggal = transformDate(getValue(row, ['tanggal', 'Tanggal', 'date', 'tgl']));
      const rawPg = getValue(row, ['pg', 'PG', 'pabrik_gula']);
      const rawLokasi = getValue(row, ['lokasi', 'Lokasi', 'lokasi_blok', 'blok']);

      if (!tanggal || !rawPg || !rawLokasi) continue;

      const pg = String(rawPg).trim().replace(/^pg\s*/i, '');
      const lokasi = String(rawLokasi).trim().replace(/^lokasi\s*/i, '');

      const rainfall = toFloat(getValue(row, ['rainfall_mm', 'rainfall', 'curah_hujan', 'hujan_mm', 'hujan', 'rf_mm', 'rf']));
      const irigasi = toFloat(getValue(row, ['irigasi_mm', 'irigasi', 'siram_mm', 'siram', 'irrigation_mm', 'irrigation']));
      const luasRencana = parseAreaValue(getValue(row, [
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
      const luasReal = parseAreaValue(getValue(row, [
        'luas_siram_real_ha',
        'luas_siram_real',
        'luas_real_ha',
        'luas_real',
        'real_ha',
        'real',
      ]));
      const et = toFloat(getValue(row, [
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

      let irigasiEfektif = 0;
      if (luasRencana > 0 && luasReal > 0) {
        irigasiEfektif = (luasReal / luasRencana) * irigasi;
      } else {
        irigasiEfektif = irigasi;
      }

      let currentWB = previousWB + rainfall + irigasiEfektif - et;

      if (currentWB > FC) currentWB = FC;
      if (currentWB < WP) currentWB = WP;

      let statusZone = 'FC - MAD 50%';
      if (currentWB >= 105) {
        statusZone = 'At FC';
      } else if (currentWB >= 80) {
        statusZone = 'FC - MAD 50%';
      } else if (currentWB > 54) {
        statusZone = 'MAD 50% - WP';
      } else {
        statusZone = 'At WP';
      }

      result.push({
        pg,
        lokasi,
        tanggal,
        rainfall_mm: rainfall,
        luas_siram_rencana_ha: luasRencana,
        luas_siram_real_ha: luasReal,
        irigasi_mm: irigasi,
        irigasi_efektif_mm: irigasiEfektif,
        evapotranspirasi_mm: et,
        water_balance_mm: currentWB,
        status_zone: statusZone,
      });

      previousWB = currentWB;
    }
  }

  return result;
}
