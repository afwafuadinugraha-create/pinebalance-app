import { NextResponse } from 'next/server';
import { getSupabaseClient } from '@/lib/supabase';

export async function GET() {
  const supabase = getSupabaseClient();

  if (!supabase) {
    return NextResponse.json({ error: 'Supabase env belum diisi.' }, { status: 500 });
  }

  const { data, error } = await supabase
    .from('daily_water_balances')
    .select('pg')
    .order('pg');

  if (error) {
    return NextResponse.json({ error: error.message }, { status: 500 });
  }

  const unique = [...new Set((data || []).map((item) => item.pg).filter(Boolean))];
  return NextResponse.json(unique);
}
