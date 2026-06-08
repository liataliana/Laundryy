<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// Handle actions: add, edit, delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    if ($action === 'add' || $action === 'edit') {
        if (empty($name) || empty($email)) {
            setFlash('error', 'Nama dan email wajib diisi.');
        } elseif ($action === 'add' && empty($password)) {
            setFlash('error', 'Password wajib diisi untuk pengguna baru.');
        } else {
            try {
                if ($action === 'add') {
                    // Check if email already exists
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        setFlash('error', 'Email sudah terdaftar.');
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$name, $email, $hashed_password, $role]);
                        setFlash('success', 'Pengguna berhasil ditambahkan.');
                    }
                } elseif ($action === 'edit') {
                    if (!empty($password)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, password=?, role=? WHERE id=?");
                        $stmt->execute([$name, $email, $hashed_password, $role, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
                        $stmt->execute([$name, $email, $role, $id]);
                    }
                    setFlash('success', 'Pengguna berhasil diperbarui.');
                }
            } catch (PDOException $e) {
                setFlash('error', 'Terjadi kesalahan: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'delete' && $id > 0) {
        // Prevent deleting yourself
        if ($id === $_SESSION['admin_id']) {
            setFlash('error', 'Tidak dapat menghapus akun sendiri.');
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                setFlash('success', 'Pengguna berhasil dihapus.');
            } catch (PDOException $e) {
                setFlash('error', 'Terjadi kesalahan: ' . $e->getMessage());
            }
        }
    }

    redirect(APP_URL . '/admin/users.php');
}

// Fetch users for listing
$users = $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC")->fetchAll();
$pending = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='menunggu'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?=v2">
    <style>
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:1000}
        .modal-content{background:white;padding:24px;border-radius:12px;width:90%;max-width:400px;position:relative}
        .modal-close{position:absolute;top:12px;right:12px;font-size:24px;cursor:pointer;color:#6b7a99}
        .form-group{margin-bottom:16px}
        .form-group label{display:block;margin-bottom:6px;font-weight:600}
        .form-group input{width:100%;padding:10px;border:1px solid var(--border);border-radius:6px;font-size:14px}
        .form-group select{width:100%;padding:10px;border:1px solid var(--border);border-radius:6px;font-size:14px}
        .btn-primary{background:var(--primary);color:white;border:none;padding:10px 16px;border-radius:6px;cursor:pointer}
        .btn-outline{border:1.5px solid var(--primary);color:var(--primary);background:transparent;padding:10px 16px;border-radius:6px;cursor:pointer}
        .btn-sm{padding:8px 12px;font-size:13px}
        .action-btn{margin-right:8px}
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
            <a href="<?= APP_URL ?>/admin/users.php" class="nav-item active"><span class="nav-icon">👥</span> Pengguna</a>
            <a href="<?= APP_URL ?>/admin/pricing.php" class="nav-item"><span class="nav-icon">💰</span> Harga Layanan</a>
            <a href="<?= APP_URL ?>/admin/reports.php" class="nav-item"><span class="nav-icon">📊</span> Laporan</a>
        </nav>
        <div class="sidebar-footer">
            <a href="<?= APP_URL ?>/admin/logout.php" class="btn btn-outline btn-block" style="color:#ff4757;border-color:#ff4757">🚪 Keluar</a>
        </div>
    </aside>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title">Kelola Pengguna</div>
            <button onclick="showAddUserModal()" class="btn btn-primary btn-sm">+ Tambah Pengguna</button>
        </div>
        <div class="page-content">
            <?php showFlash(); ?>

            <div class="card">
                <div class="card-header"><div class="card-title">Daftar Pengguna</div></div>
                <div class="card-body">
                    <?php if (empty($users)): ?>
                        <div class="empty-state"><div class="empty-icon">👥</div><p>Belum ada pengguna terdaftar.</p></div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Email</th>
                                    <th>Peran</th>
                                    <th>Daftar Pada</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?= sanitize($user['name']) ?></td>
                                        <td><?= sanitize($user['email']) ?></td>
                                        <td><?= ucfirst($user['role']) ?></td>
                                        <td><?= formatDateTime($user['created_at']) ?></td>
                                        <td>
                                            <button class="btn btn-outline btn-sm action-btn" onclick="showEditUserModal(<?= $user['id'] ?>, '<?= addslashes($user['name']) ?>', '<?= addslashes($user['email']) ?>', '<?= $user['role'] ?>')">
                                                ✏️ Edit
                                            </button>
                                            <?php if ($user['id'] !== $_SESSION['admin_id']): ?>
                                                <button class="btn btn-outline btn-sm action-btn" style="color:#ff4757;border-color:#ff4757" onclick="if(confirm('Yakin ingin menghapus pengguna ini?')){ document.getElementById('delete-user-form-<?= $user['id'] ?>').submit(); }">
                                                    🗑️ Hapus
                                                </button>
                                                <form id="delete-user-form-<?= $user['id'] ?>" method="POST" style="display:none">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                </form>
                                            <?php endif; ?>
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

<!-- Add User Modal -->
<div id="add-user-modal" class="modal">
    <div class="modal-content">
        <div class="modal-close" onclick="hideAddUserModal()">×</div>
        <h3>Tambah Pengguna Baru</h3>
        <form id="add-user-form" method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>Peran</label>
                <select name="role">
                    <option value="user">Pelanggan</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Simpan Pengguna</button>
            <button type="button" class="btn btn-outline" onclick="hideAddUserModal()">Batal</button>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="edit-user-modal" class="modal">
    <div class="modal-content">
        <div class="modal-close" onclick="hideEditUserModal()">×</div>
        <h3>Edit Pengguna</h3>
        <form id="edit-user-form" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-user-id">
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="name" id="edit-user-name" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="edit-user-email" required>
            </div>
            <div class="form-group">
                <label>Password (biarkan kosong jika tidak ingin mengubah)</label>
                <input type="password" name="password" id="edit-user-password">
            </div>
            <div class="form-group">
                <label>Peran</label>
                <select name="role" id="edit-user-role">
                    <option value="user">Pelanggan</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            <button type="button" class="btn btn-outline" onclick="hideEditUserModal()">Batal</button>
        </form>
    </div>
</div>

<script>
    function showAddUserModal() {
        document.getElementById('add-user-modal').style.display = 'flex';
        document.getElementById('add-user-form').reset();
    }
    function hideAddUserModal() {
        document.getElementById('add-user-modal').style.display = 'none';
    }
    function showEditUserModal(id, name, email, role) {
        document.getElementById('edit-user-id').value = id;
        document.getElementById('edit-user-name').value = name;
        document.getElementById('edit-user-email').value = email;
        document.getElementById('edit-user-role').value = role;
        document.getElementById('edit-user-password').value = '';
        document.getElementById('edit-user-modal').style.display = 'flex';
    }
    function hideEditUserModal() {
        document.getElementById('edit-user-modal').style.display = 'none';
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