import { NextResponse } from 'next/server';
import { getSupabaseClient } from '@/lib/supabase';

export async function GET(req: Request) {
  const { searchParams } = new URL(req.url);
  const pg = searchParams.get('pg');

  if (!pg) return NextResponse.json([]);

  const supabase = getSupabaseClient();

  if (!supabase) {
    return NextResponse.json({ error: 'Supabase env belum diisi.' }, { status: 500 });
  }

  const { data, error } = await supabase
    .from('daily_water_balances')
    .select('lokasi')
    .eq('pg', pg)
    .order('lokasi');

  if (error) {
    return NextResponse.json({ error: error.message }, { status: 500 });
  }

  const unique = [...new Set((data || []).map((item) => item.lokasi).filter(Boolean))];
  return NextResponse.json(unique);
}
