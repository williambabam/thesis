<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/google_config.php';

if (is_logged_in()) { header('Location: dashboard.php'); exit; }
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if (empty($full_name)) $errors[] = 'Full Name is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    elseif (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    elseif ($password !== $confirm) $errors[] = 'Passwords do not match.';
    else {
        // Check if Email exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $errors[] = 'This email is already registered.';
        } else {
            // Create Account Directly
            $hash = password_hash($password, PASSWORD_DEFAULT);
            // If you added a 'username' column previously, we fill it with the email prefix to avoid errors
            // If you removed it, you can remove the 'username' part from the query
            $username_fallback = explode('@', $email)[0] . rand(100,999); 

            $stmt = $pdo->prepare('INSERT INTO users (full_name, email, username, password_hash, role, is_active) VALUES (?, ?, ?, ?, "guest", 1)');
            
            try {
                $stmt->execute([$full_name, $email, $username_fallback, $hash]);
                
                $_SESSION['user'] = [
                    'id' => $pdo->lastInsertId(),
                    'full_name' => $full_name,
                    'email' => $email,
                    'role' => 'guest'
                ];
                header('Location: dashboard.php');
                exit;
            } catch (PDOException $e) {
                // If 'username' column doesn't exist, fallback query without it
                $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role, is_active) VALUES (?, ?, ?, "guest", 1)');
                try {
                    $stmt->execute([$full_name, $email, $hash]);
                    $_SESSION['user'] = ['id'=>$pdo->lastInsertId(), 'full_name'=>$full_name, 'email'=>$email, 'role'=>'guest'];
                    header('Location: dashboard.php'); exit;
                } catch (Exception $ex) {
                    $errors[] = 'System error. Please try again.';
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Register - ResortEase</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #7c3aed; }
    body { 
        font-family: 'Inter', sans-serif; 
        background: url('https://images.unsplash.com/photo-1540541338287-41700207dee6?q=80&w=2070&auto=format&fit=crop') no-repeat center center fixed;
        background-size: cover;
        display: flex; flex-direction: column; min-height: 100vh; margin: 0; position: relative;
    }
    body::before { content: ""; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); z-index: -1; }
    .main-content { flex: 1; display: flex; align-items: center; justify-content: center; padding: 80px 20px; }
    .brand-header { position: absolute; top: 25px; left: 40px; font-weight: 800; font-size: 24px; color: white; text-decoration: none; letter-spacing: -0.5px; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
    .brand-header span { color: #a78bfa; }
    .register-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 40px; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.2); width: 100%; max-width: 480px; border: 1px solid rgba(255,255,255,0.2); }
    .btn-primary { background-color: var(--primary); border: none; font-weight: 600; padding: 12px; }
    .btn-primary:hover { background-color: #6d28d9; }
    .btn-google { border: 1px solid #e5e7eb; color: #374151; background: white; font-weight: 600; padding: 10px; transition:0.2s; }
    .btn-google:hover { background: #f8fafc; }
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
    <div class="register-card">
      <div class="text-center mb-4">
        <h4 class="fw-bold text-dark">Create account</h4>
        <p class="text-muted small">Join ResortEase to find the best deals</p>
      </div>
      <?php if ($errors) echo '<div class="alert alert-danger py-2 small text-center">' . implode('<br>', $errors) . '</div>'; ?>
      
      <form method="post">
        <div class="mb-3"><label class="form-label small fw-bold text-secondary">Full Name</label><input type="text" name="full_name" class="form-control" required></div>
        <div class="mb-3"><label class="form-label small fw-bold text-secondary">Email Address</label><input type="email" name="email" class="form-control" required></div>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label small fw-bold text-secondary">Password</label><input type="password" name="password" class="form-control" required></div>
            <div class="col-md-6 mb-3"><label class="form-label small fw-bold text-secondary">Confirm</label><input type="password" name="confirm_password" class="form-control" required></div>
        </div>
        <div class="mb-4 text-muted small"><i class="fas fa-info-circle me-1"></i> Password must be at least 8 characters.</div>
        <div class="d-grid gap-2"><button type="submit" class="btn btn-primary">Register</button></div>
      </form>

      <div class="text-center mt-3 mb-3"><p class="mb-0 small">Already have an account? <a href="login.php" class="text-decoration-none fw-bold text-primary" style="color:var(--primary)!important;">Log in</a></p></div>
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