'use client';

import { useState } from 'react';

export default function UploadPanel() {
  const [status, setStatus] = useState('');
  const [loading, setLoading] = useState(false);

  const handleUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    setLoading(true);
    setStatus('Mengupload dan memproses Excel...');

    try {
      const formData = new FormData();
      formData.append('file', file);

      const response = await fetch('/api/import', {
        method: 'POST',
        body: formData,
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.error || 'Upload gagal');
      }

      setStatus(`Berhasil import ${data.total} data.`);
    } catch (error: any) {
      setStatus(error.message || 'Terjadi error saat import');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="rounded-xl bg-white p-5 shadow-sm">
      <h2 className="mb-3 text-xl font-semibold text-slate-800">Import Excel</h2>
      <label className="flex cursor-pointer items-center justify-center rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600 transition hover:border-blue-500 hover:bg-blue-50">
        <input type="file" accept=".xlsx,.xls,.csv" className="hidden" onChange={handleUpload} />
        {loading ? 'Memproses...' : 'Klik untuk upload file Excel'}
      </label>

      {status && (
        <p className="mt-3 text-sm text-slate-700">{status}</p>
      )}
    </div>
  );
}
