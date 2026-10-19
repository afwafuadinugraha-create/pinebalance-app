let waterBalanceChartInstance = null;
let statusPieChartInstance = null;
let compareBarChartInstance = null;
let currentLocationRows = [];
let monthlyZoneDetailsRequestId = 0;

const monthlyZoneChartZones = [
    { label: 'WP', status: 'At WP', count: 'count_wp', areaCount: 'area_wp', percentage: 'percentage_wp', areaPercentage: 'area_percentage_wp', className: 'zone-critical', color: '#ef4444' },
    { label: 'MAD 50%-WP', status: 'MAD 50% - WP', count: 'count_mad_wp', areaCount: 'area_mad_wp', percentage: 'percentage_mad_wp', areaPercentage: 'area_percentage_mad_wp', className: 'zone-drying', color: '#eab308' },
    { label: 'FC-MAD 50%', status: 'FC - MAD 50%', count: 'count_fc_mad', areaCount: 'area_fc_mad', percentage: 'percentage_fc_mad', areaPercentage: 'area_percentage_fc_mad', className: 'zone-safe', color: '#3b82f6' },
    { label: 'FC', status: 'At FC', count: 'count_fc', areaCount: 'area_fc', percentage: 'percentage_fc', areaPercentage: 'area_percentage_fc', className: 'zone-full', color: '#22c55e' },
];

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

    renderWilayahAlerts(selectedPG);
}

renderWilayahAlerts();
renderMonthlyZoneSummary();

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
    currentLocationRows = rows;

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

    setupChartFilters(rows);
    applyChartFilters();
    renderPieChart(rows);
    renderRawDataTable(rows);
}

function setupChartFilters(rows) {
    const dateFrom = document.getElementById('chartDateFrom');
    const dateTo = document.getElementById('chartDateTo');
    const priorityFilter = document.getElementById('chartPriorityFilter');
    if (!dateFrom || !dateTo || !priorityFilter) return;

    const dates = rows.map(row => row.tanggal).filter(Boolean).sort();
    dateFrom.min = dates[0] || '';
    dateFrom.max = dates[dates.length - 1] || '';
    dateFrom.value = dates[0] || '';
    dateTo.min = dates[0] || '';
    dateTo.max = dates[dates.length - 1] || '';
    dateTo.value = dates[dates.length - 1] || '';

    const priorities = [...new Set(rows.map(row => row.status_harian).filter(Boolean))].sort((a, b) => a.localeCompare(b));
    priorityFilter.innerHTML = '<option value="">All priorities</option>';
    priorities.forEach(priority => {
        const option = document.createElement('option');
        option.value = priority;
        option.textContent = priority.toLowerCase() === 'bongkar' ? 'Bongkar' : priority;
        priorityFilter.appendChild(option);
    });

    dateFrom.disabled = false;
    dateTo.disabled = false;
    priorityFilter.disabled = false;
}

function applyChartFilters() {
    const dateFrom = document.getElementById('chartDateFrom')?.value || '';
    const dateTo = document.getElementById('chartDateTo')?.value || '';
    const priority = document.getElementById('chartPriorityFilter')?.value || '';
    const filteredRows = currentLocationRows.filter(row => {
        if (dateFrom && row.tanggal < dateFrom) return false;
        if (dateTo && row.tanggal > dateTo) return false;
        if (priority && row.status_harian !== priority) return false;

        return true;
    });

    const summary = document.getElementById('chartFilterSummary');
    if (summary) {
        summary.textContent = `${filteredRows.length} of ${currentLocationRows.length} days shown`;
    }

    const emptyState = document.getElementById('emptyChartState');
    if (filteredRows.length === 0) {
        if (waterBalanceChartInstance) {
            waterBalanceChartInstance.destroy();
            waterBalanceChartInstance = null;
        }
        const canvas = document.getElementById('waterBalanceChart');
        if (canvas) canvas.style.display = 'none';
        if (emptyState) {
            emptyState.style.display = 'flex';
            emptyState.querySelector('p').textContent = 'No data matches the selected date and priority filters.';
        }

        return;
    }

    if (emptyState) {
        emptyState.style.display = 'none';
    }

    renderLineChart(filteredRows);
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
            labels: ['FC', 'FC-MAD 50%', 'MAD 50%-WP', 'WP'],
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
        const locationStatusCell = document.createElement('td');
        locationStatusCell.textContent = r.status_lokasi || '-';
        tr.insertBefore(locationStatusCell, tr.children[2]);
        tbody.appendChild(tr);
    });
}

function renderPGSummaryTable(pg, status = '', statusLokasi = '') {
    const isAllPG = !pg || pg.toLowerCase() === 'all';
    const cleanPG = isAllPG ? 'ALL PG' : pg.toString().replace(/^pg\s*/gi, '').trim();
    const summaryPgBadge = document.getElementById('summaryPgBadge');
    if (summaryPgBadge) summaryPgBadge.innerText = isAllPG ? 'ALL PG' : `PG ${cleanPG}`;

    const params = new URLSearchParams({ pg: isAllPG ? 'all' : pg });
    if (status) params.set('status_harian', status);
    if (statusLokasi) params.set('status_lokasi', statusLokasi);

    fetch(`/api/pg-summary?${params}`)
        .then(response => response.json())
        .then(summaryList => {
            const summaryTbody = document.getElementById('summaryTableBody');
            if (!summaryTbody) return;
            summaryTbody.innerHTML = '';

            if (!Array.isArray(summaryList) || summaryList.length === 0) {
                summaryTbody.innerHTML = `<tr><td colspan="8" style="text-align:center;">No location data for this PG.</td></tr>`;
                return;
            }

            summaryList.forEach((item, index) => {
                const total = parseInt(item.total_hari);
                const getPerc = (val) => ((val / total) * 100).toFixed(1);
                const cleanLokasi = item.lokasi.toString().replace(/^lokasi\s*/gi, '').trim();
                const rowPG = item.pg ? formatMonthlyZonePg(item.pg) : `PG ${cleanPG}`;

                const isCritical = parseInt(item.count_wp) > 0;
                const rowStyle = index === 0 && isCritical ? 'background: #fef2f2;' : '';

                const tr = document.createElement('tr');
                tr.style = rowStyle;
                tr.innerHTML = `
                    <td style="text-align: center; font-weight: 800;">${index + 1}</td>
                    <td style="font-weight: 700;">${rowPG} - Location ${cleanLokasi}</td>
                    <td style="text-align: center;">${item.count_fc} Days (${getPerc(item.count_fc)}%)</td>
                    <td style="text-align: center;">${item.count_fc_mad} Days (${getPerc(item.count_fc_mad)}%)</td>
                    <td style="text-align: center;">${item.count_mad_wp} Days (${getPerc(item.count_mad_wp)}%)</td>
                    <td style="text-align: center; color: #ef4444; font-weight: 700;">${item.count_wp} Days (${getPerc(item.count_wp)}%)</td>
                    <td style="text-align: center; font-weight: 800;">${total} Days</td>
                `;
                const locationStatusCell = document.createElement('td');
                locationStatusCell.textContent = item.status_lokasi || 'Belum diisi';
                tr.insertBefore(locationStatusCell, tr.children[2]);
                summaryTbody.appendChild(tr);
            });

            renderCompareBarChart(summaryList, cleanPG, isAllPG);
        })
        .catch(err => console.error('Error fetching summary:', err));
}

function renderMonthlyZoneSummary() {
    const tbody = document.getElementById('monthlyZoneSummaryBody');
    const selection = document.getElementById('monthlyZonePgSelection');
    if (!tbody || !selection) return;

    const params = new URLSearchParams();
    const status = document.getElementById('monthlyZoneStatusSelection')?.value || '';
    const statusLokasi = document.getElementById('monthlyZoneLocationStatusSelection')?.value || '';
    if (status) params.set('status_harian', status);
    if (statusLokasi) params.set('status_lokasi', statusLokasi);
    const queryString = params.toString();
    const query = queryString ? `?${queryString}` : '';

    fetch(`/api/monthly-zone-summary${query}`)
        .then(response => response.json())
        .then(result => {
            const rows = result.rows || [];
            if (rows.length === 0) {
                renderMonthlyZoneView([], selection.value);
                onAnalyticsPGChange();
                return;
            }

            selection.onchange = () => {
                clearMonthlyZoneDetails();
                renderMonthlyZoneView(rows, selection.value);
                onAnalyticsPGChange();
            };
            renderMonthlyZoneView(rows, selection.value);
            onAnalyticsPGChange();
        })
        .catch(() => {
            renderMonthlyZoneView([], selection.value);
            onAnalyticsPGChange();
        });
}

function onAnalyticsStatusChange() {
    clearMonthlyZoneDetails();
    renderMonthlyZoneSummary();
}

function onAnalyticsPGChange() {
    const selection = document.getElementById('monthlyZonePgSelection');
    if (!selection || !selection.value) return;

    const selectedPG = selection.value === '__all__' ? 'all' : selection.value.replace(/^pg:/, '');
    const selectedStatus = document.getElementById('monthlyZoneStatusSelection')?.value || '';
    const selectedLocationStatus = document.getElementById('monthlyZoneLocationStatusSelection')?.value || '';
    renderPGSummaryTable(selectedPG, selectedStatus, selectedLocationStatus);
    renderPGMonthlyIrrigationTable(selectedPG, selectedStatus, selectedLocationStatus);
}

function renderMonthlyZoneView(rows, selectionValue) {
    const tbody = document.getElementById('monthlyZoneSummaryBody');
    const selectedLabel = document.getElementById('monthlyZoneSelectedPgLabel');
    const selectedRows = selectionValue === '__all__'
        ? rows.filter(row => row.is_all_pg)
        : rows.filter(row => !row.is_all_pg && `pg:${row.pg}` === selectionValue);
    const pgLabel = selectionValue === '__all__'
        ? 'ALL PG'
        : (selectedRows.length > 0 ? formatMonthlyZonePg(selectedRows[0].pg) : '');

    if (selectedLabel) {
        selectedLabel.textContent = pgLabel ? `${pgLabel} · ${selectedRows.length} bulan` : '';
    }

    if (tbody) {
        tbody.replaceChildren();
        if (selectedRows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">Data bulanan belum tersedia untuk PG ini.</td></tr>';
        } else {
            selectedRows.forEach(row => {
                const tr = document.createElement('tr');
                const monthCell = document.createElement('td');
                monthCell.textContent = formatMonthlyZoneMonth(row.month);
                tr.appendChild(monthCell);
                const locationStatusCell = document.createElement('td');
                locationStatusCell.textContent = row.status_lokasi || 'Belum diisi';
                tr.appendChild(locationStatusCell);

                monthlyZoneChartZones.forEach(zone => {
                    const percentage = Number(row[zone.areaPercentage] ?? row[zone.percentage]) || 0;
                    const areaValue = Number(row[zone.areaCount]) || 0;
                    const cell = document.createElement('td');
                    cell.className = `zone-cell ${zone.className}`;
                    cell.style.textAlign = 'center';

                    const value = document.createElement('div');
                    value.className = 'zone-value';
                    value.textContent = `${percentage.toFixed(1)}%`;

                    const count = document.createElement('div');
                    count.className = 'zone-count';
                    count.textContent = `${areaValue.toLocaleString('id-ID', { maximumFractionDigits: 2 })} ha`;

                    const track = document.createElement('div');
                    track.className = 'zone-track';
                    track.setAttribute('role', 'progressbar');
                    track.setAttribute('aria-label', `${zone.label}: ${percentage.toFixed(1)}%`);
                    track.setAttribute('aria-valuemin', '0');
                    track.setAttribute('aria-valuemax', '100');
                    track.setAttribute('aria-valuenow', percentage.toString());

                    const fill = document.createElement('div');
                    fill.className = 'zone-fill';
                    fill.style.width = `${Math.min(100, Math.max(0, percentage))}%`;
                    track.appendChild(fill);

                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'zone-drilldown-button';
                    button.setAttribute('aria-label', `Lihat rincian ${zone.label}, ${formatMonthlyZoneMonth(row.month)}, ${percentage.toFixed(1)} persen`);
                    button.setAttribute('aria-pressed', 'false');
                    button.append(value, count, track);
                    button.addEventListener('click', () => {
                        document.querySelectorAll('.zone-drilldown-button[aria-pressed="true"]').forEach(activeButton => {
                            activeButton.setAttribute('aria-pressed', 'false');
                        });
                        button.setAttribute('aria-pressed', 'true');
                        loadMonthlyZoneDetails(row.month, zone, button, row.status_lokasi);
                    });
                    cell.appendChild(button);
                    tr.appendChild(cell);
                });

                const areaCell = document.createElement('td');
                areaCell.textContent = `${Number(row.total_area_ha || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 })} ha`;
                areaCell.style.textAlign = 'center';
                areaCell.style.fontWeight = '800';
                tr.appendChild(areaCell);

                const locationCountCell = document.createElement('td');
                locationCountCell.textContent = Number(row.location_count || 0).toLocaleString('id-ID');
                locationCountCell.style.textAlign = 'center';
                locationCountCell.style.fontWeight = '800';
                tr.appendChild(locationCountCell);

                tbody.appendChild(tr);
            });
        }
    }

}

function formatMonthlyZonePg(pg) {
    const cleanPg = String(pg).replace(/^pg\s*/i, '').trim();
    const numericPg = Number(cleanPg);

    return `PG ${cleanPg !== '' && Number.isFinite(numericPg) ? numericPg : cleanPg}`;
}

function formatMonthlyZoneMonth(yearMonth) {
    const [year, month] = String(yearMonth).split('-');
    const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    return `${monthNames[Number(month) - 1] || month} ${year}`;
}

function clearMonthlyZoneDetails() {
    monthlyZoneDetailsRequestId++;
    const meta = document.getElementById('monthlyZoneDetailsMeta');
    const tbody = document.getElementById('monthlyZoneDetailsBody');
    if (meta) meta.textContent = '';
    if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Belum ada zona yang dipilih.</td></tr>';
}

function loadMonthlyZoneDetails(month, zone, button, rowStatusLokasi = '') {
    const meta = document.getElementById('monthlyZoneDetailsMeta');
    const tbody = document.getElementById('monthlyZoneDetailsBody');
    const selection = document.getElementById('monthlyZonePgSelection');
    if (!tbody || !selection) return;

    const requestId = ++monthlyZoneDetailsRequestId;
    const selectedPG = selection.value === '__all__' ? 'all' : selection.value.replace(/^pg:/, '');
    const selectedStatus = document.getElementById('monthlyZoneStatusSelection')?.value || '';
    const selectedLocationStatus = document.getElementById('monthlyZoneLocationStatusSelection')?.value || rowStatusLokasi || '__empty__';
    const params = new URLSearchParams({ month, zone: zone.status, pg: selectedPG });
    if (selectedStatus) params.set('status_harian', selectedStatus);
    if (selectedLocationStatus) params.set('status_lokasi', selectedLocationStatus);

    const pgLabel = selectedPG === 'all' ? 'ALL PG' : formatMonthlyZonePg(selectedPG);
    if (meta) meta.textContent = `${pgLabel} · ${formatMonthlyZoneMonth(month)} · ${zone.label}`;
    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Memuat rincian data...</td></tr>';

    fetch(`/api/monthly-zone-details?${params}`)
        .then(response => {
            if (!response.ok) throw new Error('Rincian data tidak dapat dimuat.');

            return response.json();
        })
        .then(result => {
            if (requestId !== monthlyZoneDetailsRequestId || !button.isConnected) return;

            const rows = Array.isArray(result.rows) ? result.rows : [];
            tbody.replaceChildren();
            if (rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Tidak ada catatan untuk zona dan filter yang dipilih.</td></tr>';
                if (meta) meta.textContent += ' · 0 lokasi';

                return;
            }

            rows.forEach(row => {
                const tr = document.createElement('tr');
                const pgCell = document.createElement('td');
                pgCell.textContent = formatMonthlyZonePg(row.pg);
                const locationCell = document.createElement('td');
                locationCell.textContent = row.lokasi || '-';
                const locationStatusCell = document.createElement('td');
                locationStatusCell.textContent = row.status_lokasi || 'Belum diisi';
                const daysCell = document.createElement('td');
                daysCell.textContent = Number(row.total_hari || 0).toLocaleString('id-ID');
                daysCell.style.textAlign = 'center';
                const areaCell = document.createElement('td');
                areaCell.textContent = `${Number(row.luas_tanaman_ha || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 })} ha`;
                areaCell.style.textAlign = 'center';
                const zoneCell = document.createElement('td');
                zoneCell.textContent = row.status_zone || '-';
                const dailyStatusCell = document.createElement('td');
                dailyStatusCell.textContent = row.status_harian || '-';
                tr.append(pgCell, locationCell, locationStatusCell, daysCell, areaCell, zoneCell, dailyStatusCell);
                tbody.appendChild(tr);
            });

            if (meta) meta.textContent += ` · ${rows.length.toLocaleString('id-ID')} lokasi`;
        })
        .catch(() => {
            if (requestId !== monthlyZoneDetailsRequestId || !button.isConnected) return;
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: #b91c1c;">Rincian gagal dimuat. Coba pilih zona lagi.</td></tr>';
        });
}

function renderCompareBarChart(summaryList, cleanPG, isAllPG = false) {
    const emptyState = document.getElementById('emptyCompareChartState');
    if (emptyState) emptyState.style.display = 'none';

    const canvas = document.getElementById('compareBarChart');
    if (!canvas) return;
    canvas.style.display = 'block';

    const labels = summaryList.map(item => {
        const location = item.lokasi.replace(/^lokasi\s*/gi, '').trim();
        const pgLabel = item.pg ? formatMonthlyZonePg(item.pg) : `PG ${cleanPG}`;

        return isAllPG ? `${pgLabel} · ${location}` : `Location ${location}`;
    });
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
                { label: 'FC', data: dataFC, backgroundColor: '#22c55e' },
                { label: 'FC-MAD 50%', data: dataFCMAD, backgroundColor: '#3b82f6' },
                { label: 'MAD 50%-WP', data: dataMADWP, backgroundColor: '#eab308' },
                { label: 'WP', data: dataWP, backgroundColor: '#ef4444' }
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

function renderPGMonthlyIrrigationTable(pg, status = '', statusLokasi = '') {
    const isAllPG = !pg || pg.toLowerCase() === 'all';
    const params = new URLSearchParams({ pg: isAllPG ? 'all' : pg });
    if (status) params.set('status_harian', status);
    if (statusLokasi) params.set('status_lokasi', statusLokasi);

    fetch(`/api/pg-irrigation-monthly?${params}`)
        .then(response => response.json())
        .then(res => {
            const months = res.months || [];
            const report = res.report || [];

            const headerTr = document.getElementById('irrigationMonthlyHeader');
            const tbody = document.getElementById('irrigationMonthlyBody');
            if (!headerTr || !tbody) return;

            let headerHTML = `<th>PG - Location</th>`;
            headerHTML += `<th>Status Lokasi</th>`;
            months.forEach(m => {
                headerHTML += `<th style="text-align: center;">${formatMonthName(m)}</th>`;
            });
            headerHTML += `<th style="text-align: center; color: #0284c7;">Total Irrigation</th>`;
            headerHTML += `<th style="text-align: center; color: #16a34a;">Average / Month</th>`;
            headerTr.innerHTML = headerHTML;

            tbody.innerHTML = '';
            if (report.length === 0) {
                tbody.innerHTML = `<tr><td colspan="${months.length + 4}" style="text-align:center;">No irrigation history for this PG.</td></tr>`;
                return;
            }

            report.forEach(locationSummary => {
                const cleanLokasi = locationSummary.lokasi.toString().replace(/^lokasi\s*/gi, '').trim();
                let rowTotal = 0;

                let rowHTML = `<td style="font-weight: 700;">${formatMonthlyZonePg(locationSummary.pg)} - Location ${cleanLokasi}</td>`;
                rowHTML += '<td></td>';

                months.forEach(m => {
                    const monthlyData = locationSummary.months[m];
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
                tr.children[1].textContent = locationSummary.status_lokasi || 'Belum diisi';
                tbody.appendChild(tr);
            });
        })
        .catch(err => console.error('Error fetching PG monthly irrigation:', err));
}
