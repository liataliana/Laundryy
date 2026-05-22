<?php
session_start();

// Define constants
define('APP_NAME', 'Laundry Management System');
define('APP_URL', 'http://localhost/LMS');

// Database configuration
$host = 'localhost';
$dbname = 'lms';
$username = 'root';
$password = ''; // Laragon default: no password for root

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}