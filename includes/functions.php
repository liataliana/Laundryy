<?php
/**
 * Redirect to a given URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Set a flash message in the session
 */
function setFlash($key, $value) {
    $_SESSION['flash'][$key] = $value;
}

/**
 * Show and remove flash messages
 */
function showFlash() {
    if (!empty($_SESSION['flash'])) {
        foreach ($_SESSION['flash'] as $key => $value) {
            $class = $key === 'success' ? 'alert-success' : 'alert-error';
            echo "<div class=\"alert $class\">";
            echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            echo "</div>";
        }
        unset($_SESSION['flash']);
    }
}

/**
 * Sanitize output for HTML
 */
function sanitize($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Check if admin is logged in
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

/**
 * Require admin login, redirect to login if not
 */
function requireAdmin() {
    if (!isAdminLoggedIn()) {
        redirect(APP_URL . '/login.php');
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Require user login, redirect to login if not
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect(APP_URL . '/user/login.php');
    }
}

/**
 * Format number as Rupiah currency
 */
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Get status badge HTML
 */
function getStatusBadge($status) {
    $colors = [
        'menunggu' => '#ffa502',
        'diproses' => '#1a6bff',
        'selesai'  => '#00c896',
        'diambil'  => '#6b7a99',
        'dibatalkan' => '#ff4757',
    ];
    $labels = [
        'menunggu' => 'Menunggu',
        'diproses' => 'Diproses',
        'selesai'  => 'Selesai',
        'diambil'  => 'Diambil',
        'dibatalkan' => 'Dibatalkan',
    ];
    $color = $colors[$status] ?? '#6b7a99';
    $label = $labels[$status] ?? $status;
    return "<span style=\"background:{$color};color:white;padding:2px 8px;border-radius:4px;font-size:12px\">{$label}</span>";
}

/**
 * Format date from Y-m-d to d/m/Y
 */
function formatDate($date) {
    if (!$date) return '-';
    return date('d/m/Y', strtotime($date));
}

/**
 * Format datetime from Y-m-d H:i:s to d/m/Y H:i
 */
function formatDateTime($datetime) {
    if (!$datetime) return '-';
    return date('d/m/Y H:i', strtotime($datetime));
}