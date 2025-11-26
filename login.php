<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/google_config.php';

if (is_logged_in()) { header('Location: dashboard.php'); exit; }
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $errors[] = 'Please enter both username and password.';
    } else {
        // SEARCH BY USERNAME
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user'] = $user;
            header('Location: ' . (($user['role'] === 'resort_admin' || $user['role'] === 'super_admin') ? 'dashboard.php' : 'guest/browse.php'));
            exit;
        } else {
            $errors[] = 'Invalid username or password.';
        }
    }
}
$googleError = $_SESSION['error'] ?? ''; unset($_SESSION['error']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Log in - ResortEase</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #7c3aed; }
    
    /* Background & Layout */
    body { 
        font-family: 'Inter', sans-serif; 
        background: url('https://images.unsplash.com/photo-1540541338287-41700207dee6?q=80&w=2070&auto=format&fit=crop') no-repeat center center fixed;
        background-size: cover;
        display: flex; 
        flex-direction: column; 
        min-height: 100vh; 
        margin: 0; 
        position: relative;
    }
    body::before { content: ""; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); z-index: -1; }
    
    .main-content { flex: 1; display: flex; align-items: center; justify-content: center; padding: 80px 20px; }
    
    /* Brand Header */
    .brand-header { position: absolute; top: 25px; left: 40px; font-weight: 800; font-size: 24px; color: white; text-decoration: none; letter-spacing: -0.5px; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
    .brand-header span { color: #a78bfa; }

    /* Card Design */
    .login-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 40px; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.2); width: 100%; max-width: 420px; border: 1px solid rgba(255,255,255,0.2); }
    
    /* Buttons */
    .btn-primary { background-color: var(--primary); border: none; font-weight: 600; padding: 12px; }
    .btn-primary:hover { background-color: #6d28d9; }
    .btn-google { border: 1px solid #e5e7eb; color: #374151; background: white; font-weight: 600; padding: 10px; transition:0.2s; }
    .btn-google:hover { background: #f8fafc; }
    
    /* Footer */
    .footer-dark { background-color: #191e1f; color: #efefef; padding: 40px 0; font-size: 14px; width: 100%; margin-top: auto; position: relative; z-index: 2; }
    .footer-brand { font-weight: 800; font-size: 24px; color: white; text-decoration: none; display: block; margin-bottom: 15px; }
    .country-select { display: inline-flex; align-items: center; gap: 10px; border: 1px solid #4b5563; padding: 8px 12px; border-radius: 4px; color: #fff; font-size: 0.9rem; cursor: pointer; }
    .social-icons a { color: white; margin-right: 15px; font-size: 1.2rem; opacity: 0.8; transition: 0.2s; }
    .legal-link { color: #9ca3af; text-decoration: none; transition: 0.2s; }
    .legal-link:hover { color: white; text-decoration: underline; }
  </style>
</head>
<body>

<a href="index.php" class="brand-header">Resort<span>Ease</span></a>

<div class="main-content">
    <div class="login-card">
      <div class="text-center mb-4">
        <h4 class="fw-bold text-dark">Welcome back</h4>
        <p class="text-muted small">Log in to manage your bookings</p>
      </div>
      <?php if ($errors) echo '<div class="alert alert-danger py-2 small text-center">' . implode('<br>', $errors) . '</div>'; ?>
      
      <form method="post">
        <div class="mb-3">
            <label class="form-label small fw-bold text-secondary">Username</label>
            <input type="text" name="username" class="form-control" required placeholder="Enter your username">
        </div>
        <div class="mb-3">
            <label class="form-label small fw-bold text-secondary">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="d-grid gap-2 mt-4"><button type="submit" class="btn btn-primary">Log in</button></div>
      </form>

      <div class="text-center mt-3 mb-3"><p class="mb-0 small">Don't have an account? <a href="register.php" class="text-decoration-none fw-bold text-primary" style="color:var(--primary)!important;">Register</a></p></div>
      <div class="position-relative my-4"><hr class="text-muted opacity-25"><span class="position-absolute top-50 start-50 translate-middle bg-white px-2 text-muted small">OR</span></div>
      <div class="d-grid"><a href="<?php echo getGoogleLoginUrl(); ?>" class="btn btn-google"><img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" alt="Google" width="18" class="me-2"> Continue with Google</a></div>
    </div>
</div>

<footer class="footer-dark">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <a href="#" class="footer-brand">ResortEase</a>
                <div class="country-select d-inline-flex mb-3"><span>🇵🇭</span> <span class="ms-2">Philippines (Pilipinas)</span> <i class="fas fa-chevron-down ms-2 small"></i></div>
                <div class="social-icons"><a href="#"><i class="fab fa-facebook"></i></a><a href="#"><i class="fab fa-twitter"></i></a><a href="#"><i class="fab fa-instagram"></i></a></div>
            </div>
            <div class="col-md-6 text-md-end mt-4 mt-md-0">
                <h6 class="text-white fw-bold mb-2">Legal</h6>
                <a href="https://privacy.gov.ph/data-privacy-act/" target="_blank" class="legal-link">Data Privacy Act of 2012 <i class="fas fa-external-link-alt small ms-1"></i></a>
            </div>
        </div>
        <div class="text-center text-muted small mt-5 pt-4 border-top border-secondary">&copy; <?php echo date('Y'); ?> ResortEase Philippines. All rights reserved.</div>
    </div>
</footer>
</body>
</html>