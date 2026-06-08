<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = (int)($_POST['service_id'] ?? 0);
    $weight_kg  = (float)($_POST['weight_kg'] ?? 0);
    $notes      = trim($_POST['notes'] ?? '');

    if ($service_id <= 0 || $weight_kg <= 0) {
        setFlash('error', 'Layanan dan berat harus dipilih dengan nilai valid.');
    } else {
        // Get service details
        $stmt = $pdo->prepare("SELECT price_per_kg, estimated_days FROM services WHERE id = ?");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch();

        if (!$service) {
            setFlash('error', 'Layanan tidak ditemukan.');
        } else {
            // Generate order code
            $order_code = 'LS' . strtoupper(substr(md5(time()), 0, 8));
            $total_price = $service['price_per_kg'] * $weight_kg;

            try {
                // Insert order
                $stmt = $pdo->prepare("INSERT INTO orders (user_id, service_id, order_code, weight_kg, total_price, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $service_id, $order_code, $weight_kg, $total_price, $notes, 'menunggu']);
                
                setFlash('success', 'Pesanan laundry berhasil dibuat! Kode pesanan: '.$order_code);
                redirect(APP_URL . '/user/history.php');
            } catch (PDOException $e) {
                setFlash('error', 'Terjadi kesalahan: ' . $e->getMessage());
            }
        }
    }
}

// Fetch services for dropdown
$services = $pdo->query("SELECT id, name, price_per_kg, estimated_days FROM services ORDER BY name")->fetchAll();
$pending = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='menunggu'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Pesanan Laundry - <?= APP_NAME ?></title>
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
            <a href="<?= APP_URL ?>/user/request.php" class="nav-item active"><span class="nav-icon">📋</span> Pesanan Baru</a>
            <a href="<?= APP_URL ?>/user/history.php" class="nav-item"><span class="nav-icon">📜</span> Riwayat Pesanan</a>
        </nav>
        <div class="sidebar-footer">
            <a href="<?= APP_URL ?>/logout.php" class="btn btn-outline btn-block" style="color:#ff4757;border-color:#ff4757">🚪 Keluar</a>
        </div>
    </aside>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title">Buat Pesanan Laundry</div>
        </div>
        <div class="page-content">
            <?php showFlash(); ?>

            <div class="card">
                <div class="card-header"><div class="card-title">Form Pesanan Laundry</div></div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label>Layanan Laundry</label>
                            <select name="service_id" class="form-control" required>
                                <option value="">-- Pilih Layanan --</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?= $service['id'] ?>">
                                        <?= sanitize($service['name']) ?> 
                                        (Rp <?= formatRupiah($service['price_per_kg']) ?>/kg, 
                                        est. <?= $service['estimated_days'] ?> hari)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Berat (kg)</label>
                            <input type="number" name="weight_kg" min="0.1" step="0.1" class="form-control" placeholder="Misal: 2.5" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Catatan (opsional)</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Contoh: Ada bentrokan, butuh setrika, dsb."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-block btn-lg">Buat Pesanan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>