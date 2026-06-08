<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// Stats
$total_users   = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_orders  = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$total_revenue = $pdo->query("SELECT COALESCE(SUM(total_price),0) FROM orders WHERE status IN ('selesai','diambil')")->fetchColumn();
$pending       = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='menunggu'")->fetchColumn();

// Orders per status
$status_counts = [];
foreach (['menunggu','diproses','selesai','diambil','dibatalkan'] as $s) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status=?");
    $stmt->execute([$s]);
    $status_counts[$s] = $stmt->fetchColumn();
}

// Recent orders
$recent = $pdo->query("
    SELECT o.*, u.name as user_name, s.name as service_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN services s ON o.service_id = s.id
    ORDER BY o.created_at DESC
    LIMIT 8
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=2">
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon">⚙️</div>
      <div class="logo-text">Admin Panel</div>
    </div>
    <div class="sidebar-user">
      <div class="user-name"><?= sanitize($_SESSION['admin_name']) ?></div>
      <div class="user-role">Administrator</div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section">Menu</div>
      <a href="<?= APP_URL ?>/admin/dashboard.php" class="nav-item active"><span class="nav-icon">🏠</span> Dashboard</a>
      <a href="<?= APP_URL ?>/admin/requests.php" class="nav-item">
        <span class="nav-icon">📋</span> Pesanan
        <?php if ($pending > 0): ?><span class="nav-badge"><?= $pending ?></span><?php endif; ?>
      </a>
      <a href="<?= APP_URL ?>/admin/users.php" class="nav-item"><span class="nav-icon">👥</span> Pengguna</a>
      <a href="<?= APP_URL ?>/admin/pricing.php" class="nav-item"><span class="nav-icon">💰</span> Harga Layanan</a>
      <a href="<?= APP_URL ?>/admin/reports.php" class="nav-item"><span class="nav-icon">📊</span> Laporan</a>
    </nav>
            <div class="sidebar-footer">
            <a href="<?= APP_URL ?>/logout.php" class="btn btn-outline btn-block" style="color:#ff4757;border-color:#ff4757">🚪 Keluar</a>
          </div>
  </aside>

  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title">Dashboard</div>
      <div class="topbar-actions">
        <span style="color:var(--gray);font-size:13px">Halo, <strong><?= sanitize($_SESSION['admin_name']) ?></strong> 👋</span>
      </div>
    </div>

    <div class="page-content">
      <?php showFlash(); ?>

      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon blue">👥</div>
          <div>
            <div class="stat-value"><?= number_format($total_users) ?></div>
            <div class="stat-label">Total Pelanggan</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon orange">📋</div>
          <div>
            <div class="stat-value"><?= number_format($total_orders) ?></div>
            <div class="stat-label">Total Pesanan</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">💰</div>
          <div>
            <div class="stat-value" style="font-size:18px"><?= formatRupiah($total_revenue) ?></div>
            <div class="stat-label">Total Pendapatan</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon red">⏳</div>
          <div>
            <div class="stat-value"><?= $pending ?></div>
            <div class="stat-label">Pesanan Menunggu</div>
          </div>
        </div>
      </div>

      <!-- Status Overview -->
      <div class="card mb-6">
        <div class="card-header"><div class="card-title">Ringkasan Status Pesanan</div></div>
        <div class="card-body">
          <div style="display:flex;gap:16px;flex-wrap:wrap">
            <?php
            $status_colors = ['menunggu'=>'#ffa502','diproses'=>'#1a6bff','selesai'=>'#00c896','diambil'=>'#6b7a99','dibatalkan'=>'#ff4757'];
            $status_labels = ['menunggu'=>'Menunggu','diproses'=>'Diproses','selesai'=>'Selesai','diambil'=>'Diambil','dibatalkan'=>'Dibatalkan'];
            foreach ($status_counts as $s => $count): ?>
            <div style="flex:1;min-width:140px;padding:16px;background:var(--gray-light);border-radius:10px;text-align:center">
              <div style="font-size:24px;font-weight:800;color:<?= $status_colors[$s] ?>"><?= $count ?></div>
              <div style="font-size:13px;color:var(--gray);margin-top:4px"><?= $status_labels[$s] ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Recent Orders -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">Pesanan Terbaru</div>
          <a href="<?= APP_URL ?>/admin/requests.php" class="btn btn-outline btn-sm">Lihat Semua</a>
        </div>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Kode</th>
                <th>Pelanggan</th>
                <th>Layanan</th>
                <th>Berat</th>
                <th>Total</th>
                <th>Status</th>
                <th>Tanggal</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent as $o): ?>
              <tr>
                <td><strong><?= sanitize($o['order_code']) ?></strong></td>
                <td><?= sanitize($o['user_name']) ?></td>
                <td><?= sanitize($o['service_name']) ?></td>
                <td><?= $o['weight_kg'] ?> kg</td>
                <td><?= formatRupiah($o['total_price']) ?></td>
                <td><?= getStatusBadge($o['status']) ?></td>
                <td class="text-muted fs-sm"><?= formatDateTime($o['created_at']) ?></td>
                <td><a href="<?= APP_URL ?>/admin/request-details.php?id=<?= $o['id'] ?>" class="btn btn-outline btn-sm">Detail</a></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>