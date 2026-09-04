<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PineBalance - Daily Water Balance Monitoring System</title>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">

    <style>
        body {
            background: linear-gradient(135deg, #e0f2fe 0%, #f0f9ff 50%, #e2e8f0 100%) !important;
            background-attachment: fixed !important;
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
        }

        .sidebar {
            width: 260px;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 100;
            overflow-y: auto;
            padding: 20px 16px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;

            background: linear-gradient(rgba(15, 23, 42, 0.65), rgba(15, 23, 42, 0.80)), 
                        url('{{ asset("images/Produk Unggulan PT GGP-min.jpeg") }}') !important;
            background-size: cover !important;
            background-position: center !important;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.15);
        }

        .sidebar .brand-text h2 { color: #ffffff !important; }
        .sidebar .brand-text p { color: #38bdf8 !important; }

        .sidebar .nav-item {
            color: #e2e8f0 !important;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(4px);
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }

        .sidebar .nav-item:hover {
            background: rgba(255, 255, 255, 0.2) !important;
            color: #ffffff !important;
        }

        .sidebar .nav-item.active {
            background: #0284c7 !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.4);
            border: none;
        }

        .sidebar .filter-card {
            background: rgba(255, 255, 255, 0.94) !important;
            backdrop-filter: blur(8px);
            border-radius: 16px;
            padding: 16px;
            margin-top: auto !important;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .sidebar .filter-header { color: #0f172a !important; font-weight: 800; }
        .sidebar .filter-group label { color: #334155 !important; font-weight: 700; }

        .main-wrapper {
            margin-left: 280px !important;
            width: calc(100% - 300px) !important;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 24px;
            margin-top: 16px;
            margin-bottom: 16px;
            box-sizing: border-box;
        }

        .dashboard-vertical-grid {
            display: flex;
            flex-direction: column;
            gap: 24px;
            margin-top: 20px;
        }

        .full-width-card { width: 100%; }

        .pie-container-flex {
            display: flex;
            align-items: center;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
            padding: 10px 0;
        }

        .pie-chart-box { width: 280px; height: 280px; position: relative; }

        .pie-details-legend {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            flex: 1;
            min-width: 300px;
        }

        .legend-stat-item {
            background: #ffffff;
            padding: 16px 20px;
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            border-left: 5px solid #cbd5e1;
        }

        .legend-stat-item.fc { border-left-color: #22c55e; }
        .legend-stat-item.fc-mad { border-left-color: #3b82f6; }
        .legend-stat-item.mad-wp { border-left-color: #eab308; }
        .legend-stat-item.wp { border-left-color: #ef4444; }

        .legend-stat-item .title {
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .legend-stat-item .value {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 6px;
        }

        .legend-stat-item .percentage {
            font-size: 12px;
            font-weight: 600;
            color: #0284c7;
            margin-top: 2px;
        }

        .upload-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .upload-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #0284c7, #0ea5e9);
            color: #ffffff;
            border: none;
            border-radius: 999px;
            padding: 10px 16px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            box-shadow: 0 10px 18px rgba(2, 132, 199, 0.22);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .upload-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 20px rgba(2, 132, 199, 0.26);
        }

        .upload-status-text {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            background: rgba(148, 163, 184, 0.12);
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 999px;
            padding: 8px 12px;
        }

        .toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 12px;
            pointer-events: none;
        }

        .toast {
            min-width: 280px;
            max-width: 360px;
            pointer-events: auto;
            background: rgba(15, 23, 42, 0.92);
            color: #ffffff;
            border-radius: 14px;
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.22);
            padding: 14px 16px;
            border-left: 5px solid #0284c7;
            opacity: 0;
            transform: translateY(-10px);
            animation: toastIn 0.25s ease forwards;
        }

        .toast.success {
            border-left-color: #22c55e;
        }

        .toast.error {
            border-left-color: #ef4444;
        }

        .toast-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .toast-message {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.82);
            line-height: 1.5;
        }

        @keyframes toastIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1024px) {
            body {
                display: block !important;
                overflow-x: hidden;
            }

            .sidebar {
                position: relative !important;
                width: 100% !important;
                height: auto !important;
                border-right: none !important;
                border-bottom: 1px solid rgba(255, 255, 255, 0.12) !important;
                padding: 18px 16px !important;
            }

            .main-wrapper {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 16px !important;
                border-radius: 18px !important;
            }

            .dashboard-charts-grid {
                grid-template-columns: 1fr !important;
            }

            .kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            .pie-container-flex {
                flex-direction: column !important;
            }
        }

        @media (max-width: 640px) {
            .main-wrapper {
                padding: 12px !important;
            }

            .top-bar {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 12px !important;
                padding: 16px !important;
            }

            .top-bar h1 {
                font-size: 18px !important;
                line-height: 1.3 !important;
            }

            .card {
                padding: 16px !important;
                border-radius: 16px !important;
            }

            .card-header {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 12px !important;
            }

            .kpi-grid {
                grid-template-columns: 1fr !important;
            }

            .kpi-card {
                padding: 14px 12px !important;
            }

            .kpi-value {
                font-size: 18px !important;
            }

            .pie-container-flex {
                gap: 12px !important;
            }

            .pie-chart-box {
                width: min(220px, 100%) !important;
                height: 220px !important;
            }

            .pie-details-legend {
                grid-template-columns: 1fr !important;
                width: 100% !important;
                min-width: 0 !important;
            }

            .nav-menu {
                display: grid !important;
                grid-template-columns: 1fr !important;
            }

            .filter-card {
                margin-top: 16px !important;
            }

            .brand-header {
                align-items: flex-start !important;
            }
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="brand-header">
            <div class="brand-logo-ggp">
                <img src="{{ asset('pineapplelogo.png') }}" alt="Logo PT. Great Giant Pineapple" id="ggpBrandImg" onerror="this.src='https://upload.wikimedia.org/wikipedia/commons/thumb/c/c8/Orange_logo.svg/1200px-Orange_logo.svg.png'">
            </div>
            <div class="brand-text">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <h2>PineBalance</h2>
                    <span class="ggp-logo-badge">GGP</span>
                </div>
                <p>Water Management v2.0</p>
            </div>
        </div>

        <nav class="nav-menu" style="margin-top: 20px;">
            <button class="nav-item active" onclick="switchTab('tab-all-pg', this)">
                <i class="fa-solid fa-globe"></i>
                <span>Overview All PG</span>
            </button>
            <button class="nav-item" onclick="switchTab('tab-dashboard', this)">
                <i class="fa-solid fa-chart-line"></i>
                <span>Dashboard Utama</span>
            </button>
            <button class="nav-item" onclick="switchTab('tab-rawdata', this)">
                <i class="fa-solid fa-database"></i>
                <span>Rincian Data</span>
            </button>
            <button class="nav-item" onclick="switchTab('tab-summary', this)">
                <i class="fa-solid fa-table-columns"></i>
                <span>Summary & Analytics</span>
            </button>
        </nav>

        <div class="filter-card">
            <div class="filter-header">
                <i class="fa-solid fa-sliders"></i>
                <span>Filter Control</span>
            </div>
            
            <div class="filter-group">
                <label for="selectPG">Pilih PG</label>
                <select id="selectPG" onchange="onPGChange()">
                    <option value="">-- Pilih PG --</option>
                    @if(isset($pgList))
                        @foreach($pgList as $item)
                            @php 
                                $cleanItem = trim(preg_replace('/^pg\s*/i', '', $item)); 
                            @endphp
                            <option value="{{ $item }}">PG {{ $cleanItem }}</option>
                        @endforeach
                    @endif
                </select>
            </div>

            <div class="filter-group">
                <label for="selectLokasi">Pilih Lokasi / Blok</label>
                <select id="selectLokasi" onchange="onLokasiChange()" disabled>
                    <option value="">-- Pilih PG Dulu --</option>
                </select>
            </div>
        </div>
    </aside>

    <div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="true"></div>

    <main class="main-wrapper">
        <header class="top-bar">
            <div>
                <h1 style="font-size: 20px; font-weight: 800; color: #0f172a;">Daily Water Balance Monitoring System</h1>
                <p style="font-size: 13px; color: #0284c7; font-weight: 700; margin-top: 2px;">PT. Great Giant Pineapple - Irrigation PPIC</p>
            </div>

            <div class="upload-actions">
                <button type="button" id="exportDataBtn" class="upload-button" style="background: linear-gradient(135deg, #16a34a, #22c55e);">
                    <i class="fa-solid fa-download"></i>
                    Export Data
                </button>
                <input type="file" id="excelFileInput" accept=".xlsx,.xls,.csv" hidden>
                <button type="button" id="triggerUploadBtn" class="upload-button">
                    <i class="fa-solid fa-file-excel"></i>
                    Upload Excel
                </button>
                <div id="uploadStatusText" class="upload-status-text">Belum ada file dipilih</div>
                <div id="fileStatusBadge" class="badge-status-file">
                    <i class="fa-solid fa-database" style="color: #0284c7;"></i> Database Connected
                </div>
            </div>
        </header>

        <!-- TAB 1: OVERVIEW ALL PG -->
        <div id="tab-all-pg" class="tab-page active">
            <section class="card">
                <div class="card-header">
                    <div>
                        <h2 style="font-size: 18px; font-weight: 800; color: #0f172a;"><i class="fa-solid fa-globe" style="color: #0284c7;"></i> Overview Water Balance Seluruh PG</h2>
                        <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">Ringkasan kondisi air untuk menentukan prioritas penanganan antar-PG</p>
                    </div>
                    <span id="allPgStatusBadge" class="badge-status-file"><i class="fa-solid fa-database"></i> Memuat data...</span>
                </div>

                <div class="kpi-grid">
                    <div class="kpi-card kpi-blue">
                        <div class="kpi-icon-box"><i class="fa-solid fa-industry"></i></div>
                        <div><span class="kpi-title">Total PG</span><div class="kpi-value" id="allPgTotal">0</div></div>
                    </div>
                    <div class="kpi-card kpi-green">
                        <div class="kpi-icon-box"><i class="fa-solid fa-location-dot"></i></div>
                        <div><span class="kpi-title">Total Lokasi</span><div class="kpi-value" id="allLokasiTotal">0</div></div>
                    </div>
                    <div class="kpi-card kpi-gold">
                        <div class="kpi-icon-box"><i class="fa-solid fa-calendar-days"></i></div>
                        <div><span class="kpi-title">Total Hari Data</span><div class="kpi-value" id="allHariTotal">0</div></div>
                    </div>
                    <div class="kpi-card kpi-red">
                        <div class="kpi-icon-box"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <div><span class="kpi-title">Hari Kritis (At WP)</span><div class="kpi-value" id="allWpTotal">0</div></div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card-header">
                    <h3><i class="fa-solid fa-ranking-star" style="color: #eab308;"></i> Ranking Kondisi Air Antar-PG</h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 60px; text-align: center;">Rank</th>
                                <th>PG</th>
                                <th style="text-align: center;">Lokasi</th>
                                <th style="text-align: center;">Total Hari</th>
                                <th style="text-align: center;">Air Penuh</th>
                                <th style="text-align: center;">Aman</th>
                                <th style="text-align: center;">Waspada</th>
                                <th style="text-align: center;">Kritis</th>
                            </tr>
                        </thead>
                        <tbody id="allPgSummaryBody">
                            <tr><td colspan="8"><div class="empty-state-box"><i class="fa-solid fa-spinner fa-spin"></i><p>Memuat ringkasan seluruh PG...</p></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card">
                <div class="card-header">
                    <h3><i class="fa-solid fa-chart-bar" style="color: #0284c7;"></i> Perbandingan Status Water Balance Antar-PG</h3>
                </div>
                <div style="height: 360px; position: relative;">
                    <div class="empty-state-box" id="emptyAllPgChartState"><i class="fa-solid fa-chart-simple"></i><p>Belum ada data untuk ditampilkan.</p></div>
                    <canvas id="allPgChart" style="display: none;"></canvas>
                </div>
            </section>
        </div>

        <!-- TAB 2: DASHBOARD UTAMA -->
        <div id="tab-dashboard" class="tab-page">
            <div class="card">
                <div class="card-header">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div class="icon-circle-blue"><i class="fa-solid fa-droplet"></i></div>
                        <div>
                            <h2 style="font-size: 18px; font-weight: 800; color: #0f172a;">Tren & Distribusi Water Balance Harian</h2>
                            <p style="font-size: 13px; color: var(--text-muted);">Visualisasi tren water balance dan proporsi fase air harian</p>
                        </div>
                    </div>
                    <span id="dataRowCountBadge" class="badge-status-file"><i class="fa-regular fa-calendar"></i> 0 Hari</span>
                </div>

                <div class="kpi-grid">
                    <div class="kpi-card kpi-blue">
                        <div class="kpi-icon-box"><i class="fa-solid fa-droplet"></i></div>
                        <div>
                            <span class="kpi-title">Water Balance Terakhir</span>
                            <div class="kpi-value" id="statCurrentWB">- <small>mm</small></div>
                        </div>
                    </div>
                    <div class="kpi-card kpi-green">
                        <div class="kpi-icon-box"><i class="fa-solid fa-leaf"></i></div>
                        <div>
                            <span class="kpi-title">FC (Field Capacity)</span>
                            <div class="kpi-value">105 <small>mm</small></div>
                        </div>
                    </div>
                    <div class="kpi-card kpi-gold">
                        <div class="kpi-icon-box"><i class="fa-solid fa-ellipsis"></i></div>
                        <div>
                            <span class="kpi-title">MAD 50%</span>
                            <div class="kpi-value">80 <small>mm</small></div>
                        </div>
                    </div>
                    <div class="kpi-card kpi-red">
                        <div class="kpi-icon-box"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <div>
                            <span class="kpi-title">WP (Wilting Point)</span>
                            <div class="kpi-value">54 <small>mm</small></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-vertical-grid">
                <section class="card full-width-card" style="margin-bottom: 0;">
                    <div class="card-header">
                        <h3><i class="fa-solid fa-chart-area" style="color: #0284c7;"></i> Grafik Tren Water Balance</h3>
                        <span id="statLokasiBadge" style="font-weight: 700; color: #0284c7; font-size: 13px;">-</span>
                    </div>
                    <div style="height: 380px; position: relative;">
                        <div class="empty-state-box" id="emptyChartState">
                            <i class="fa-solid fa-chart-line"></i>
                            <p>Silakan pilih PG dan Lokasi pada filter control di sebelah kiri.</p>
                        </div>
                        <canvas id="waterBalanceChart" style="display: none;"></canvas>
                    </div>
                </section>

                <section class="card full-width-card" style="margin-bottom: 0;">
                    <div class="card-header">
                        <h3><i class="fa-solid fa-chart-pie" style="color: #22c55e;"></i> Proporsi & Distribusi Kondisi Air Tanah</h3>
                    </div>
                    <div class="pie-container-flex">
                        <div class="pie-chart-box">
                            <div class="empty-state-box" id="emptyPieState">
                                <i class="fa-solid fa-chart-pie"></i>
                                <p>Pilih lokasi terlebih dahulu.</p>
                            </div>
                            <canvas id="statusPieChart" style="display: none;"></canvas>
                        </div>

                        <div class="pie-details-legend">
                            <div class="legend-stat-item fc">
                                <div class="title">🟢 Air Penuh (Field Capacity)</div>
                                <div class="value" id="legFcVal">0 Hari</div>
                                <div class="percentage" id="legFcPerc">0% dari total durasi</div>
                            </div>
                            <div class="legend-stat-item fc-mad">
                                <div class="title">🔵 Kondisi Aman (Optimal)</div>
                                <div class="value" id="legFcMadVal">0 Hari</div>
                                <div class="percentage" id="legFcMadPerc">0% dari total durasi</div>
                            </div>
                            <div class="legend-stat-item mad-wp">
                                <div class="title">🟡 Mulai Kering (Waspada)</div>
                                <div class="value" id="legMadWpVal">0 Hari</div>
                                <div class="percentage" id="legMadWpPerc">0% dari total durasi</div>
                            </div>
                            <div class="legend-stat-item wp">
                                <div class="title">🔴 Sangat Kritis (Titik Layu)</div>
                                <div class="value" id="legWpVal">0 Hari</div>
                                <div class="percentage" id="legWpPerc">0% dari total durasi</div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- TAB 3: RINCIAN DATA -->
        <div id="tab-rawdata" class="tab-page">
            <section class="card">
                <div class="card-header">
                    <h3><i class="fa-solid fa-table-list" style="color: #0284c7;"></i> Rincian Data Water Balance Harian</h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Rainfall (mm)</th>
                                <th>Luas Siram / Total (Ha)</th>
                                <th>Irigasi (mm)</th>
                                <th>Evapotranspirasi (mm/day)</th>
                                <th>Water Balance (mm)</th>
                                <th>Status Zone</th>
                            </tr>
                        </thead>
                        <tbody id="excelTableBody">
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state-box">
                                        <i class="fa-solid fa-folder-open"></i>
                                        <p>Silakan pilih lokasi pada filter control.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- TAB 4: SUMMARY & ANALYTICS -->
        <div id="tab-summary" class="tab-page">
            <section class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="color: #0f172a;"><i class="fa-solid fa-ranking-star" style="color: #eab308;"></i> Rangking Kesehatan Air Seluruh Lokasi</h3>
                    <span id="summaryPgBadge" style="font-weight: 700; color: #0284c7; font-size: 13px;">-</span>
                </div>
                <p style="font-size: 12px; color: #64748b; margin-top: -8px; margin-bottom: 14px;">
                    *Diurutkan berdasarkan lokasi dengan durasi <strong>Sangat Kritis (At WP)</strong> paling banyak untuk prioritas penanganan penyiraman.
                </p>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 60px; text-align: center;">Rank</th>
                                <th>PG - Lokasi</th>
                                <th style="text-align: center;">Air Penuh (Hari / %)</th>
                                <th style="text-align: center;">Kondisi Aman (Hari / %)</th>
                                <th style="text-align: center;">Mulai Kering (Hari / %)</th>
                                <th style="text-align: center;">Sangat Kritis (Hari / %)</th>
                                <th style="text-align: center;">Total Hari</th>
                            </tr>
                        </thead>
                        <tbody id="summaryTableBody">
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state-box">
                                        <i class="fa-solid fa-chart-pie"></i>
                                        <p>Pilih PG pada filter control untuk menampilkan perbandingan seluruh lokasi.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card full-width-card">
                <div class="card-header">
                    <h3 style="color: #0f172a;"><i class="fa-solid fa-chart-bar" style="color: #0284c7;"></i> Perbandingan Visual Kondisi Air Antar Lokasi</h3>
                </div>
                <div style="height: 350px; position: relative;">
                    <div class="empty-state-box" id="emptyCompareChartState">
                        <i class="fa-solid fa-chart-simple"></i>
                        <p>Pilih PG pada filter control untuk menampilkan grafik perbandingan.</p>
                    </div>
                    <canvas id="compareBarChart" style="display: none;"></canvas>
                </div>
            </section>

            <section class="card">
                <div class="card-header">
                    <h3 style="color: #0f172a;"><i class="fa-solid fa-droplet" style="color: #0284c7;"></i> Rekapitulasi Frekuensi Siram Per Bulan</h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr id="irrigationMonthlyHeader">
                                <th>PG - Lokasi</th>
                                <th style="text-align: center; color: #0284c7;">Total Siram</th>
                            </tr>
                        </thead>
                        <tbody id="irrigationMonthlyBody">
                            <tr>
                                <td colspan="5" style="text-align:center;">
                                    <div class="empty-state-box">
                                        <i class="fa-solid fa-droplet-slash"></i>
                                        <p>Pilih PG pada filter control untuk memuat rekapitulasi bulanan seluruh lokasi.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <script src="{{ asset('js/app.js') }}"></script>
    <script>
        const uploadToken = '{{ csrf_token() }}';
        const excelFileInput = document.getElementById('excelFileInput');
        const triggerUploadBtn = document.getElementById('triggerUploadBtn');
        const exportDataBtn = document.getElementById('exportDataBtn');
        const uploadStatusText = document.getElementById('uploadStatusText');
        const toastContainer = document.getElementById('toastContainer');

        function showToast(type, title, message) {
            const toast = document.createElement('div');
            toast.className = 'toast ' + type;
            toast.innerHTML = `
                <div class="toast-title">
                    <i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'}"></i>
                    <span>${title}</span>
                </div>
                <div class="toast-message">${message}</div>
            `;

            toastContainer.appendChild(toast);

            setTimeout(() => {
                toast.remove();
            }, 4000);
        }

        triggerUploadBtn.addEventListener('click', () => {
            excelFileInput.click();
        });

        exportDataBtn.addEventListener('click', () => {
            const selectedPG = document.getElementById('selectPG')?.value || '';
            const exportUrl = selectedPG
                ? '{{ url("/api/water-balance/export") }}?pg=' + encodeURIComponent(selectedPG)
                : '{{ url("/api/water-balance/export") }}';

            window.location.href = exportUrl;
            showToast('success', 'Export data', 'File CSV sedang diunduh.');
        });

        excelFileInput.addEventListener('change', async () => {
            const file = excelFileInput.files[0];

            if (!file) {
                return;
            }

            uploadStatusText.textContent = 'Mengupload ' + file.name + '...';
            uploadStatusText.style.color = '#0f172a';
            uploadStatusText.style.background = 'rgba(14, 165, 233, 0.10)';

            const formData = new FormData();
            formData.append('file', file);

            try {
                const response = await fetch('{{ url("/api/water-balance/import") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': uploadToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                const result = await response.json();

                if (!response.ok || result.success === false) {
                    throw new Error(result.message || 'Import gagal.');
                }

                const summary = result.summary || { created: 0, updated: 0, skipped: 0 };
                const summaryText = 'Baru: ' + summary.created + ', Update: ' + summary.updated + ', Lewat: ' + summary.skipped;
                uploadStatusText.textContent = 'Import berhasil | ' + summaryText;
                uploadStatusText.style.color = '#166534';
                uploadStatusText.style.background = 'rgba(34, 197, 94, 0.10)';
                showToast('success', 'Import berhasil', result.message + ' (' + summaryText + ')');

                setTimeout(() => {
                    if (typeof onPGChange === 'function') {
                        onPGChange();
                    }
                    if (typeof onLokasiChange === 'function') {
                        onLokasiChange();
                    }
                }, 500);
            } catch (error) {
                const errMessage = error.message || 'Import gagal.';
                uploadStatusText.textContent = errMessage;
                uploadStatusText.style.color = '#b91c1c';
                uploadStatusText.style.background = 'rgba(239, 68, 68, 0.10)';
                showToast('error', 'Import gagal', errMessage);
            } finally {
                excelFileInput.value = '';
            }
        });
    </script>
</body>
</html>