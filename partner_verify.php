<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['partner_temp'])) { header('Location: partner_register.php'); exit; }
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['otp'] ?? '');
    $temp = $_SESSION['partner_temp'];

    if ($code == $temp['otp']) {
        try {
            $pdo->beginTransaction();

            // 1. Create User (Status: Pending)
            $stmt = $pdo->prepare('INSERT INTO users (full_name, username, email, password_hash, role, approval_status, is_active) VALUES (?, ?, ?, ?, "resort_admin", "pending", 1)');
            $stmt->execute([$temp['full_name'], $temp['username'], $temp['email'], $temp['password']]);
            $user_id = $pdo->lastInsertId();

            // 2. Save Documents
            $docStmt = $pdo->prepare('INSERT INTO partner_documents (user_id, document_type, file_path) VALUES (?, ?, ?)');
            $docStmt->execute([$user_id, 'Business Permit', $temp['permit_path']]);
            $docStmt->execute([$user_id, 'Valid ID', $temp['id_path']]);

            $pdo->commit();
            
            unset($_SESSION['partner_temp']);
            // Redirect to Login with Success Message
            $_SESSION['success_msg'] = "Verification successful! Your application is now pending admin approval.";
            header('Location: partner_login.php'); exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Database Error: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid OTP Code.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <title>Verify Email - ResortEase</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body { font-family: 'Inter', sans-serif; background-color: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .otp-card { background: white; border: 1px solid #e2e8f0; padding: 40px; border-radius: 16px; width: 100%; max-width: 400px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
    .btn-primary { background-color: #7c3aed; border: none; font-weight: 600; padding: 12px; width: 100%; }
    input { font-size: 1.5rem; letter-spacing: 5px; text-align: center; }
  </style>
</head>
<body>

<div class="otp-card">
    <i class="fas fa-envelope-open-text fa-3x mb-3" style="color: #7c3aed;"></i>
    <h4 class="fw-bold text-dark">Verify Email</h4>
    <p class="text-muted small mb-4">
        We sent a code to <strong><?php echo htmlspecialchars($_SESSION['partner_temp']['email']); ?></strong>.
    </p>

    <?php if ($error) echo "<div class='alert alert-danger py-2 small'>$error</div>"; ?>

    <form method="post">
        <div class="mb-4">
            <input type="text" name="otp" class="form-control form-control-lg" placeholder="123456" maxlength="6" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary">Verify & Submit</button>
    </form>
    
    <div class="mt-3">
        <a href="partner_register.php" class="small text-decoration-none text-secondary">Change Email</a>
    </div>
</div>

</body>
</html>