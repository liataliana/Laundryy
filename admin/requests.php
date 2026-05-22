<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT o.*, u.name as user_name, s.name as service_name
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN services s ON o.service_id = s.id
        WHERE 1=1";
$params = [];

if ($filter) { $sql .= " AND o.status = ?"; $params[] = $filter; }
if ($search) { $sql .= " AND (o.order_code LIKE ? OR u.name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= " ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
$pending = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='menunggu'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manajemen Pesanan - <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <style>.filter-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}.filter-tab{padding:7px 16px;border-radius:99px;font-size:13px;font-weight:600;border:1.5px solid var(--border);color:var(--gray);transition:all .2s}.filter-tab:hover,.filter-tab.active{border-color:var(--primary);background:var(--primary-light);color:var(--primary)}</style>
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-logo"><div class="logo-icon">⚙️</div><div class="logo-text">Admin Panel</div></div>
    <div class="sidebar-user"><div class="user-name"><?= sanitize($_SESSION['admin_name']) ?></div><div class="user-role">Administrator</div></div>
    <nav class="sidebar-nav">
      <div class="nav-section">Menu</div>
      <a href="<?= APP_URL ?>/admin/dashboard.php" class="nav-item"><span class="nav-icon">🏠</span> Dashboard</a>
      <a href="<?= APP_URL ?>/admin/requests.php" class="nav-item active">
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
      <div class="topbar-title">Manajemen Pesanan</div>
    </div>
    <div class="page-content">
      <?php showFlash(); ?>

      <div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap">
        <form method="GET" style="flex:1;min-width:200px">
          <?php if ($filter): ?><input type="hidden" name="status" value="<?= sanitize($filter) ?>"><?php endif; ?>
          <input type="text" name="search" class="form-control" placeholder="🔍 Cari kode atau nama pelanggan..." value="<?= sanitize($search) ?>">
        </form>
      </div>

      <div class="filter-tabs">
        <a href="?" class="filter-tab <?= !$filter ? 'active' : '' ?>">Semua</a>
        <a href="?status=menunggu"   class="filter-tab <?= $filter==='menunggu'   ? 'active' : '' ?>">⏳ Menunggu</a>
        <a href="?status=diproses"   class="filter-tab <?= $filter==='diproses'   ? 'active' : '' ?>">⚙️ Diproses</a>
        <a href="?status=selesai"    class="filter-tab <?= $filter==='selesai'    ? 'active' : '' ?>">✅ Selesai</a>
        <a href="?status=diambil"    class="filter-tab <?= $filter==='diambil'    ? 'active' : '' ?>">📦 Diambil</a>
        <a href="?status=dibatalkan" class="filter-tab <?= $filter==='dibatalkan' ? 'active' : '' ?>">❌ Dibatalkan</a>
      </div>

      <div class="card">
        <div class="table-wrapper">
          <?php if (empty($orders)): ?>
            <div class="empty-state"><div class="empty-icon">📭</div><p>Tidak ada pesanan.</p></div>
          <?php else: ?>
          <table>
            <thead>
              <tr><th>Kode</th><th>Pelanggan</th><th>Layanan</th><th>Berat</th><th>Total</th><th>Status</th><th>Tanggal</th><th>Aksi</th></tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $o): ?>
              <tr>
                <td><strong><?= sanitize($o['order_code']) ?></strong></td>
                <td><?= sanitize($o['user_name']) ?></td>
                <td><?= sanitize($o['service_name']) ?></td>
                <td><?= $o['weight_kg'] ?> kg</td>
                <td><?= formatRupiah($o['total_price']) ?></td>
                <td><?= getStatusBadge($o['status']) ?></td>
                <td class="text-muted fs-sm"><?= formatDateTime($o['created_at']) ?></td>
                <td><a href="<?= APP_URL ?>/admin/request-details.php?id=<?= $o['id'] ?>" class="btn btn-primary btn-sm">Detail</a></td>
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