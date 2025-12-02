<?php
require __DIR__ . '/../config.php';
require_login();
$role = $_SESSION['user']['role'];
if ($role !== 'resort_admin' && $role !== 'super_admin') {
  http_response_code(403);
  exit('Access denied');
}

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
  $stmt = $pdo->prepare('DELETE FROM rooms WHERE id = ?');
  $stmt->execute([$id]);
}
header('Location: index.php');
exit;
