<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();

if (!in_array($_SESSION['user']['role'], ['resort_admin', 'super_admin'])) {
    header('Location: ../dashboard.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_method'])) {
    verify_csrf();
    
    $provider = trim($_POST['provider']);
    $acc_name = trim($_POST['account_name']);
    $acc_num  = trim($_POST['account_number']);
    
    if ($provider && $acc_num) {
        try {
            // Handle Upload
            $qr_path = null;
            if (!empty($_FILES['qr_code']['name'])) {
                $uploadDir = __DIR__ . '/../uploads/qr/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $filename = time() . '_' . basename($_FILES['qr_code']['name']);
                if (move_uploaded_file($_FILES['qr_code']['tmp_name'], $uploadDir . $filename)) {
                    $qr_path = 'uploads/qr/' . $filename;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO payment_methods (provider_name, account_name, account_number, qr_code_path, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$provider, $acc_name, $acc_num, $qr_path]);
            
            $_SESSION['success'] = "Payment method added.";
        } catch (Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        header('Location: payment_settings.php'); exit;
    }
}

// --- HANDLE DELETE ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM payment_methods WHERE id = ?")->execute([$id]);
    $_SESSION['success'] = "Method removed.";
    header('Location: payment_settings.php'); exit;
}

$methods = $pdo->query("SELECT * FROM payment_methods ORDER BY id DESC")->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <title>Payments - ResortEase</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #7c3aed; --bg: #f5f3ff; }
    body { background-color: var(--bg); font-family: 'Inter', sans-serif; display: flex; flex-direction: column; min-height: 100vh; }
    .main-content { flex: 1; }
    
    .content-card { background: white; border-radius: 16px; border: 1px solid #ede9fe; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
    .qr-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; background: #f9fafb; display: flex; align-items: center; justify-content: center; }
    
    .btn-primary { background-color: var(--primary); border: none; padding: 10px 20px; font-weight: 600; }
    .btn-primary:hover { background-color: #6d28d9; }
    
    .footer-dark { background-color: #191e1f; color: #efefef; padding: 40px 0; font-size: 14px; margin-top: 60px; }
    .footer-brand { font-weight: 800; font-size: 24px; color: white; text-decoration: none; display: block; margin-bottom: 20px; }
    .legal-link { color: #d1d5db; text-decoration: none; }
    .country-select { display: inline-flex; align-items: center; gap: 10px; border: 1px solid #4b5563; padding: 8px 12px; border-radius: 4px; color: #fff; font-size: 0.9rem; cursor: pointer; }
    .social-icons a { color: white; margin-right: 15px; font-size: 1.2rem; opacity: 0.8; }
  </style>
</head>
<body>
<?php include __DIR__ . '/../navbar.php'; ?>

<div class="container py-4 main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">Payment Methods</h2>
            <p class="text-muted small mb-0">Configure accounts for guest payments</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addMethodModal">
            <i class="fas fa-plus me-2"></i> Add Method
        </button>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success py-2 small fw-bold rounded-3 mb-4"><i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger py-2 small fw-bold rounded-3 mb-4"><i class="fas fa-exclamation-triangle me-2"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="content-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3">Provider</th>
                        <th>Account Details</th>
                        <th>QR Code</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($methods)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">No payment methods added yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($methods as $m): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-primary"><?php echo htmlspecialchars($m['provider_name']); ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($m['account_number']); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars($m['account_name']); ?></div>
                            </td>
                            <td>
                                <?php if($m['qr_code_path']): ?>
                                    <img src="../<?php echo htmlspecialchars($m['qr_code_path']); ?>" class="qr-thumb">
                                <?php else: ?>
                                    <span class="badge bg-light text-secondary border">No QR</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <a href="?delete=<?php echo $m['id']; ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="return confirm('Delete this payment method?');">Remove</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addMethodModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">New Payment Method</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-0">
                <form method="post" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="add_method" value="1">
                    
                    <div class="mb-3">
                        <label class="small fw-bold text-secondary">Provider Name</label>
                        <input type="text" name="provider" class="form-control rounded-3" placeholder="e.g. GCash, BDO" required>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="small fw-bold text-secondary">Account Name</label>
                            <input type="text" name="account_name" class="form-control rounded-3" required>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-secondary">Account Number</label>
                            <input type="text" name="account_number" class="form-control rounded-3" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold text-secondary">QR Code Image (Optional)</label>
                        <input type="file" name="qr_code" class="form-control rounded-3" accept="image/*">
                        <div class="form-text small">Upload a screenshot of your QR.</div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary rounded-pill py-2 fw-bold">Save Payment Method</button>
                    </div>
                </form>
            </div>
        </div>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>