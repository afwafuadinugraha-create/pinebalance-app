import { NextResponse } from 'next/server';
import { getSupabaseClient } from '@/lib/supabase';

export async function GET(req: Request) {
  const { searchParams } = new URL(req.url);
  const pg = searchParams.get('pg');
  const lokasi = searchParams.get('lokasi');

  if (!pg || !lokasi) {
    return NextResponse.json([]);
  }

  const supabase = getSupabaseClient();

  if (!supabase) {
    return NextResponse.json({ error: 'Supabase env belum diisi.' }, { status: 500 });
  }

  const { data, error } = await supabase
    .from('daily_water_balances')
    .select('*')
    .eq('pg', pg)
    .eq('lokasi', lokasi)
    .order('tanggal', { ascending: true });

  if (error) {
    return NextResponse.json({ error: error.message }, { status: 500 });
  }

  return NextResponse.json(data || []);
}
