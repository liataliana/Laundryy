<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$pending = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='menunggu'")->fetchColumn();

// Get user's orders with pagination (simple limit for now)
$stmt = $pdo->prepare("
    SELECT o.*, s.name as service_name
    FROM orders o
    JOIN services s ON o.service_id = s.id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=2">
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
            <a href="<?= APP_URL ?>/user/dashboard.php" class="nav-item"><span class="nav-icon">🏠</span> Dashboard</a>
            <a href="<?= APP_URL ?>/user/request.php" class="nav-item"><span class="nav-icon">📋</span> Pesanan Baru</a>
            <a href="<?= APP_URL ?>/user/history.php" class="nav-item active"><span class="nav-icon">📜</span> Riwayat Pesanan</a>
        </nav>
        <div class="sidebar-footer">
            <a href="<?= APP_URL ?>/logout.php" class="btn btn-outline btn-block" style="color:#ff4757;border-color:#ff4757">🚪 Keluar</a>
        </div>
    </aside>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title">Riwayat Pesanan</div>
        </div>
        <div class="page-content">
            <?php showFlash(); ?>

            <?php if (empty($orders)): ?>
                <div class="empty-state"><div class="empty-icon">📭</div><p>Belum ada pesanan.</p></div>
            <?php else: ?>
                <div class="table-wrapper">
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
                            <?php foreach ($orders as $o): ?>
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
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>