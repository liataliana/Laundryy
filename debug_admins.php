<?php
// Temporary debug page — remove after troubleshooting
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug Admins</h2>";
echo "<p><strong>APP_URL:</strong> " . (defined('APP_URL') ? APP_URL : 'not defined') . "</p>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<h3>\$_SESSION</h3>";
echo '<pre>' . htmlspecialchars(print_r($_SESSION, true)) . '</pre>';

try {
    $stmt = $pdo->query("SELECT id, name, email, password, created_at FROM admins");
    $admins = $stmt->fetchAll();
    echo "<h3>Admins table</h3>";
    echo '<pre>' . htmlspecialchars(print_r($admins, true)) . '</pre>';
} catch (Exception $e) {
    echo "<p style=\"color:red\">DB error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p>When you visit this page in the browser, confirm there is an admin row with email <strong>admin@lms.com</strong>.</p>";
echo "<p>Then try logging in at <a href=\"" . APP_URL . "/login.php\">Login</a> (open in incognito to avoid session conflicts).</p>";

?>
