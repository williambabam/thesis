<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Key change: Only allow admin roles to log in here
            if (in_array($user['role'], ['resort_admin', 'super_admin'])) {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                ];
                header('Location: index.php'); // Redirect to admin dashboard
                exit;
            } else {
                $error = 'Access denied. This login is for administrators only.';
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login - ResortEase</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #7c3aed; }
    body {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      background-color: #f8fafc;
      font-family: 'Inter', sans-serif;
    }
    .login-card {
      width: 100%;
      max-width: 400px;
      padding: 2.5rem;
      background: white;
      border-radius: 1rem;
      border: 1px solid #e2e8f0;
      box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    }
    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 0.25rem rgb(124 58 237 / 25%);
    }
    .btn-primary {
      background-color: var(--primary);
      border-color: var(--primary);
    }
  </style>
</head>
<body>
  <div class="login-card">
    <h2 class="fw-bold text-center mb-1">Admin Portal</h2>
    <p class="text-center text-muted mb-4">Sign in to manage your resort.</p>
    
    <?php if ($error): ?>
      <div class="alert alert-danger small"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label for="email" class="form-label fw-bold small">Email address</label>
        <input type="email" class="form-control" id="email" name="email" required>
      </div>
      <div class="mb-4">
        <label for="password" class="form-label fw-bold small">Password</label>
        <input type="password" class="form-control" id="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Sign In</button>
    </form>
  </div>
</body>
</html>