<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// Get date range from filter
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // first day of current month
$end_date   = $_GET['end_date']   ?? date('Y-m-t');   // last day of current month

// Validate dates
if (isset($_GET['start_date'])) {
    $start_date = $_GET['start_date'];
}

if (isset($_GET['end_date'])) {
    $end_date = $_GET['end_date'];
}

// Ensure start_date <= end_date
if ($start_date > $end_date) {
    $tmp = $start_date;
    $start_date = $end_date;
    $end_date = $tmp;
}

// Fetch orders within date range
$stmt = $pdo->prepare("
    SELECT o.*, u.name as user_name, s.name as service_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN services s ON o.service_id = s.id
    WHERE DATE(o.created_at) BETWEEN ? AND ?
    ORDER BY o.created_at DESC
");
$stmt->execute([$start_date, $end_date]);
$orders = $stmt->fetchAll();

// Calculate summary stats
$total_orders   = count($orders);
$total_revenue  = array_sum(array_column($orders, 'total_price'));
$completed      = array_filter($orders, fn($o) => in_array($o['status'], ['selesai', 'diambil']));
$completed_count = count($completed);
$cancelled      = array_filter($orders, fn($o) => $o['status'] === 'dibatalkan');
$cancelled_count = count($cancelled);
$pending        = array_filter($orders, fn($o) => $o['status'] === 'menunggu');
$pending_count  = count($pending);
$processing     = array_filter($orders, fn($o) => $o['status'] === 'diproses');
$processing_count = count($processing);

// Revenue by service
$revenue_by_service = [];
foreach ($orders as $order) {
    $service = $order['service_name'];
    if (!isset($revenue_by_service[$service])) {
        $revenue_by_service[$service] = 0;
    }
    $revenue_by_service[$service] += $order['total_price'];
}

// Orders by status for chart
$status_counts = [];
foreach (['menunggu','diproses','selesai','diambil','dibatalkan'] as $status) {
    $status_counts[$status] = count(array_filter($orders, fn($o) => $o['status'] === $status));
}
$pending = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='menunggu'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <style>
        .filters{display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-bottom:24px}
        .filters input[type="date"], .filters select{padding:10px;border:1px solid var(--border);border-radius:6px;font-size:14px}
        .filters button{padding:10px 16px;border:none;border-radius:6px;cursor:pointer;font-weight:600}
        .filters .btn-primary{background:var(--primary);color:white}
        .filters .btn-outline{border:1.5px solid var(--primary);color:var(--primary);background:transparent}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-bottom:24px}
        .stat-card{background:var(--gray-light);border-radius:10px;padding:20px;text-align:center}
        .stat-value{font-size:24px;font-weight:800;margin-bottom:4px}
        .stat-label{font-size:14px;color:var(--gray)}
        .chart-container{background:var(--gray-light);border-radius:10px;padding:20px;margin-bottom:24px}
        .chart-bar{display:flex;align-items:center;gap:12px;margin-bottom:12px}
        .chart-bar-label{min-width:100px;font-weight:600}
        .chart-bar-fill{background:var(--primary);height:24px;border-radius:4px;position:relative;overflow:hidden}
        .chart-bar-fill-inner{height:100%;background:var(--primary-light);transition:width .3s ease}
        .chart-bar-value{min-width:60px;text-align:right;font-family:monospace}
        .recent-orders{background:var(--gray-light);border-radius:10px;overflow:hidden}
        .recent-orders table{width:100%;border-collapse:collapse}
        .recent-orders th,.recent-orders td{padding:12px 16px;text-align:left;border-bottom:1px solid var(--border)}
        .recent-orders th{background:var(--gray-lighter);font-weight:600;font-size:14px}
        .recent-orders tbody tr:hover{background:var(--gray)}
        .no-data{text-align:center;padding:32px;color:var(--gray)}
        .no-data-icon{font-size:48px;margin-bottom:16px}
        .status-badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:600;text-transform:uppercase}
        .status-menunggu{background:#ffa50220;color:#ffa502}
        .status-diproses{background:#1a6bff20;color:#1a6bff}
        .status-selesai{background:#00c89620;color:#00c896}
        .status-diambil{background:#6b7a9920;color:#6b7a99}
        .status-dibatalkan{background:#ff475720;color:#ff4757}
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-logo"><div class="logo-icon">⚙️</div><div class="logo-text">Admin Panel</div></div>
        <div class="sidebar-user"><div class="user-name"><?= sanitize($_SESSION['admin_name']) ?></div><div class="user-role">Administrator</div></div>
        <nav class="sidebar-nav">
            <div class="nav-section">Menu</div>
            <a href="<?= APP_URL ?>/admin/dashboard.php" class="nav-item"><span class="nav-icon">🏠</span> Dashboard</a>
            <a href="<?= APP_URL ?>/admin/requests.php" class="nav-item"><span class="nav-icon">📋</span> Pesanan</a>
            <a href="<?= APP_URL ?>/admin/users.php" class="nav-item"><span class="nav-icon">👥</span> Pengguna</a>
            <a href="<?= APP_URL ?>/admin/pricing.php" class="nav-item"><span class="nav-icon">💰</span> Harga Layanan</a>
            <a href="<?= APP_URL ?>/admin/reports.php" class="nav-item active"><span class="nav-icon">📊</span> Laporan</a>
        </nav>
        <div class="sidebar-footer">
            <a href="<?= APP_URL ?>/logout.php" class="btn btn-outline btn-block" style="color:#ff4757;border-color:#ff4757">🚪 Keluar</a>
        </div>
    </aside>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title">Laporan</div>
        </div>
        <div class="page-content">
            <?php showFlash(); ?>

            <!-- Filter Form -->
            <div class="filters">
                <form method="GET">
                    <label>Dari Tanggal</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                    <label>Sampai Tanggal</label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                    <button type="submit" class="btn-primary">Terapkan Filter</button>
                    <a href="?" class="btn-outline">Reset</a>
                </form>
            </div>

            <!-- Summary Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📋</div>
                    <div>
                        <div class="stat-value"><?= number_format($total_orders) ?></div>
                        <div class="stat-label">Total Pesanan</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div>
                        <div class="stat-value"><?= formatRupiah($total_revenue) ?></div>
                        <div class="stat-label">Total Pendapatan</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div>
                        <div class="stat-value"><?= number_format($completed_count) ?></div>
                        <div class="stat-label">Pesanan Selesai</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⏳</div>
                    <div>
                        <div class="stat-value"><?= number_format($pending_count) ?></div>
                        <div class="stat-label">Pesanan Menunggu</div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="chart-container">
                <div class="card-header"><div class="card-title">Pendapatan per Layanan</div></div>
                <div class="card-body">
                    <?php if (!empty($revenue_by_service)): ?>
                        <?php
                        $max_revenue = max($revenue_by_service);
                        foreach ($revenue_by_service as $service => $revenue): ?>
                            <div class="chart-bar">
                                <span class="chart-bar-label"><?= sanitize($service) ?></span>
                                <div class="chart-bar-fill" style="width: <?= ($revenue / $max_revenue) * 100 ?>%">
                                    <div class="chart-bar-fill-inner"></div>
                                </div>
                                <span class="chart-bar-value"><?= formatRupiah($revenue) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data">
                            <div class="no-data-icon">📊</div>
                            <p>Tidak ada data untuk periode yang dipilih.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="chart-container">
                <div class="card-header"><div class="card-title">Distribusi Status Pesanan</div></div>
                <div class="card-body">
                    <?php if (!empty($status_counts)): ?>
                        <?php
                        $max_count = max($status_counts);
                        foreach ($status_counts as $status => $count): ?>
                            <div class="chart-bar">
                                <span class="chart-bar-label"><?= ucfirst($status) ?></span>
                                <div class="chart-bar-fill" style="width: <?= ($count / $max_count) * 100 ?>%; background: <?= match($status) {
                                    'menunggu' => '#ffa502',
                                    'diproses' => '#1a6bff',
                                    'selesai' => '#00c896',
                                    'diambil' => '#6b7a99',
                                    'dibatalkan' => '#ff4757',
                                } ?>;">
                                    <div class="chart-bar-fill-inner"></div>
                                </div>
                                <span class="chart-bar-value"><?= number_format($count) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data">
                            <div class="no-data-icon">📊</div>
                            <p>Tidak ada data untuk periode yang dipilih.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="card">
                <div class="card-header"><div class="card-title">Pesanan Terbaru (<?= number_format($total_orders) ?>)</div></div>
                <div class="card-body">
                    <?php if (empty($orders)): ?>
                        <div class="no-data">
                            <div class="no-data-icon">📭</div>
                            <p>Tidak ada pesanan untuk periode yang dipilih.</div>
                        </div>
                    <?php else: ?>
                        <table class="recent-orders">
                            <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Pelanggan</th>
                                    <th>Layanan</th>
                                    <th>Berat</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><strong><?= sanitize($order['order_code']) ?></strong></td>
                                        <td><?= sanitize($order['user_name']) ?></td>
                                        <td><?= sanitize($order['service_name']) ?></td>
                                        <td><?= $order['weight_kg'] ?> kg</td>
                                        <td><?= formatRupiah($order['total_price']) ?></td>
                                        <td><span class="status-badge status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></td>
                                        <td><?= formatDateTime($order['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>