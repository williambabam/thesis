<?php
declare(strict_types=1);

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
// This removes subfolders to find the main project folder
$root = preg_replace('#/(admin|guest|rooms).*$#', '', $scriptDir);
$root = rtrim($root, '/'); 

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database credentials
$DB_HOST = '127.0.0.1';
$DB_NAME = 'resort_db';
$DB_USER = 'root';
$DB_PASS = '';

$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];

try {
  $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
  http_response_code(500);
  exit('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_field(): string {
  return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf(): void {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
      http_response_code(400); exit('Invalid CSRF token');
    }
  }
}

// Authentication helpers
function is_logged_in(): bool { return isset($_SESSION['user']); }

function require_login(): void {
  global $root;
  if (!is_logged_in()) {
    header('Location: ' . $root . '/login.php');
    exit;
  }
}
?>