create table if not exists daily_water_balances (
  id bigserial primary key,
  pg text not null,
  lokasi text not null,
  tanggal date not null,
  rainfall_mm double precision default 0,
  luas_siram_rencana_ha double precision default 0,
  luas_siram_real_ha double precision default 0,
  irigasi_mm double precision default 0,
  irigasi_efektif_mm double precision default 0,
  evapotranspirasi_mm double precision default 0,
  water_balance_mm double precision default 0,
  status_zone text,
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

create unique index if not exists idx_daily_water_balances_unique
on daily_water_balances (pg, lokasi, tanggal);

create index if not exists idx_daily_water_balances_pg
on daily_water_balances (pg);

create index if not exists idx_daily_water_balances_lokasi
on daily_water_balances (lokasi);

create or replace function set_updated_at()
returns trigger as $$
begin
  new.updated_at = now();
  return new;
end;
$$ language plpgsql;

create trigger daily_water_balances_updated_at
before update on daily_water_balances
for each row
execute function set_updated_at();
