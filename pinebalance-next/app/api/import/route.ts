import { NextResponse } from 'next/server';
import * as XLSX from 'xlsx';
import { getAdminSupabaseClient } from '@/lib/supabase';
import { calculateWaterBalance } from '@/lib/water-balance';

export async function POST(req: Request) {
  try {
    const supabase = getAdminSupabaseClient();

    if (!supabase) {
      return NextResponse.json({ error: 'Supabase env belum diisi.' }, { status: 500 });
    }

    const formData = await req.formData();
    const file = formData.get('file');

    if (!file || !(file instanceof File)) {
      return NextResponse.json({ error: 'File tidak valid' }, { status: 400 });
    }

    const buffer = Buffer.from(await file.arrayBuffer());
    const workbook = XLSX.read(buffer, { type: 'buffer' });
    const sheetName = workbook.SheetNames[0];
    const sheet = workbook.Sheets[sheetName];
    const rawRows = XLSX.utils.sheet_to_json(sheet, { defval: null });

    const processed = calculateWaterBalance(rawRows as Record<string, any>[]);

    const { error } = await supabase.from('daily_water_balances').upsert(
      processed.map((item) => ({
        pg: item.pg,
        lokasi: item.lokasi,
        tanggal: item.tanggal,
        rainfall_mm: item.rainfall_mm,
        luas_siram_rencana_ha: item.luas_siram_rencana_ha,
        luas_siram_real_ha: item.luas_siram_real_ha,
        irigasi_mm: item.irigasi_mm,
        irigasi_efektif_mm: item.irigasi_efektif_mm,
        evapotranspirasi_mm: item.evapotranspirasi_mm,
        water_balance_mm: item.water_balance_mm,
        status_zone: item.status_zone,
      })),
      { onConflict: 'pg,lokasi,tanggal' }
    );

    if (error) throw error;

    return NextResponse.json({ success: true, total: processed.length });
  } catch (error: any) {
    return NextResponse.json({ error: error.message || 'Import gagal' }, { status: 500 });
  }
}
