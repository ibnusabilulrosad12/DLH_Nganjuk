<?php
session_start();
// Proteksi: hanya admin yang sudah login
// if (!isset($_SESSION['admin_id'])) {
//     header("Location: ../login/login.php");
//     exit;
// }

// Load koneksi database
require_once '../KoneksiDatabase/koneksi.php';

// Daftar nama bulan
$bulan_list = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
];

// Ambil input filter
$selected_tahun = $_GET['tahun'] ?? date('Y');
$selected_bulan = $_GET['bulan'] ?? date('n');

// Validasi input
$selected_tahun = (int)$selected_tahun;
$selected_bulan = (int)$selected_bulan;

if ($selected_bulan < 1 || $selected_bulan > 12) $selected_bulan = (int)date('n');
if ($selected_tahun < 2000 || $selected_tahun > (int)date('Y') + 1) $selected_tahun = (int)date('Y');

// Buat WHERE clause berdasarkan tahun & bulan
$where = "WHERE (
    (tanggal IS NOT NULL AND YEAR(tanggal) = $selected_tahun AND MONTH(tanggal) = $selected_bulan)
    OR
    (tanggal IS NULL AND YEAR(created_at) = $selected_tahun AND MONTH(created_at) = $selected_bulan)
)";

// Ambil total laporan
$stmt = $pdo->query("SELECT COUNT(*) FROM laporan $where");
$total = $stmt->fetchColumn();

// Ambil jumlah per status
$stmt = $pdo->prepare("SELECT status, COUNT(*) as total FROM laporan $where GROUP BY status");
$stmt->execute();
$status_counts = [];
while ($row = $stmt->fetch()) {
    $status_counts[$row['status']] = $row['total'];
}

$diproses = $status_counts['Diproses'] ?? 0;
$diterima = $status_counts['Diterima'] ?? 0;
$ditolak = $status_counts['Ditolak'] ?? 0;

$belum_diproses = $diproses;
$selesai_diproses = $diterima;

// === PERBAIKAN: GANTI 7 QUERY JADI 1 QUERY ===
$dates_range = [];
$date_labels = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates_range[] = $date;
    $date_labels[] = date('D', strtotime($date));
}

$placeholders = str_repeat('?,', count($dates_range) - 1) . '?';
$stmt = $pdo->prepare("
    SELECT DATE(COALESCE(tanggal, created_at)) as report_date, COUNT(*) as total
    FROM laporan
    WHERE DATE(COALESCE(tanggal, created_at)) IN ($placeholders)
    GROUP BY report_date
");
$stmt->execute($dates_range);

$result_map = [];
while ($row = $stmt->fetch()) {
    $result_map[$row['report_date']] = (int)$row['total'];
}

$counts = [];
foreach ($dates_range as $date) {
    $counts[] = $result_map[$date] ?? 0;
}
$dates = $date_labels;
// === AKHIR PERBAIKAN ===

// Laporan terbaru (4 terakhir)
$stmt = $pdo->query("
    SELECT lokasi AS alamat, status, created_at 
    FROM laporan 
    ORDER BY created_at DESC 
    LIMIT 4
");
$recent_reports = $stmt->fetchAll();

// Ambil daftar tahun unik dari database untuk dropdown
$stmt = $pdo->query("SELECT DISTINCT YEAR(COALESCE(tanggal, created_at)) as tahun FROM laporan ORDER BY tahun DESC");
$tahun_options = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($tahun_options)) {
    $tahun_options = [date('Y')];
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - SIMPELSI</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            display: flex;
            min-height: 100vh;
        }

        /* Fade-in saat halaman load */
        body.fade-in .main-content {
            opacity: 0;
            transition: opacity 0.3s ease-out;
        }

        body.fade-in-ready .main-content {
            opacity: 1;
        }

        /* Header */
        .header {
            width: 100%;
            background: #2e8b57;
            color: white;
            padding: 12px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-logo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #2e8b57;
        }

        .header-exit {
            background: white;
            color: #2e8b57;
            padding: 6px 12px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }

        .header-exit:hover {
            background: #e6ffe6;
            transform: scale(1.05);
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: #e6e6e6;
            position: fixed;
            top: 60px;
            left: 0;
            bottom: 0;
            padding: 20px 0;
            overflow-y: auto;
            box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
            z-index: 999;
            display: flex;
            flex-direction: column;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 20px;
            margin: 0;
            flex: 1;
        }

        .menu-item {
            padding: 14px 20px;
            margin-bottom: 8px;
            background: white;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #333;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .menu-item:hover {
            background: #f0f0f0;
            transform: translateX(4px);
        }

        .menu-item.active {
            background: #2e8b57;
            color: white;
            border: none;
            box-shadow: 0 2px 6px rgba(46, 139, 87, 0.3);
        }

        .menu-icon {
            width: 32px;
            height: 32px;
            background: #2e8b57;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .menu-item.active .menu-icon {
            background: white;
            color: #2e8b57;
        }

        /* Main Content dengan animasi fade */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 80px 30px 40px;
            background: #f9f9f9;
            min-height: 100vh;
            opacity: 1;
            transition: opacity 0.25s ease-in-out;
        }

        .main-content.fade-out {
            opacity: 0;
        }

        /* Sisa CSS tetap sama... */
        .content-header {
            margin-bottom: 20px;
        }

        .content-header h2 {
            color: #2e8b57;
            font-size: 24px;
        }

        .stats-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .stats-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }

        .stats-header h3 {
            color: #2e8b57;
            font-size: 18px;
        }

        .filter-controls {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-controls label {
            font-size: 12px;
            color: #2e8b57;
            margin-bottom: 2px;
        }

        .filter-controls select,
        .filter-controls button,
        .filter-controls a {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            border: 1px solid #ccc;
        }

        .filter-controls button {
            background: #2e8b57;
            color: white;
            border: none;
            cursor: pointer;
        }

        .filter-controls button:hover {
            background: #226b43;
        }

        .filter-controls a {
            background: #e6e6e6;
            color: #333;
            text-decoration: none;
            display: inline-block;
        }

        .filter-controls a:hover {
            background: #ddd;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #2e8b57;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
        }

        .trend-chart {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .trend-chart h4 {
            color: #2e8b57;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .mini-bar-chart {
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
            height: 110px;
            gap: 5px;
        }

        .bar-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            height: 100%;
            font-size: 10px;
            color: #666;
            width: 14.28%;
        }

        .bar-value {
            font-weight: bold;
            color: #2e8b57;
            margin-bottom: 5px;
            font-size: 12px;
        }

        .bar-label {
            margin-top: 5px;
            white-space: nowrap;
            font-size: 11px;
        }

        .bar {
            width: 24px;
            background: #2e8b57;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 3px 3px 0 0;
            transition: all 0.2s;
            font-size: 10px;
        }

        .bar:hover {
            background: #226b43;
            transform: scaleY(1.05);
        }

        .recent-reports {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .recent-reports h4 {
            color: #2e8b57;
            margin-bottom: 10px;
            font-size: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #e6f2e6;
            font-weight: bold;
        }

        .status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }

        .status-green {
            background: #d4edda;
            color: #155724;
        }

        .status-yellow {
            background: #fff3cd;
            color: #856404;
        }

        .status-red {
            background: #f8d7da;
            color: #721c24;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
                padding: 80px 15px 20px;
            }

            .main-content {
                margin-left: 200px;
            }

            .stats-cards {
                grid-template-columns: 1fr;
            }

            .filter-controls {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 480px) {
            .sidebar {
                width: 100%;
                position: static;
                height: auto;
                box-shadow: none;
                border-bottom: 2px solid #2e8b57;
            }

            .main-content {
                margin-left: 0;
                padding-top: 100px;
            }

            .header {
                position: static;
            }

            .stats-header {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
        }
    </style>
</head>

<body class="fade-in">
    <!-- Header -->
    <div class="header">
        <div class="header-title">
            <div class="header-logo">S</div>
            <div>
                <div style="font-size: 18px; font-weight: bold;">Beranda</div>
                <div style="font-size: 12px; opacity: 0.9;">ADMIN</div>
            </div>
        </div>
        <a href="../dashboard.php" class="header-exit">
            <span>←</span> KELUAR
        </a>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <ul class="sidebar-menu">
            <li>
                <a href="dashboardAdmin.php" class="menu-item active">
                    <div class="menu-icon">📊</div>
                    <div>Beranda</div>
                </a>
            </li>
            <li>
                <a href="kelolaLaporan.php" class="menu-item">
                    <div class="menu-icon">📋</div>
                    <div>Kelola Laporan Aduan</div>
                </a>
            </li>
            <li>
                <a href="kelolaArtikel.php" class="menu-item">
                    <div class="menu-icon">📝</div>
                    <div>Kelola Artikel Edukasi</div>
                </a>
            </li>
            <li>
                <a href="kelolaTPS.php" class="menu-item">
                    <div class="menu-icon">🗑️</div>
                    <div>Kelola Informasi TPS</div>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="content-header">
            <h2>Statistik Laporan</h2>
        </div>

        <div class="stats-container">
            <div class="stats-header">
                <h3>Statistik Laporan – <?= htmlspecialchars($bulan_list[$selected_bulan]) ?> <?= $selected_tahun ?></h3>
                <div class="filter-controls">
                    <form method="GET" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                        <div>
                            <label for="tahun">Tahun:</label>
                            <select name="tahun" id="tahun">
                                <?php foreach ($tahun_options as $thn): ?>
                                    <option value="<?= $thn ?>" <?= $thn == $selected_tahun ? 'selected' : '' ?>><?= $thn ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="bulan">Bulan:</label>
                            <select name="bulan" id="bulan">
                                <?php foreach ($bulan_list as $num => $nama): ?>
                                    <option value="<?= $num ?>" <?= $num == $selected_bulan ? 'selected' : '' ?>><?= $nama ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit">Tampilkan</button>
                        <a href="dashboardAdmin.php">Reset</a>
                    </form>
                </div>
            </div>

            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-number"><?= $total ?></div>
                    <div class="stat-label">Total Laporan</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $selesai_diproses ?></div>
                    <div class="stat-label">Selesai Diproses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $belum_diproses ?></div>
                    <div class="stat-label">Belum Diproses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $ditolak ?></div>
                    <div class="stat-label">Ditolak</div>
                </div>
            </div>

            <div class="trend-chart">
                <h4>Trend Laporan (7 Hari Terakhir)</h4>
                <div class="mini-bar-chart">
                    <?php
                    $max_count = max($counts) ?: 1;
                    ?>
                    <?php foreach ($dates as $i => $day_abbr):
                        $full_date = date('Y-m-d', strtotime("-" . (6 - $i) . " days"));
                        $count = $counts[$i];
                        $height = ($count / $max_count) * 80;
                        if ($height < 10) $height = 10;
                    ?>
                        <div class="bar-container">
                            <div class="bar-value"><?= $count ?></div>
                            <div class="bar" style="height: <?= $height ?>px;"></div>
                            <div class="bar-label"><?= date('d M', strtotime($full_date)) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="recent-reports">
                <h4>Laporan Terbaru</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Alamat</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_reports as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars(date('d M Y', strtotime($r['created_at']))) ?></td>
                                <td><?= htmlspecialchars($r['alamat']) ?></td>
                                <td>
                                    <?php
                                    $status_class = match ($r['status']) {
                                        'Diterima' => 'status-green',
                                        'Diproses' => 'status-yellow',
                                        'Ditolak' => 'status-red',
                                        default => 'status-yellow'
                                    };
                                    ?>
                                    <span class="status <?= $status_class ?>"><?= htmlspecialchars($r['status']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Animasi Navigasi -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const body = document.body;
            const mainContent = document.getElementById('mainContent');

            // Fade-in saat halaman dimuat
            setTimeout(() => {
                body.classList.add('fade-in-ready');
            }, 50);

            // Fade-out saat navigasi
            document.querySelectorAll('.menu-item a, .filter-controls a').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    mainContent.style.opacity = '0';
                    setTimeout(() => {
                        window.location.href = this.href;
                    }, 200);
                });
            });

            // Form submit (filter)
            document.querySelectorAll('.filter-controls form').forEach(form => {
                form.addEventListener('submit', function() {
                    mainContent.style.opacity = '0';
                });
            });
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            const mainContent = document.getElementById('mainContent');

            // Terapkan fade out saat klik link internal (kecuali logout)
            document.querySelectorAll('.menu-item, .filter-controls a').forEach(link => {
                link.addEventListener('click', function(e) {
                    if (this.tagName === 'A') {
                        e.preventDefault();
                        const url = this.href;
                        mainContent.classList.add('fade-out');
                        setTimeout(() => {
                            window.location.href = url;
                        }, 200);
                    }
                });
            });

            // Untuk tombol submit form (filter)
            document.querySelectorAll('.filter-controls form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    mainContent.classList.add('fade-out');
                    // Biarkan form submit normal setelah animasi
                    setTimeout(() => {
                        // Form akan submit sendiri karena tidak dicegah
                    }, 200);
                });
            });
        });
    </script>
</body>

</html>
