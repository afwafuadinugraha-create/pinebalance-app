'use client';

import { useEffect, useState } from 'react';
import { LineChart, Line, ResponsiveContainer, XAxis, YAxis, CartesianGrid, Tooltip } from 'recharts';
import UploadPanel from './UploadPanel';

export default function Dashboard() {
  const [rows, setRows] = useState<any[]>([]);
  const [pgs, setPgs] = useState<string[]>([]);
  const [selectedPg, setSelectedPg] = useState('');
  const [selectedLokasi, setSelectedLokasi] = useState('');
  const [lokasiList, setLokasiList] = useState<string[]>([]);

  useEffect(() => {
    fetch('/api/pg')
      .then((res) => res.json())
      .then((data) => {
        const list = Array.isArray(data) ? data : [];
        setPgs(list);
        if (list[0]) setSelectedPg(list[0]);
      })
      .catch(() => setPgs([]));
  }, []);

  useEffect(() => {
    if (!selectedPg) return;

    fetch(`/api/lokasi?pg=${encodeURIComponent(selectedPg)}`)
      .then((res) => res.json())
      .then((data) => {
        const list = Array.isArray(data) ? data : [];
        setLokasiList(list);
        if (list[0]) setSelectedLokasi(list[0]);
      })
      .catch(() => setLokasiList([]));
  }, [selectedPg]);

  useEffect(() => {
    if (!selectedPg || !selectedLokasi) return;

    fetch(`/api/data?pg=${encodeURIComponent(selectedPg)}&lokasi=${encodeURIComponent(selectedLokasi)}`)
      .then((res) => res.json())
      .then((data) => setRows(data || []))
      .catch(() => setRows([]));
  }, [selectedPg, selectedLokasi]);

  const summary = rows.reduce(
    (acc, item) => {
      const key = item.status_zone || 'Unknown';
      acc[key] = (acc[key] || 0) + 1;
      return acc;
    },
    {} as Record<string, number>
  );

  const summaryEntries = Object.entries(summary).map(([label, value]) => [label, Number(value)] as const);

  return (
    <main className="min-h-screen bg-slate-100 p-6">
      <div className="mx-auto max-w-7xl">
        <header className="mb-6 flex items-center justify-between">
          <div>
            <p className="text-sm text-slate-500">PineBalance</p>
            <h1 className="text-3xl font-bold text-slate-800">Water Balance Dashboard</h1>
          </div>
          <label className="rounded border bg-white px-3 py-2 text-sm shadow-sm">
            <input
              type="file"
              accept=".xlsx,.xls,.csv"
              onChange={async (event) => {
                const file = event.target.files?.[0];
                if (!file) return;

                const form = new FormData();
                form.append('file', file);

                const res = await fetch('/api/import', {
                  method: 'POST',
                  body: form,
                });

                const data = await res.json();
                alert(data.success ? `Import berhasil: ${data.total} data` : data.error || 'Import gagal');
                if (data.success) window.location.reload();
              }}
            />
          </label>
        </header>

        <section className="mb-6 grid gap-4 md:grid-cols-3">
          <div className="rounded-xl bg-white p-4 shadow-sm">
            <label className="mb-2 block text-sm font-medium text-slate-600">PG</label>
            <select
              className="w-full rounded border p-2"
              value={selectedPg}
              onChange={(e) => setSelectedPg(e.target.value)}
              disabled={pgs.length === 0}
            >
              {!pgs.length && <option value="">Belum ada PG</option>}
              {pgs.map((pg) => (
                <option key={pg} value={pg}>{pg}</option>
              ))}
            </select>
          </div>

          <div className="rounded-xl bg-white p-4 shadow-sm">
            <label className="mb-2 block text-sm font-medium text-slate-600">Lokasi</label>
            <select
              className="w-full rounded border p-2"
              value={selectedLokasi}
              onChange={(e) => setSelectedLokasi(e.target.value)}
              disabled={lokasiList.length === 0}
            >
              {!lokasiList.length && <option value="">Belum ada lokasi</option>}
              {lokasiList.map((lokasi) => (
                <option key={lokasi} value={lokasi}>{lokasi}</option>
              ))}
            </select>
          </div>

          <div className="rounded-xl bg-white p-4 shadow-sm">
            <p className="text-sm text-slate-600">Total data</p>
            <p className="mt-2 text-3xl font-bold">{rows.length}</p>
          </div>
        </section>

        <div className="mb-6">
          <UploadPanel />
        </div>

        <section className="mb-6 grid gap-4 md:grid-cols-3">
          {summaryEntries.map(([label, value]) => (
            <div key={label} className="rounded-xl bg-white p-4 shadow-sm">
              <p className="text-sm text-slate-500">{label}</p>
              <p className="mt-2 text-2xl font-bold text-slate-800">{value}</p>
            </div>
          ))}
        </section>

        <section className="rounded-xl bg-white p-4 shadow-sm">
          <h2 className="mb-4 text-xl font-semibold">Trend Water Balance</h2>
          <div className="h-80">
            <ResponsiveContainer width="100%" height="100%">
              <LineChart data={rows}>
                <CartesianGrid stroke="#e2e8f0" />
                <XAxis dataKey="tanggal" />
                <YAxis domain={[54, 105]} />
                <Tooltip />
                <Line type="monotone" dataKey="water_balance_mm" stroke="#16a34a" strokeWidth={2} />
              </LineChart>
            </ResponsiveContainer>
          </div>
        </section>
      </div>
    </main>
  );
}
