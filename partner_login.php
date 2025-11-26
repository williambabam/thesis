<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (is_logged_in()) { header('Location: dashboard.php'); exit; }
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $errors[] = 'Please enter both username and password.';
    } else {
        // Check only RESORT ADMINS or SUPER ADMINS here
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND role IN ("resort_admin", "super_admin")');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // CHECK APPROVAL STATUS
            if ($user['approval_status'] === 'pending') {
                $errors[] = 'Your account is still under review by the Super Admin.';
            } elseif ($user['approval_status'] === 'rejected') {
                $errors[] = 'Your application was rejected. Please contact support.';
            } else {
                $_SESSION['user'] = $user;
                header('Location: dashboard.php');
                exit;
            }
        } else {
            $errors[] = 'Invalid credentials or not a partner account.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <title>Partner Login - ResortEase</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .login-card { background: white; border: 1px solid #cbd5e1; border-radius: 12px; padding: 40px; width: 100%; max-width: 400px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
    .brand-logo { font-weight: 800; font-size: 1.5rem; color: #374151; text-decoration: none; display: block; text-align: center; margin-bottom: 20px; }
    .brand-logo span { color: #7c3aed; }
  </style>
</head>
<body>

<div class="login-card">
    <a href="index.php" class="brand-logo">Resort<span>Ease</span></a>
    <h5 class="fw-bold text-center mb-4">Partner Portal</h5>
    
    <?php if ($errors) echo '<div class="alert alert-danger py-2 small text-center">' . implode('<br>', $errors) . '</div>'; ?>

    <form method="post">
        <div class="mb-3">
            <label class="form-label small fw-bold text-secondary">Username</label>
            <input type="text" name="username" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label small fw-bold text-secondary">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="d-grid mt-4">
            <button type="submit" class="btn btn-primary" style="background-color: #7c3aed; border:none; font-weight:600; padding:10px;">Login to Dashboard</button>
        </div>
    </form>

    <div class="text-center mt-3 small">
        <a href="partner_register.php" class="text-decoration-none text-secondary">Apply to be a partner</a>
    </div>
</div>

</body>
</html>