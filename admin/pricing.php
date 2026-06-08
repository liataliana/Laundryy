<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// Handle actions: add, edit, delete service
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $price_per_kg = (float)($_POST['price_per_kg'] ?? 0);
    $estimated_days = (int)($_POST['estimated_days'] ?? 0);

    if ($action === 'add' || $action === 'edit') {
        if (empty($name) || $price_per_kg <= 0 || $estimated_days <= 0) {
            setFlash('error', 'Nama layanan, harga/kg, dan estimasi hari wajib diisi dengan nilai valid.');
        } else {
            try {
                if ($action === 'add') {
                    // Check if service name already exists
                    $stmt = $pdo->prepare("SELECT id FROM services WHERE name = ?");
                    $stmt->execute([$name]);
                    if ($stmt->fetch()) {
                        setFlash('error', 'Nama layanan sudah ada.');
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO services (name, price_per_kg, estimated_days) VALUES (?, ?, ?)");
                        $stmt->execute([$name, $price_per_kg, $estimated_days]);
                        setFlash('success', 'Layanan berhasil ditambahkan.');
                    }
                } elseif ($action === 'edit') {
                    $stmt = $pdo->prepare("UPDATE services SET name=?, price_per_kg=?, estimated_days=? WHERE id=?");
                    $stmt->execute([$name, $price_per_kg, $estimated_days, $id]);
                    setFlash('success', 'Layanan berhasil diperbarui.');
                }
            } catch (PDOException $e) {
                setFlash('error', 'Terjadi kesalahan: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'delete' && $id > 0) {
        // Check if service is used in any orders
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE service_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                setFlash('error', 'Tidak dapat menghapus layanan yang sedang digunakan dalam pesanan.');
            } else {
                $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
                $stmt->execute([$id]);
                setFlash('success', 'Layanan berhasil dihapus.');
            }
        } catch (PDOException $e) {
            setFlash('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    redirect(APP_URL . '/admin/pricing.php');
}

// Fetch services for listing
$services = $pdo->query("SELECT id, name, price_per_kg, estimated_days, created_at FROM services ORDER BY created_at DESC")->fetchAll();
$pending = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='menunggu'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Harga Layanan - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?=v2">
    <style>
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:1000}
        .modal-content{background:white;padding:24px;border-radius:12px;width:90%;max-width:400px;position:relative}
        .modal-close{position:absolute;top:12px;right:12px;font-size:24px;cursor:pointer;color:#6b7a99}
        .form-group{margin-bottom:16px}
        .form-group label{display:block;margin-bottom:6px;font-weight:600}
        .form-group input{width:100%;padding:10px;border:1px solid var(--border);border-radius:6px;font-size:14px}
        .form-group select{width:100%;padding:10px;border:1px solid var(--border);border-radius:6px;font-size:14px}
        .form-group textarea{width:100%;padding:10px;border:1px solid var(--border);border-radius:6px;font-size:14px;height:80px}
        .btn-primary{background:var(--primary);color:white;border:none;padding:10px 16px;border-radius:6px;cursor:pointer}
        .btn-outline{border:1.5px solid var(--primary);color:var(--primary);background:transparent;padding:10px 16px;border-radius:6px;cursor:pointer}
        .btn-sm{padding:8px 12px;font-size:13px}
        .action-btn{margin-right:8px}
        .price-cell{font-family:monospace}
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
            <a href="<?= APP_URL ?>/admin/pricing.php" class="nav-item active"><span class="nav-icon">💰</span> Harga Layanan</a>
            <a href="<?= APP_URL ?>/admin/reports.php" class="nav-item"><span class="nav-icon">📊</span> Laporan</a>
        </nav>
        <div class="sidebar-footer">
            <a href="<?= APP_URL ?>/logout.php" class="btn btn-outline btn-block" style="color:#ff4757;border-color:#ff4757">🚪 Keluar</a>
        </div>
    </aside>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title">Kelola Harga Layanan</div>
            <button onclick="showAddServiceModal()" class="btn btn-primary btn-sm">+ Tambah Layanan</button>
        </div>
        <div class="page-content">
            <?php showFlash(); ?>

            <div class="card">
                <div class="card-header"><div class="card-title">Daftar Layanan</div></div>
                <div class="card-body">
                    <?php if (empty($services)): ?>
                        <div class="empty-state"><div class="empty-icon">💰</div><p>Belum ada layanan terdaftar.</p></div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama Layanan</th>
                                    <th>Harga/kg</th>
                                    <th>Estimasi Selesai</th>
                                    <th>Daftar Pada</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($services as $service): ?>
                                    <tr>
                                        <td><?= sanitize($service['name']) ?></td>
                                        <td class="price-cell"><?= formatRupiah($service['price_per_kg']) ?></td>
                                        <td><?= $service['estimated_days'] ?> hari</td>
                                        <td><?= formatDateTime($service['created_at']) ?></td>
                                        <td>
                                            <button class="btn btn-outline btn-sm action-btn" onclick="showEditServiceModal(<?= $service['id'] ?>, '<?= addslashes($service['name']) ?>', <?= $service['price_per_kg'] ?>, <?= $service['estimated_days'] ?>)">
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-outline btn-sm action-btn" style="color:#ff4757;border-color:#ff4757" onclick="if(confirm('Yakin ingin menghapus layanan ini?')){ document.getElementById('delete-service-form-<?= $service['id'] ?>').submit(); }">
                                                🗑️ Hapus
                                            </button>
                                            <form id="delete-service-form-<?= $service['id'] ?>" method="POST" style="display:none">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $service['id'] ?>">
                                            </form>
                                        </td>
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

<!-- Add Service Modal -->
<div id="add-service-modal" class="modal">
    <div class="modal-content">
        <div class="modal-close" onclick="hideAddServiceModal()">×</div>
        <h3>Tambah Layanan Baru</h3>
        <form id="add-service-form" method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Nama Layanan</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Harga per kg (Rp)</label>
                <input type="number" name="price_per_kg" min="0" step="100" required>
            </div>
            <div class="form-group">
                <label>Estimasi Selesai (hari)</label>
                <input type="number" name="estimated_days" min="1" required>
            </div>
            <button type="submit" class="btn btn-primary">Simpan Layanan</button>
            <button type="button" class="btn btn-outline" onclick="hideAddServiceModal()">Batal</button>
        </form>
    </div>
</div>

<!-- Edit Service Modal -->
<div id="edit-service-modal" class="modal">
    <div class="modal-content">
        <div class="modal-close" onclick="hideEditServiceModal()">×</div>
        <h3>Edit Layanan</h3>
        <form id="edit-service-form" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-service-id">
            <div class="form-group">
                <label>Nama Layanan</label>
                <input type="text" name="name" id="edit-service-name" required>
            </div>
            <div class="form-group">
                <label>Harga per kg (Rp)</label>
                <input type="number" name="price_per_kg" id="edit-service-price" min="0" step="100" required>
            </div>
            <div class="form-group">
                <label>Estimasi Selesai (hari)</label>
                <input type="number" name="estimated_days" id="edit-service-days" min="1" required>
            </div>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            <button type="button" class="btn btn-outline" onclick="hideEditServiceModal()">Batal</button>
        </form>
    </div>
</div>

<script>
    function showAddServiceModal() {
        document.getElementById('add-service-modal').style.display = 'flex';
        document.getElementById('add-service-form').reset();
    }
    function hideAddServiceModal() {
        document.getElementById('add-service-modal').style.display = 'none';
    }
    function showEditServiceModal(id, name, price, days) {
        document.getElementById('edit-service-id').value = id;
        document.getElementById('edit-service-name').value = name;
        document.getElementById('edit-service-price').value = price;
        document.getElementById('edit-service-days').value = days;
        document.getElementById('edit-service-modal').style.display = 'flex';
    }
    function hideEditServiceModal() {
        document.getElementById('edit-service-modal').style.display = 'none';
    }
    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }
</script>
</body>
</html>