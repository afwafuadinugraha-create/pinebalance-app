let waterBalanceChartInstance = null;
let statusPieChartInstance = null;
let compareBarChartInstance = null;
let allPgChartInstance = null;

function formatDateCustom(dateString) {
    if (!dateString) return '';
    const dateObj = new Date(dateString);
    if (isNaN(dateObj.getTime())) return dateString;

    const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    const day = String(dateObj.getDate()).padStart(2, '0');
    const month = months[dateObj.getMonth()];
    const year = String(dateObj.getFullYear()).slice(-2);

    return `${day}-${month}-${year}`;
}

function formatMonthName(yearMonthStr) {
    if (!yearMonthStr) return '';
    const parts = yearMonthStr.split('-');
    if (parts.length !== 2) return yearMonthStr;

    const year = parts[0];
    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    const monthIndex = parseInt(parts[1], 10) - 1;
    const monthName = monthNames[monthIndex] || parts[1];

    return `${monthName}-${year}`;
}

function switchTab(tabId, element) {
    document.querySelectorAll('.tab-page').forEach(page => page.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(btn => btn.classList.remove('active'));

    const targetTab = document.getElementById(tabId);
    if (targetTab) targetTab.classList.add('active');
    if (element) element.classList.add('active');
}

function onPGChange() {
    const selectedPG = document.getElementById('selectPG').value;
    const lokasiSelect = document.getElementById('selectLokasi');

    if (!selectedPG) {
        lokasiSelect.disabled = true;
        lokasiSelect.innerHTML = '<option value="">-- Pilih PG Dulu --</option>';
        return;
    }

    lokasiSelect.disabled = true;
    lokasiSelect.innerHTML = '<option value="">-- Memuat Lokasi... --</option>';

    fetch(`/api/lokasi?pg=${encodeURIComponent(selectedPG)}`)
        .then(response => response.json())
        .then(data => {
            lokasiSelect.innerHTML = '<option value="">-- Pilih Lokasi --</option>';
            if (Array.isArray(data)) {
                data.forEach(lokasi => {
                    const opt = document.createElement('option');
                    opt.value = lokasi;
                    const cleanLokasi = lokasi.toString().replace(/^lokasi\s*/gi, '').trim();
                    opt.innerText = `Lokasi ${cleanLokasi}`;
                    lokasiSelect.appendChild(opt);
                });
            }
            lokasiSelect.disabled = false;
        })
        .catch(err => console.error('Error fetching lokasi:', err));

    renderPGSummaryTable(selectedPG);
    renderPGMonthlyIrrigationTable(selectedPG);
}

function onLokasiChange() {
    const selectedPG = document.getElementById('selectPG').value;
    const selectedLokasi = document.getElementById('selectLokasi').value;

    if (!selectedPG || !selectedLokasi) return;

    fetch(`/api/water-balance-data?pg=${encodeURIComponent(selectedPG)}&lokasi=${encodeURIComponent(selectedLokasi)}`)
        .then(response => response.json())
        .then(rows => {
            if (Array.isArray(rows)) {
                renderDashboardForLocation(rows, selectedPG, selectedLokasi);
            }
        })
        .catch(err => console.error('Error fetching data:', err));
}

function renderDashboardForLocation(rows, pg, lokasi) {
    if (!rows || rows.length === 0) return;

    const cleanPG = pg.toString().replace(/^pg\s*/gi, '').trim();
    const cleanLokasi = lokasi.toString().replace(/^lokasi\s*/gi, '').trim();

    const statusBadge = document.getElementById('fileStatusBadge');
    if (statusBadge) {
        statusBadge.innerHTML = `<i class="fa-solid fa-circle-check" style="color: #22c55e;"></i> Data Aktif: PG ${cleanPG} - Lokasi ${cleanLokasi}`;
    }

    const rowBadge = document.getElementById('dataRowCountBadge');
    if (rowBadge) {
        rowBadge.innerHTML = `<i class="fa-regular fa-calendar"></i> ${rows.length} Hari Monitor`;
    }

    const lokBadge = document.getElementById('statLokasiBadge');
    if (lokBadge) {
        lokBadge.innerText = `PG ${cleanPG} - Lokasi ${cleanLokasi}`;
    }

    const lastRow = rows[rows.length - 1];
    const currentWB = parseFloat(lastRow.water_balance_mm).toFixed(2);
    const wbElem = document.getElementById('statCurrentWB');
    if (wbElem) {
        wbElem.innerHTML = `${currentWB} <small>mm</small>`;
    }

    renderLineChart(rows);
    renderPieChart(rows);
    renderRawDataTable(rows);
}

function renderLineChart(rows) {
    const emptyState = document.getElementById('emptyChartState');
    if (emptyState) emptyState.style.display = 'none';

    const canvas = document.getElementById('waterBalanceChart');
    if (!canvas) return;
    canvas.style.display = 'block';

    const labels = rows.map(r => formatDateCustom(r.tanggal));
    const dataWB = rows.map(r => parseFloat(r.water_balance_mm));

    const pointColors = dataWB.map(val => {
        if (val >= 105.0) return '#22c55e';
        if (val >= 80.0) return '#3b82f6';
        if (val > 54.0) return '#eab308';
        return '#ef4444';
    });

    const lineFC = Array(rows.length).fill(105);
    const lineMAD = Array(rows.length).fill(80);
    const lineWP = Array(rows.length).fill(54);

    if (waterBalanceChartInstance) {
        waterBalanceChartInstance.destroy();
    }

    const ctx = canvas.getContext('2d');
    const gradientWater = ctx.createLinearGradient(0, 0, 0, 350);
    gradientWater.addColorStop(0, 'rgba(2, 132, 199, 0.35)');
    gradientWater.addColorStop(0.5, 'rgba(56, 189, 248, 0.15)');
    gradientWater.addColorStop(1, 'rgba(224, 242, 254, 0.02)');

    waterBalanceChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Water Balance (mm)',
                    data: dataWB,
                    borderColor: '#0284c7',
                    backgroundColor: gradientWater,
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.45,
                    pointRadius: 4.5,
                    pointHoverRadius: 8,
                    pointBackgroundColor: pointColors,
                    pointBorderColor: pointColors,
                    pointHoverBackgroundColor: pointColors,
                    pointHoverBorderColor: '#ffffff'
                },
                { label: 'FC (105)', data: lineFC, borderColor: '#22c55e', borderWidth: 1.5, borderDash: [5, 5], pointRadius: 0, fill: false },
                { label: 'MAD 50% (80)', data: lineMAD, borderColor: '#eab308', borderWidth: 1.5, borderDash: [5, 5], pointRadius: 0, fill: false },
                { label: 'WP (54)', data: lineWP, borderColor: '#ef4444', borderWidth: 1.5, borderDash: [5, 5], pointRadius: 0, fill: false }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: true, position: 'top' } },
            scales: {
                y: { title: { display: true, text: 'Water Balance (mm)' }, min: 0, max: 120 },
                x: { ticks: { maxRotation: 45, minRotation: 45 } }
            }
        }
    });
}

function renderPieChart(rows) {
    const emptyState = document.getElementById('emptyPieState');
    if (emptyState) emptyState.style.display = 'none';

    const canvas = document.getElementById('statusPieChart');
    if (!canvas) return;
    canvas.style.display = 'block';

    const totalHari = rows.length;
    let counts = { 'At FC': 0, 'FC - MAD 50%': 0, 'MAD 50% - WP': 0, 'At WP': 0 };

    rows.forEach(r => {
        if (counts[r.status_zone] !== undefined) counts[r.status_zone]++;
    });

    const getPerc = (val) => ((val / totalHari) * 100).toFixed(1);
    const setElemText = (id, txt) => {
        const el = document.getElementById(id);
        if (el) el.innerText = txt;
    };

    setElemText('legFcVal', `${counts['At FC']} Hari`);
    setElemText('legFcPerc', `${getPerc(counts['At FC'])}% dari total durasi`);
    setElemText('legFcMadVal', `${counts['FC - MAD 50%']} Hari`);
    setElemText('legFcMadPerc', `${getPerc(counts['FC - MAD 50%'])}% dari total durasi`);
    setElemText('legMadWpVal', `${counts['MAD 50% - WP']} Hari`);
    setElemText('legMadWpPerc', `${getPerc(counts['MAD 50% - WP'])}% dari total durasi`);
    setElemText('legWpVal', `${counts['At WP']} Hari`);
    setElemText('legWpPerc', `${getPerc(counts['At WP'])}% dari total durasi`);

    if (statusPieChartInstance) {
        statusPieChartInstance.destroy();
    }

    const ctx = canvas.getContext('2d');
    statusPieChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Air Penuh (FC)', 'Kondisi Aman (Optimal)', 'Mulai Kering (Waspada)', 'Sangat Kritis (Titik Layu)'],
            datasets: [{
                data: [counts['At FC'], counts['FC - MAD 50%'], counts['MAD 50% - WP'], counts['At WP']],
                backgroundColor: ['#22c55e', '#3b82f6', '#eab308', '#ef4444']
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });
}

function renderRawDataTable(rows) {
    const tbody = document.getElementById('excelTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    rows.forEach(r => {
        let badgeColor = '#3b82f6';
        if (r.status_zone === 'At FC') badgeColor = '#22c55e';
        if (r.status_zone === 'MAD 50% - WP') badgeColor = '#eab308';
        if (r.status_zone === 'At WP') badgeColor = '#ef4444';

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${formatDateCustom(r.tanggal)}</td>
            <td>${parseFloat(r.rainfall_mm).toFixed(2)}</td>
            <td>${parseFloat(r.luas_siram_real_ha).toFixed(2)} / ${parseFloat(r.luas_siram_rencana_ha).toFixed(2)}</td>
            <td>${parseFloat(r.irigasi_mm).toFixed(2)}</td>
            <td>${parseFloat(r.evapotranspirasi_mm).toFixed(2)}</td>
            <td style="font-weight:700; color: #0f172a;">${parseFloat(r.water_balance_mm).toFixed(2)}</td>
            <td><span style="background:${badgeColor}; color:#fff; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight:700;">${r.status_zone}</span></td>
        `;
        tbody.appendChild(tr);
    });
}

function renderPGSummaryTable(pg) {
    const cleanPG = pg.toString().replace(/^pg\s*/gi, '').trim();
    const summaryPgBadge = document.getElementById('summaryPgBadge');
    if (summaryPgBadge) summaryPgBadge.innerText = `PG ${cleanPG}`;

    fetch(`/api/pg-summary?pg=${encodeURIComponent(pg)}`)
        .then(response => response.json())
        .then(summaryList => {
            const summaryTbody = document.getElementById('summaryTableBody');
            if (!summaryTbody) return;
            summaryTbody.innerHTML = '';

            if (!Array.isArray(summaryList) || summaryList.length === 0) {
                summaryTbody.innerHTML = `<tr><td colspan="7" style="text-align:center;">Tidak ada data lokasi untuk PG ini.</td></tr>`;
                return;
            }

            summaryList.forEach((item, index) => {
                const total = parseInt(item.total_hari);
                const getPerc = (val) => ((val / total) * 100).toFixed(1);
                const cleanLokasi = item.lokasi.toString().replace(/^lokasi\s*/gi, '').trim();

                const isCritical = parseInt(item.count_wp) > 0;
                const rowStyle = index === 0 && isCritical ? 'background: #fef2f2;' : '';

                const tr = document.createElement('tr');
                tr.style = rowStyle;
                tr.innerHTML = `
                    <td style="text-align: center; font-weight: 800;">${index + 1}</td>
                    <td style="font-weight: 700;">PG ${cleanPG} - Lokasi ${cleanLokasi}</td>
                    <td style="text-align: center;">${item.count_fc} Hari (${getPerc(item.count_fc)}%)</td>
                    <td style="text-align: center;">${item.count_fc_mad} Hari (${getPerc(item.count_fc_mad)}%)</td>
                    <td style="text-align: center;">${item.count_mad_wp} Hari (${getPerc(item.count_mad_wp)}%)</td>
                    <td style="text-align: center; color: #ef4444; font-weight: 700;">${item.count_wp} Hari (${getPerc(item.count_wp)}%)</td>
                    <td style="text-align: center; font-weight: 800;">${total} Hari</td>
                `;
                summaryTbody.appendChild(tr);
            });

            renderCompareBarChart(summaryList, cleanPG);
        })
        .catch(err => console.error('Error fetching summary:', err));
}

function renderCompareBarChart(summaryList, cleanPG) {
    const emptyState = document.getElementById('emptyCompareChartState');
    if (emptyState) emptyState.style.display = 'none';

    const canvas = document.getElementById('compareBarChart');
    if (!canvas) return;
    canvas.style.display = 'block';

    const labels = summaryList.map(item => `Lokasi ${item.lokasi.replace(/^lokasi\s*/gi, '').trim()}`);
    const dataFC = summaryList.map(item => parseInt(item.count_fc));
    const dataFCMAD = summaryList.map(item => parseInt(item.count_fc_mad));
    const dataMADWP = summaryList.map(item => parseInt(item.count_mad_wp));
    const dataWP = summaryList.map(item => parseInt(item.count_wp));

    if (compareBarChartInstance) {
        compareBarChartInstance.destroy();
    }

    const ctx = canvas.getContext('2d');
    compareBarChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Air Penuh (FC)', data: dataFC, backgroundColor: '#22c55e' },
                { label: 'Kondisi Aman (Optimal)', data: dataFCMAD, backgroundColor: '#3b82f6' },
                { label: 'Mulai Kering (Waspada)', data: dataMADWP, backgroundColor: '#eab308' },
                { label: 'Sangat Kritis (Titik Layu)', data: dataWP, backgroundColor: '#ef4444' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { stacked: true },
                y: { stacked: true, title: { display: true, text: 'Jumlah Hari' } }
            },
            plugins: { legend: { display: true, position: 'top' } }
        }
    });
}

function renderPGMonthlyIrrigationTable(pg) {
    const cleanPG = pg.toString().replace(/^pg\s*/gi, '').trim();

    fetch(`/api/pg-irrigation-monthly?pg=${encodeURIComponent(pg)}`)
        .then(response => response.json())
        .then(res => {
            const months = res.months || [];
            const report = res.report || {};

            const headerTr = document.getElementById('irrigationMonthlyHeader');
            const tbody = document.getElementById('irrigationMonthlyBody');
            if (!headerTr || !tbody) return;

            let headerHTML = `<th>PG - Lokasi</th>`;
            months.forEach(m => {
                headerHTML += `<th style="text-align: center;">${formatMonthName(m)}</th>`;
            });
            headerHTML += `<th style="text-align: center; color: #0284c7;">Total Siram</th>`;
            headerTr.innerHTML = headerHTML;

            tbody.innerHTML = '';
            const lokasiKeys = Object.keys(report);

            if (lokasiKeys.length === 0) {
                tbody.innerHTML = `<tr><td colspan="${months.length + 2}" style="text-align:center;">Tidak ada riwayat penyiraman di PG ini.</td></tr>`;
                return;
            }

            lokasiKeys.forEach(lokasi => {
                const cleanLokasi = lokasi.toString().replace(/^lokasi\s*/gi, '').trim();
                let rowTotal = 0;

                let rowHTML = `<td style="font-weight: 700;">PG ${cleanPG} - Lokasi ${cleanLokasi}</td>`;

                months.forEach(m => {
                    const count = report[lokasi][m] || 0;
                    rowTotal += count;
                    rowHTML += `<td style="text-align: center;">${count > 0 ? count + ' Kali' : '-'}</td>`;
                });

                rowHTML += `<td style="text-align: center; font-weight: 800; color: #0284c7;">${rowTotal} Kali</td>`;

                const tr = document.createElement('tr');
                tr.innerHTML = rowHTML;
                tbody.appendChild(tr);
            });
        })
        .catch(err => console.error('Error fetching PG monthly irrigation:', err));
}

function renderAllPgSummary() {
    fetch('/api/pg-summary-all')
        .then(response => response.json())
        .then(summaryList => {
            if (!Array.isArray(summaryList)) {
                throw new Error('Format ringkasan tidak valid');
            }

            const totalLokasi = summaryList.reduce((total, item) => total + parseInt(item.total_lokasi || 0), 0);
            const totalWp = summaryList.reduce((total, item) => total + parseInt(item.count_wp || 0), 0);
            const totalLokasiWp = summaryList.reduce((total, item) => total + parseInt(item.lokasi_wp || 0), 0);

            document.getElementById('allPgTotal').textContent = summaryList.length;
            document.getElementById('allLokasiTotal').textContent = totalLokasi;
            document.getElementById('allLokasiWpTotal').textContent = totalLokasiWp;
            document.getElementById('allWpTotal').textContent = totalWp;
            document.getElementById('allPgStatusBadge').innerHTML = '<i class="fa-solid fa-circle-check" style="color: #22c55e;"></i> Data terhubung';

            const tbody = document.getElementById('allPgSummaryBody');
            if (!summaryList.length) {
                tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state-box"><i class="fa-solid fa-folder-open"></i><p>Belum ada data water balance.</p></div></td></tr>';
                return;
            }

            tbody.innerHTML = summaryList.map((item, index) => {
                const cleanPg = item.pg.toString().replace(/^pg\s*/gi, '').trim();
                const criticalStyle = parseInt(item.count_wp || 0) > 0 ? ' style="background: #fef2f2;"' : '';

                return `<tr${criticalStyle}>
                    <td style="text-align: center; font-weight: 800;">${index + 1}</td>
                    <td style="font-weight: 700;">PG ${cleanPg}</td>
                    <td style="text-align: center;">${item.total_lokasi}</td>
                    <td style="text-align: center; color: #15803d; font-weight: 700;">${item.count_fc}</td>
                    <td style="text-align: center; color: #2563eb; font-weight: 700;">${item.count_fc_mad}</td>
                    <td style="text-align: center; color: #a16207; font-weight: 700;">${item.count_mad_wp}</td>
                    <td style="text-align: center; color: #b91c1c; font-weight: 800;">${item.count_wp}</td>
                </tr>`;
            }).join('');

            document.getElementById('allPgWpBody').innerHTML = summaryList.map((item, index) => {
                const cleanPg = item.pg.toString().replace(/^pg\s*/gi, '').trim();
                return `<tr>
                    <td style="text-align: center; font-weight: 800;">${index + 1}</td>
                    <td style="font-weight: 700;">PG ${cleanPg}</td>
                    <td style="text-align: center; color: #b91c1c; font-weight: 800;">${item.lokasi_wp}</td>
                    <td style="text-align: center; color: #b91c1c; font-weight: 800;">${item.count_wp}</td>
                    <td style="text-align: center;">${item.lokasi_wp_terlama ? 'Lokasi ' + item.lokasi_wp_terlama : '-'}</td>
                    <td style="text-align: center; font-weight: 800;">${item.hari_wp_terlama} hari</td>
                </tr>`;
            }).join('');

            renderAllPgChart(summaryList);
            renderAllPgIrrigation();
        })
        .catch(error => {
            document.getElementById('allPgStatusBadge').innerHTML = '<i class="fa-solid fa-circle-exclamation" style="color: #ef4444;"></i> Gagal memuat data';
            console.error('Error fetching all PG summary:', error);
        });
}

function renderAllPgIrrigation() {
    fetch('/api/pg-irrigation-monthly-all')
        .then(response => response.json())
        .then(result => {
            const months = result.months || [];
            const report = result.report || {};
            const header = document.getElementById('allPgIrrigationHeader');
            const tbody = document.getElementById('allPgIrrigationBody');

            header.innerHTML = '<th>PG</th>' + months.map(month => `<th style="text-align: center;">${formatMonthName(month)}</th>`).join('') + '<th style="text-align: center; color: #0284c7;">Total Siram</th>';
            const pgs = Object.keys(report);
            if (!pgs.length) {
                tbody.innerHTML = `<tr><td colspan="${months.length + 2}"><div class="empty-state-box"><i class="fa-solid fa-folder-open"></i><p>Belum ada data frekuensi siram.</p></div></td></tr>`;
                return;
            }

            tbody.innerHTML = pgs.map(pg => {
                let total = 0;
                const cells = months.map(month => {
                    const count = report[pg][month] || 0;
                    total += count;
                    return `<td style="text-align: center;">${count > 0 ? count + ' Kali' : '-'}</td>`;
                }).join('');
                const cleanPg = pg.toString().replace(/^pg\s*/gi, '').trim();
                return `<tr><td style="font-weight: 700;">PG ${cleanPg}</td>${cells}<td style="text-align: center; color: #0284c7; font-weight: 800;">${total} Kali</td></tr>`;
            }).join('');
        })
        .catch(error => console.error('Error fetching all PG irrigation:', error));
}

function renderAllPgChart(summaryList) {
    const canvas = document.getElementById('allPgChart');
    const emptyState = document.getElementById('emptyAllPgChartState');
    if (!canvas || !summaryList.length) return;

    emptyState.style.display = 'none';
    canvas.style.display = 'block';
    if (allPgChartInstance) allPgChartInstance.destroy();

    allPgChartInstance = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: summaryList.map(item => `PG ${item.pg.toString().replace(/^pg\s*/gi, '').trim()}`),
            datasets: [
                { label: 'Air Penuh (FC)', data: summaryList.map(item => parseInt(item.count_fc)), backgroundColor: '#22c55e' },
                { label: 'Kondisi Aman', data: summaryList.map(item => parseInt(item.count_fc_mad)), backgroundColor: '#3b82f6' },
                { label: 'Waspada', data: summaryList.map(item => parseInt(item.count_mad_wp)), backgroundColor: '#eab308' },
                { label: 'Kritis (At WP)', data: summaryList.map(item => parseInt(item.count_wp)), backgroundColor: '#ef4444' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, title: { display: true, text: 'Jumlah Hari' } } },
            plugins: { legend: { display: true, position: 'top' } }
        }
    });
}

document.addEventListener('DOMContentLoaded', renderAllPgSummary);