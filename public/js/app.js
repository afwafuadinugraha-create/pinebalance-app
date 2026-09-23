let waterBalanceChartInstance = null;
let statusPieChartInstance = null;
let compareBarChartInstance = null;

function renderWilayahAlerts(pg = '') {
    const list = document.getElementById('wilayahAlertsList');
    const countBadge = document.getElementById('wilayahAlertCount');
    if (!list || !countBadge) return;

    const query = pg ? `?pg=${encodeURIComponent(pg)}` : '';
    fetch(`/api/wilayah-alerts${query}`)
        .then(response => response.json())
        .then(alerts => {
            if (!Array.isArray(alerts) || alerts.length === 0) {
                countBadge.innerText = 'Aman';
                countBadge.className = 'alert-count-badge alert-count-safe';
                list.innerHTML = '<div class="alert-empty"><i class="fa-solid fa-circle-check"></i><span>Belum ada lokasi dengan persentase At WP lebih dari 20%.</span></div>';
                return;
            }

            countBadge.innerText = `${alerts.length} Lokasi`;
            list.innerHTML = alerts.map(alert => {
                const wilayah = alert.wilayah || 'Wilayah belum diisi';
                const severity = Number(alert.persentase_wp) >= 50 ? 'critical' : 'warning';
                return `
                    <div class="wilayah-alert-item ${severity}">
                        <div class="wilayah-alert-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <div class="wilayah-alert-content">
                            <strong>PG ${alert.pg} · ${alert.lokasi} · ${wilayah}</strong>
                            <span>${alert.total_hari_wp} dari ${alert.total_hari} hari At WP (${alert.persentase_wp}%)</span>
                        </div>
                        <div class="wilayah-alert-action">${alert.persentase_wp}%</div>
                    </div>
                `;
            }).join('');
        })
        .catch(() => {
            countBadge.innerText = 'Gagal dimuat';
            list.innerHTML = '<div class="alert-empty"><i class="fa-solid fa-circle-exclamation"></i><span>Notifikasi wilayah tidak dapat dimuat.</span></div>';
        });
}

function toggleSidebar() {
    const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
    const toggle = document.getElementById('sidebarToggle');
    if (!toggle) return;

    toggle.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
    toggle.setAttribute('title', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
    toggle.innerHTML = `<i class="fa-solid fa-chevron-${isCollapsed ? 'right' : 'left'}"></i>`;
}

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
        lokasiSelect.innerHTML = '<option value="">-- Select PG First --</option>';
        return;
    }

    lokasiSelect.disabled = true;
    lokasiSelect.innerHTML = '<option value="">-- Loading Locations... --</option>';

    fetch(`/api/lokasi?pg=${encodeURIComponent(selectedPG)}`)
        .then(response => response.json())
        .then(data => {
            lokasiSelect.innerHTML = '<option value="">-- Select Location --</option>';
            if (Array.isArray(data)) {
                data.forEach(lokasi => {
                    const opt = document.createElement('option');
                    opt.value = lokasi;
                    const cleanLokasi = lokasi.toString().replace(/^lokasi\s*/gi, '').trim();
                    opt.innerText = `Location ${cleanLokasi}`;
                    lokasiSelect.appendChild(opt);
                });
            }
            lokasiSelect.disabled = false;
        })
        .catch(err => console.error('Error fetching lokasi:', err));

    renderPGSummaryTable(selectedPG);
    renderPGMonthlyIrrigationTable(selectedPG);
    renderWilayahAlerts(selectedPG);
}

renderWilayahAlerts();

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
        statusBadge.innerHTML = `<i class="fa-solid fa-circle-check" style="color: #22c55e;"></i> Active Data: PG ${cleanPG} - Location ${cleanLokasi}`;
    }

    const rowBadge = document.getElementById('dataRowCountBadge');
    if (rowBadge) {
        rowBadge.innerHTML = `<i class="fa-regular fa-calendar"></i> ${rows.length} Monitoring Days`;
    }

    const lokBadge = document.getElementById('statLokasiBadge');
    if (lokBadge) {
        lokBadge.innerText = `PG ${cleanPG} - Location ${cleanLokasi}`;
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
    const dataRainfall = rows.map(r => parseFloat(r.rainfall_mm) || 0);
    const dataIrrigation = rows.map(r => parseFloat(r.irigasi_mm) || 0);
    const dailyStatuses = rows.map(r => r.status_harian || '');

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

    const statusLabelPlugin = {
        id: 'dailyStatusLabels',
        afterDatasetsDraw(chart) {
            const meta = chart.getDatasetMeta(2);
            const points = meta?.data || [];
            const context = chart.ctx;

            context.save();
            context.font = '700 10px Plus Jakarta Sans';
            context.textAlign = 'center';
            context.textBaseline = 'bottom';

            points.forEach((point, index) => {
                const status = dailyStatuses[index];
                if (!status) return;

                context.fillStyle = status.toLowerCase() === 'bongkar' ? '#b45309' : '#be123c';
                const displayStatus = status.toLowerCase() === 'bongkar' ? 'Dismantled' : status;
                context.fillText(displayStatus, point.x, point.y - 10);
            });

            context.restore();
        }
    };

    waterBalanceChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Rainfall (mm)',
                    data: dataRainfall,
                    backgroundColor: '#38bdf8',
                    borderColor: '#0369a1',
                    borderWidth: 1,
                    maxBarThickness: 14,
                    order: 3
                },
                {
                    type: 'bar',
                    label: 'Irrigation (mm)',
                    data: dataIrrigation,
                    backgroundColor: '#facc15',
                    borderColor: '#a16207',
                    borderWidth: 1,
                    maxBarThickness: 14,
                    order: 2
                },
                {
                    type: 'line',
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
                    pointHoverBorderColor: '#ffffff',
                    order: 1
                },
                { label: 'FC (105)', data: lineFC, borderColor: '#22c55e', borderWidth: 1.5, borderDash: [5, 5], pointRadius: 0, fill: false },
                { label: 'MAD 50% (80)', data: lineMAD, borderColor: '#eab308', borderWidth: 1.5, borderDash: [5, 5], pointRadius: 0, fill: false },
                { label: 'WP (54)', data: lineWP, borderColor: '#ef4444', borderWidth: 1.5, borderDash: [5, 5], pointRadius: 0, fill: false }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, position: 'top' },
                tooltip: {
                    callbacks: {
                        afterBody(items) {
                            const row = rows[items[0]?.dataIndex];
                            if (!row?.status_harian) return [];

                            return [
                                `Status: ${row.status_harian.toLowerCase() === 'bongkar' ? 'Dismantled' : row.status_harian}`,
                                row.status_keterangan ? `Note: ${row.status_keterangan}` : ''
                            ].filter(Boolean);
                        }
                    }
                }
            },
            scales: {
                y: { title: { display: true, text: 'Water Balance (mm)' }, min: 0, max: 120 },
                x: { ticks: { maxRotation: 45, minRotation: 45 } }
            }
        },
        plugins: [statusLabelPlugin]
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

    setElemText('legFcVal', `${counts['At FC']} Days`);
    setElemText('legFcPerc', `${getPerc(counts['At FC'])}% of total`);
    setElemText('legFcMadVal', `${counts['FC - MAD 50%']} Days`);
    setElemText('legFcMadPerc', `${getPerc(counts['FC - MAD 50%'])}% of total`);
    setElemText('legMadWpVal', `${counts['MAD 50% - WP']} Days`);
    setElemText('legMadWpPerc', `${getPerc(counts['MAD 50% - WP'])}% of total`);
    setElemText('legWpVal', `${counts['At WP']} Days`);
    setElemText('legWpPerc', `${getPerc(counts['At WP'])}% of total`);

    if (statusPieChartInstance) {
        statusPieChartInstance.destroy();
    }

    const ctx = canvas.getContext('2d');
    statusPieChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Full (FC)', 'Safe (Optimal)', 'Drying (Warning)', 'Critical (Wilting Point)'],
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
        const dailyStatus = r.status_harian || '-';
        const displayDailyStatus = dailyStatus.toLowerCase() === 'bongkar' ? 'Dismantled' : dailyStatus;
        const dailyStatusColor = dailyStatus.toLowerCase() === 'bongkar' ? '#b45309' : '#be123c';
        tr.innerHTML = `
            <td>${formatDateCustom(r.tanggal)}</td>
            <td>${r.wilayah || '-'}</td>
            <td>${parseFloat(r.rainfall_mm).toFixed(2)}</td>
            <td>${parseFloat(r.luas_siram_real_ha).toFixed(2)} / ${parseFloat(r.luas_siram_rencana_ha).toFixed(2)}</td>
            <td>${parseFloat(r.irigasi_mm).toFixed(2)}</td>
            <td>${parseFloat(r.evapotranspirasi_mm).toFixed(2)}</td>
            <td style="font-weight:700; color: #0f172a;">${parseFloat(r.water_balance_mm).toFixed(2)}</td>
            <td><span style="background:${badgeColor}; color:#fff; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight:700;">${r.status_zone}</span></td>
            <td><span style="background:${dailyStatus === '-' ? '#cbd5e1' : dailyStatusColor}; color:#fff; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight:700;">${displayDailyStatus}</span>${r.status_keterangan ? `<br><small style="color:#64748b;">${r.status_keterangan}</small>` : ''}</td>
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
                summaryTbody.innerHTML = `<tr><td colspan="7" style="text-align:center;">No location data for this PG.</td></tr>`;
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
                    <td style="font-weight: 700;">PG ${cleanPG} - Location ${cleanLokasi}</td>
                    <td style="text-align: center;">${item.count_fc} Days (${getPerc(item.count_fc)}%)</td>
                    <td style="text-align: center;">${item.count_fc_mad} Days (${getPerc(item.count_fc_mad)}%)</td>
                    <td style="text-align: center;">${item.count_mad_wp} Days (${getPerc(item.count_mad_wp)}%)</td>
                    <td style="text-align: center; color: #ef4444; font-weight: 700;">${item.count_wp} Days (${getPerc(item.count_wp)}%)</td>
                    <td style="text-align: center; font-weight: 800;">${total} Days</td>
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

    const labels = summaryList.map(item => `Location ${item.lokasi.replace(/^lokasi\s*/gi, '').trim()}`);
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
                { label: 'Full (FC)', data: dataFC, backgroundColor: '#22c55e' },
                { label: 'Safe (Optimal)', data: dataFCMAD, backgroundColor: '#3b82f6' },
                { label: 'Drying (Warning)', data: dataMADWP, backgroundColor: '#eab308' },
                { label: 'Critical (Wilting Point)', data: dataWP, backgroundColor: '#ef4444' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { stacked: true },
                y: { stacked: true, title: { display: true, text: 'Days' } }
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

            let headerHTML = `<th>PG - Location</th>`;
            months.forEach(m => {
                headerHTML += `<th style="text-align: center;">${formatMonthName(m)}</th>`;
            });
            headerHTML += `<th style="text-align: center; color: #0284c7;">Total Irrigation</th>`;
            headerHTML += `<th style="text-align: center; color: #16a34a;">Average / Month</th>`;
            headerTr.innerHTML = headerHTML;

            tbody.innerHTML = '';
            const lokasiKeys = Object.keys(report);

            if (lokasiKeys.length === 0) {
                tbody.innerHTML = `<tr><td colspan="${months.length + 3}" style="text-align:center;">No irrigation history for this PG.</td></tr>`;
                return;
            }

            lokasiKeys.forEach(lokasi => {
                const cleanLokasi = lokasi.toString().replace(/^lokasi\s*/gi, '').trim();
                let rowTotal = 0;

                let rowHTML = `<td style="font-weight: 700;">PG ${cleanPG} - Location ${cleanLokasi}</td>`;

                months.forEach(m => {
                    const monthlyData = report[lokasi][m];
                    const hasData = monthlyData !== undefined;
                    const count = monthlyData?.count || 0;
                    rowTotal += count;
                    const displayValue = count > 0
                        ? `${count} Times`
                        : !hasData || monthlyData.bongkar ? '-' : '0 Times';
                    rowHTML += `<td style="text-align: center;">${displayValue}</td>`;
                });

                rowHTML += `<td style="text-align: center; font-weight: 800; color: #0284c7;">${rowTotal} Times</td>`;
                const averagePerMonth = months.length > 0 ? (rowTotal / months.length).toFixed(2) : '0.00';
                rowHTML += `<td style="text-align: center; font-weight: 800; color: #16a34a;">${averagePerMonth} Times</td>`;

                const tr = document.createElement('tr');
                tr.innerHTML = rowHTML;
                tbody.appendChild(tr);
            });
        })
        .catch(err => console.error('Error fetching PG monthly irrigation:', err));
}
