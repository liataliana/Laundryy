<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$pending = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='menunggu'")->fetchColumn();

// Get user's orders
$stmt = $pdo->prepare("
    SELECT o.*, s.name as service_name
    FROM orders o
    JOIN services s ON o.service_id = s.id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_orders = $stmt->fetchAll();

// Get stats for user
$total_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE user_id = ".$_SESSION['user_id'])->fetchColumn();
$total_spent = $pdo->query("SELECT COALESCE(SUM(total_price),0) FROM orders WHERE user_id = ".$_SESSION['user_id']." AND status IN ('selesai','diambil')")->fetchColumn();
$pending_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE user_id = ".$_SESSION['user_id']." AND status='menunggu'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pengguna - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?=v2">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="logo-icon">👕</div>
            <div class="logo-text"><?= APP_NAME ?></div>
        </div>
        <div class="sidebar-user">
            <div class="user-name"><?= sanitize($_SESSION['user_name']) ?></div>
            <div class="user-role">Pelanggan</div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">Menu</div>
            <a href="<?= APP_URL ?>/user/dashboard.php" class="nav-item active"><span class="nav-icon">🏠</span> Dashboard</a>
            <a href="<?= APP_URL ?>/user/request.php" class="nav-item"><span class="nav-icon">📋</span> Pesanan Baru</a>
            <a href="<?= APP_URL ?>/user/history.php" class="nav-item"><span class="nav-icon">📜</span> Riwayat Pesanan</a>
        </nav>
            <div class="sidebar-footer">
            <a href="<?= APP_URL ?>/logout.php" class="btn btn-outline btn-block" style="color:#ff4757;border-color:#ff4757">🚪 Keluar</a>
          </div>
    </aside>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title">Dashboard Pengguna</div>
            <div class="topbar-actions">
                <span style="color:var(--gray);font-size:13px">Halo, <strong><?= sanitize($_SESSION['user_name']) ?></strong> 👋</span>
            </div>
        </div>

        <div class="page-content">
            <?php showFlash(); ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">👕</div>
                    <div>
                        <div class="stat-value"><?= number_format($total_orders) ?></div>
                        <div class="stat-label">Total Pesanan</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">💰</div>
                    <div>
                        <div class="stat-value" style="font-size:18px"><?= formatRupiah($total_spent) ?></div>
                        <div class="stat-label">Total Pengeluaran</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange">📋</div>
                    <div>
                        <div class="stat-value"><?= number_format($pending_orders) ?></div>
                        <div class="stat-label">Pesanan Aktif</div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Pesanan Terbaru</div>
                    <a href="<?= APP_URL ?>/user/history.php" class="btn btn-outline btn-sm">Lihat Semua</a>
                </div>
                <div class="table-wrapper">
                    <?php if (empty($recent_orders)): ?>
                        <div class="empty-state"><div class="empty-icon">📭</div><p>Belum ada pesanan.</p></div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Layanan</th>
                                    <th>Berat</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $o): ?>
                                    <tr>
                                        <td><strong><?= sanitize($o['order_code']) ?></strong></td>
                                        <td><?= sanitize($o['service_name']) ?></td>
                                        <td><?= $o['weight_kg'] ?> kg</td>
                                        <td><?= formatRupiah($o['total_price']) ?></td>
                                        <td><?= getStatusBadge($o['status']) ?></td>
                                        <td class="text-muted fs-sm"><?= formatDateTime($o['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header"><div class="card-title">Tindakan Cepat</div></div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px">
                        <a href="<?= APP_URL ?>/user/request.php" class="btn btn-primary btn-block btn-lg">
                            📋 Buat Pesanan Baru
                        </a>
                        <a href="<?= APP_URL ?>/user/history.php" class="btn btn-outline btn-block btn-lg">
                            📜 Lihat Riwayat Pesanan
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>