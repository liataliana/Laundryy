<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$id = (int) ($_GET['id'] ?? 0);

$pending = $pdo->query("
    SELECT COUNT(*) 
    FROM orders 
    WHERE status = 'menunggu'
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| Get Order Detail
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT 
        o.*,
        u.name AS user_name,
        u.email AS user_email,
        s.name AS service_name,
        s.price_per_kg,
        s.estimated_days
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN services s ON o.service_id = s.id
    WHERE o.id = ?
");

$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'Pesanan tidak ditemukan.');
    redirect(APP_URL . '/admin/requests.php');
}

/*
|--------------------------------------------------------------------------
| Update Status
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {

    $new_status = $_POST['status'];

    $allowed_status = [
        'menunggu',
        'diproses',
        'selesai',
        'diambil',
        'dibatalkan'
    ];

    if (in_array($new_status, $allowed_status)) {

        $update = $pdo->prepare("
            UPDATE orders 
            SET status = ?
            WHERE id = ?
        ");

        $update->execute([$new_status, $id]);

        /*
        |--------------------------------------------------------------------------
        | Notification
        |--------------------------------------------------------------------------
        */
        $messages = [
            'diproses' => "Pesanan {$order['order_code']} sedang kami proses. Mohon tunggu ya!",
            'selesai' => "Pesanan {$order['order_code']} sudah selesai! Silakan diambil.",
            'diambil' => "Pesanan {$order['order_code']} berhasil diambil. Terima kasih!",
            'dibatalkan' => "Pesanan {$order['order_code']} telah dibatalkan oleh admin."
        ];

        if (isset($messages[$new_status])) {

            $notif = $pdo->prepare("
                INSERT INTO notifications (user_id, order_id, message)
                VALUES (?, ?, ?)
            ");

            $notif->execute([
                $order['user_id'],
                $id,
                $messages[$new_status]
            ]);
        }

        setFlash(
            'success',
            'Status pesanan berhasil diperbarui menjadi "' . $new_status . '".'
        );

        redirect(APP_URL . '/admin/request-details.php?id=' . $id);
    }
}

/*
|--------------------------------------------------------------------------
| Reload Updated Data
|--------------------------------------------------------------------------
*/
$stmt->execute([$id]);
$order = $stmt->fetch();

/*
|--------------------------------------------------------------------------
| Progress Config
|--------------------------------------------------------------------------
*/
$statuses = [
    'menunggu',
    'diproses',
    'selesai',
    'diambil'
];

$status_icons = [
    'menunggu' => '⏳',
    'diproses' => '⚙️',
    'selesai' => '✅',
    'diambil' => '📦'
];

$current_index = array_search($order['status'], $statuses);

$next_statuses = [
    'menunggu' => ['diproses', 'dibatalkan'],
    'diproses' => ['selesai', 'dibatalkan'],
    'selesai' => ['diambil']
];

$button_styles = [
    'diproses' => 'background:#1a6bff;color:white',
    'selesai' => 'background:#00c896;color:white',
    'diambil' => 'background:#6b7a99;color:white',
    'dibatalkan' => 'background:#ff4757;color:white'
];

$button_labels = [
    'diproses' => '⚙️ Tandai Diproses',
    'selesai' => '✅ Tandai Selesai',
    'diambil' => '📦 Tandai Sudah Diambil',
    'dibatalkan' => '❌ Batalkan Pesanan'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan - <?= APP_NAME ?></title>

    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?=v2">

    <style>
        .progress-track{
            display:flex;
            align-items:center;
            gap:0;
            margin:24px 0;
        }

        .progress-step{
            flex:1;
            text-align:center;
            position:relative;
        }

        .progress-step:not(:last-child)::after{
            content:'';
            position:absolute;
            top:18px;
            left:60%;
            width:80%;
            height:2px;
            background:var(--border);
        }

        .progress-step.done:not(:last-child)::after{
            background:var(--secondary);
        }

        .step-circle{
            width:36px;
            height:36px;
            border-radius:50%;
            background:var(--border);
            display:flex;
            align-items:center;
            justify-content:center;
            margin:0 auto 8px;
            font-size:16px;
            position:relative;
            z-index:1;
        }

        .progress-step.done .step-circle{
            background:var(--secondary);
        }

        .progress-step.current .step-circle{
            background:var(--primary);
            box-shadow:0 0 0 4px var(--primary-light);
        }

        .step-label{
            font-size:12px;
            font-weight:600;
            color:var(--gray);
        }

        .progress-step.done .step-label,
        .progress-step.current .step-label{
            color:var(--dark);
        }

        .detail-row{
            display:flex;
            justify-content:space-between;
            gap:12px;
            padding:12px 0;
            border-bottom:1px solid var(--border);
        }

        .detail-row:last-child{
            border-bottom:none;
        }

        .detail-label{
            color:var(--gray);
            font-size:13px;
        }

        .detail-value{
            font-weight:600;
            font-size:14px;
            text-align:right;
        }

        .status-btn{
            padding:12px 18px;
            border:none;
            border-radius:8px;
            font-weight:700;
            cursor:pointer;
            transition:all .2s ease;
        }

        .status-btn:hover{
            transform:translateY(-2px);
        }

        @media(max-width:768px){
            .detail-grid{
                grid-template-columns:1fr !important;
            }
        }
    </style>
</head>

<body>

<div class="layout">

    <!-- Sidebar -->
    <aside class="sidebar">

        <div class="sidebar-logo">
            <div class="logo-icon">⚙️</div>
            <div class="logo-text">Admin Panel</div>
        </div>

        <div class="sidebar-user">
            <div class="user-name">
                <?= sanitize($_SESSION['admin_name']) ?>
            </div>
            <div class="user-role">
                Administrator
            </div>
        </div>

        <nav class="sidebar-nav">

            <div class="nav-section">Menu</div>

            <a href="<?= APP_URL ?>/admin/dashboard.php" class="nav-item">
                <span class="nav-icon">🏠</span>
                Dashboard
            </a>

            <a href="<?= APP_URL ?>/admin/requests.php" class="nav-item active">
                <span class="nav-icon">📋</span>
                Pesanan

                <?php if ($pending > 0): ?>
                    <span class="nav-badge">
                        <?= $pending ?>
                    </span>
                <?php endif; ?>
            </a>

            <a href="<?= APP_URL ?>/admin/users.php" class="nav-item">
                <span class="nav-icon">👥</span>
                Pengguna
            </a>

            <a href="<?= APP_URL ?>/admin/pricing.php" class="nav-item">
                <span class="nav-icon">💰</span>
                Harga Layanan
            </a>

            <a href="<?= APP_URL ?>/admin/reports.php" class="nav-item">
                <span class="nav-icon">📊</span>
                Laporan
            </a>

        </nav>

        <div class="sidebar-footer">
            <a href="<?= APP_URL ?>/logout.php"
               class="btn btn-outline btn-block"
               style="color:#ff4757;border-color:#ff4757">
                🚪 Keluar
            </a>
        </div>

    </aside>

    <!-- Main -->
    <div class="main-content">

        <div class="topbar">

            <div class="topbar-title">
                Detail Pesanan
            </div>

            <a href="<?= APP_URL ?>/admin/requests.php"
               class="btn btn-outline btn-sm">
                ← Kembali
            </a>

        </div>

        <div class="page-content">

            <?php showFlash(); ?>

            <div class="detail-grid"
                 style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

                <!-- LEFT -->
                <div>

                    <!-- STATUS CARD -->
                    <div class="card mb-6">

                        <div class="card-body">

                            <div style="font-size:12px;color:var(--gray);margin-bottom:4px">
                                Kode Pesanan
                            </div>

                            <div style="font-size:22px;font-weight:800;margin-bottom:12px">
                                <?= sanitize($order['order_code']) ?>
                            </div>

                            <div>
                                <?= getStatusBadge($order['status']) ?>
                            </div>

                            <?php if ($order['status'] !== 'dibatalkan'): ?>

                                <div class="progress-track">

                                    <?php foreach ($statuses as $index => $status): ?>

                                        <div class="progress-step <?= $index < $current_index ? 'done' : ($index == $current_index ? 'current' : '') ?>">

                                            <div class="step-circle">
                                                <?= $status_icons[$status] ?>
                                            </div>

                                            <div class="step-label">
                                                <?= ucfirst($status) ?>
                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                    <!-- ORDER INFO -->
                    <div class="card">

                        <div class="card-header">
                            <div class="card-title">
                                Info Pesanan
                            </div>
                        </div>

                        <div class="card-body">

                            <div class="detail-row">
                                <span class="detail-label">Layanan</span>
                                <span class="detail-value">
                                    <?= sanitize($order['service_name']) ?>
                                </span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">Berat</span>
                                <span class="detail-value">
                                    <?= $order['weight_kg'] ?> kg
                                </span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">Harga / kg</span>
                                <span class="detail-value">
                                    <?= formatRupiah($order['price_per_kg']) ?>
                                </span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">Total Harga</span>
                                <span class="detail-value"
                                      style="color:var(--primary);font-size:18px">
                                    <?= formatRupiah($order['total_price']) ?>
                                </span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">Estimasi Selesai</span>
                                <span class="detail-value">
                                    <?= $order['estimated_days'] ?> hari
                                </span>
                            </div>

                            <?php if (!empty($order['pickup_date'])): ?>

                                <div class="detail-row">
                                    <span class="detail-label">Tanggal Ambil</span>
                                    <span class="detail-value">
                                        <?= formatDate($order['pickup_date']) ?>
                                    </span>
                                </div>

                            <?php endif; ?>

                            <?php if (!empty($order['notes'])): ?>

                                <div class="detail-row">
                                    <span class="detail-label">Catatan</span>
                                    <span class="detail-value">
                                        <?= sanitize($order['notes']) ?>
                                    </span>
                                </div>

                            <?php endif; ?>

                            <div class="detail-row">
                                <span class="detail-label">Tanggal Pesan</span>
                                <span class="detail-value">
                                    <?= formatDateTime($order['created_at']) ?>
                                </span>
                            </div>

                        </div>

                    </div>

                </div>

                <!-- RIGHT -->
                <div>

                    <!-- CUSTOMER -->
                    <div class="card mb-6">

                        <div class="card-header">
                            <div class="card-title">
                                👤 Info Pelanggan
                            </div>
                        </div>

                        <div class="card-body">

                            <div class="detail-row">
                                <span class="detail-label">Nama</span>
                                <span class="detail-value">
                                    <?= sanitize($order['user_name']) ?>
                                </span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">Email</span>
                                <span class="detail-value">
                                    <?= sanitize($order['user_email']) ?>
                                </span>
                            </div>

                        </div>

                    </div>

                    <!-- UPDATE STATUS -->
                    <?php if (
                        $order['status'] !== 'diambil' &&
                        $order['status'] !== 'dibatalkan'
                    ): ?>

                        <div class="card">

                            <div class="card-header">
                                <div class="card-title">
                                    🔄 Update Status
                                </div>
                            </div>

                            <div class="card-body">

                                <p class="text-muted fs-sm mb-4">
                                    Klik tombol untuk mengubah status pesanan.
                                    Pelanggan akan mendapat notifikasi otomatis.
                                </p>

                                <form method="POST"
                                      style="display:flex;flex-direction:column;gap:10px">

                                    <?php foreach (($next_statuses[$order['status']] ?? []) as $next_status): ?>

                                        <button
                                            type="submit"
                                            name="status"
                                            value="<?= $next_status ?>"
                                            class="status-btn"
                                            style="<?= $button_styles[$next_status] ?>"
                                            onclick="return confirm('Ubah status ke <?= $next_status ?>?')"
                                        >
                                            <?= $button_labels[$next_status] ?>
                                        </button>

                                    <?php endforeach; ?>

                                </form>

                            </div>

                        </div>

                    <?php else: ?>

                        <div class="card">

                            <div class="card-body text-center text-muted">

                                <div style="font-size:36px;margin-bottom:8px">

                                    <?= $order['status'] === 'diambil' ? '✅' : '❌' ?>

                                </div>

                                <p>
                                    Pesanan ini sudah
                                    <strong><?= $order['status'] ?></strong>.
                                </p>

                            </div>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>