<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (isset($_SESSION['user'])) { header('Location: dashboard.php'); exit; }
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if (empty($full_name) || empty($username) || empty($email)) $errors[] = 'All fields required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';
    elseif (strlen($password) < 8) $errors[] = 'Password must be 8+ chars.';
    elseif ($password !== $confirm) $errors[] = 'Passwords do not match.';
    elseif (empty($_FILES['business_permit']['name']) || empty($_FILES['valid_id']['name'])) {
        $errors[] = 'Please upload both Business Permit and Valid ID.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username=? OR email=?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Username or Email already taken.';
        } else {
            // 1. Handle File Uploads
            $uploadDir = __DIR__ . '/uploads/documents/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $permitName = time() . '_permit_' . basename($_FILES['business_permit']['name']);
            $idName = time() . '_id_' . basename($_FILES['valid_id']['name']);
            
            if (move_uploaded_file($_FILES['business_permit']['tmp_name'], $uploadDir . $permitName) && 
                move_uploaded_file($_FILES['valid_id']['tmp_name'], $uploadDir . $idName)) {
                
                // 2. Generate OTP
                $otp = rand(100000, 999999);
                
                // 3. Save Data to Session (Temporary)
                $_SESSION['partner_temp'] = [
                    'full_name' => $full_name,
                    'username' => $username,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'permit_path' => 'uploads/documents/' . $permitName,
                    'id_path' => 'uploads/documents/' . $idName,
                    'otp' => $otp
                ];

                // 4. Send Email
                require_once 'mail_helper.php';
                if (sendOTPEmail($email, $full_name, $otp)) {
                    header('Location: partner_verify.php'); exit;
                } else {
                    $errors[] = "Failed to send email. Check your internet.";
                }
            } else {
                $errors[] = "Failed to upload documents.";
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <title>Partner Application - ResortEase</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; background-color: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 0; }
    .card-custom { background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 40px; width: 100%; max-width: 600px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
    .brand-logo { font-weight: 800; font-size: 1.5rem; color: #374151; text-decoration: none; display: block; text-align: center; margin-bottom: 10px; }
    .brand-logo span { color: #7c3aed; }
    .btn-primary { background-color: #7c3aed; border: none; font-weight: 600; padding: 10px; }
    .btn-primary:hover { background-color: #6d28d9; }
  </style>
</head>
<body>

<div class="card-custom">
    <a href="index.php" class="brand-logo">Resort<span>Ease</span></a>
    <h4 class="fw-bold text-center mb-4 text-dark">Partner Application</h4>
    <?php if ($errors) echo '<div class="alert alert-danger text-center small">' . implode('<br>', $errors) . '</div>'; ?>

    <form method="post" enctype="multipart/form-data">
        <h6 class="fw-bold text-secondary mb-3 small text-uppercase border-bottom pb-2">1. Owner Info</h6>
        <div class="mb-3"><label class="form-label small fw-bold">Full Name</label><input type="text" name="full_name" class="form-control" required></div>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label small fw-bold">Email</label><input type="email" name="email" class="form-control" required></div>
            <div class="col-md-6 mb-3"><label class="form-label small fw-bold">Username</label><input type="text" name="username" class="form-control" required></div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label small fw-bold">Password</label><input type="password" name="password" class="form-control" required></div>
            <div class="col-md-6 mb-3"><label class="form-label small fw-bold">Confirm</label><input type="password" name="confirm_password" class="form-control" required></div>
        </div>

        <h6 class="fw-bold text-secondary mb-3 small text-uppercase border-bottom pb-2 mt-3">2. Documents</h6>
        <div class="mb-3">
            <label class="form-label small fw-bold">Business Permit</label>
            <input type="file" name="business_permit" class="form-control" accept="image/*,application/pdf" required>
        </div>
        <div class="mb-4">
            <label class="form-label small fw-bold">Valid ID</label>
            <input type="file" name="valid_id" class="form-control" accept="image/*,application/pdf" required>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-primary">Next: Verify Email</button>
        </div>
    </form>
    <div class="text-center mt-3 small"><a href="partner_login.php" class="text-decoration-none fw-bold" style="color:#7c3aed;">Login here</a></div>
</div>
</body>
</html>