<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();

if (!in_array($_SESSION['user']['role'], ['resort_admin', 'super_admin'])) {
    header('Location: ../dashboard.php'); exit;
}

$user_id = $_SESSION['user']['id'];
$activeTab = $_GET['tab'] ?? 'general';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_general'])) {
        $settings = [
            'resort_name'   => $_POST['resort_name'],
            'contact_email' => $_POST['contact_email'],
            'phone'         => $_POST['phone'],
            'address'       => $_POST['address']
        ];
        foreach ($settings as $key => $val) {
            $pdo->prepare("INSERT INTO resort_settings (setting_key, setting_value, user_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$key, $val, $user_id, $val]);
        }
        $_SESSION['success'] = "General settings saved.";
        header('Location: resort_cms.php?tab=general'); exit;
    }

    if (isset($_POST['save_policies'])) {
        $policies = [
            'cancellation_policy' => $_POST['cancellation_policy'],
            'refund_percentage'   => $_POST['refund_percentage']
        ];
        foreach ($policies as $key => $val) {
            $pdo->prepare("INSERT INTO resort_settings (setting_key, setting_value, user_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$key, $val, $user_id, $val]);
        }
        $_SESSION['success'] = "Policies updated.";
        header('Location: resort_cms.php?tab=policies'); exit;
    }
}

// Fetch ONLY this user's settings
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM resort_settings WHERE user_id = ?");
$stmt->execute([$user_id]);
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!doctype html>
<html lang="en">
<head>
  <title>Settings - ResortEase</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #7c3aed; --bg: #f8fafc; }
    body { background-color: var(--bg); font-family: 'Inter', sans-serif; display: flex; flex-direction: column; min-height: 100vh; }
    .main-content { flex: 1; }
    .nav-pills .nav-link { color: #64748b; font-weight: 600; border-radius: 10px; padding: 10px 25px; text-decoration: none; display: inline-block; margin-right: 10px; }
    .nav-pills .nav-link.active { background-color: var(--primary); color: white; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3); }
    .nav-pills .nav-link:hover:not(.active) { background-color: #e2e8f0; color: #334155; }
    .form-card { background: white; border-radius: 16px; border: 1px solid #ede9fe; box-shadow: 0 4px 10px rgba(0,0,0,0.02); padding: 30px; }
    .btn-primary { background-color: var(--primary); border: none; padding: 10px 25px; font-weight: 600; }
    .btn-primary:hover { background-color: #6d28d9; }
    .footer-dark { background-color: #191e1f; color: #efefef; padding: 40px 0; margin-top: 60px; }
    .footer-brand { font-weight: 800; font-size: 24px; color: white; text-decoration: none; }
    .country-select { display: inline-flex; align-items: center; gap: 10px; border: 1px solid #4b5563; padding: 8px 12px; border-radius: 4px; color: #fff; cursor: pointer; }
    .social-icons a { color: white; margin-right: 15px; font-size: 1.2rem; opacity: 0.8; }
    .legal-link { color: #9ca3af; text-decoration: none; }
  </style>
</head>
<body>
<?php include __DIR__ . '/../navbar.php'; ?>

<div class="container py-4 main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h2 class="fw-bold text-dark mb-1">Resort Settings</h2><p class="text-muted small mb-0">Manage branding and policies</p></div>
        <?php if (isset($_SESSION['success'])): ?><div class="alert alert-success py-1 px-3 rounded-pill mb-0 small fw-bold"><i class="fas fa-check-circle me-1"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div><?php endif; ?>
    </div>

    <div class="nav nav-pills mb-4">
        <a href="?tab=general" class="nav-link <?php echo $activeTab === 'general' ? 'active' : ''; ?>"><i class="fas fa-info-circle me-2"></i>General Info</a>
        <a href="?tab=policies" class="nav-link <?php echo $activeTab === 'policies' ? 'active' : ''; ?>"><i class="fas fa-file-contract me-2"></i>Policies</a>
    </div>

    <div class="tab-content">
        <?php if ($activeTab === 'general'): ?>
        <div class="form-card">
            <form method="post">
                <input type="hidden" name="save_general" value="1">
                <h5 class="fw-bold mb-4 text-secondary">Resort Information</h5>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label small fw-bold text-secondary">Resort Name</label><input type="text" name="resort_name" class="form-control" value="<?php echo htmlspecialchars($settings['resort_name'] ?? ''); ?>"></div>
                    <div class="col-md-6"><label class="form-label small fw-bold text-secondary">Contact Email</label><input type="email" name="contact_email" class="form-control" value="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>"></div>
                    <div class="col-md-6"><label class="form-label small fw-bold text-secondary">Phone Number</label><input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($settings['phone'] ?? ''); ?>"></div>
                    <div class="col-md-6"><label class="form-label small fw-bold text-secondary">Address</label><input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($settings['address'] ?? ''); ?>"></div>
                </div>
                <hr class="my-4">
                <div class="text-end"><button type="submit" class="btn btn-primary rounded-pill shadow-sm px-4">Save General Info</button></div>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($activeTab === 'policies'): ?>
        <div class="form-card">
            <form method="post">
                <input type="hidden" name="save_policies" value="1">
                <h5 class="fw-bold mb-4 text-secondary">Booking & Cancellation Policies</h5>
                <div class="mb-4">
                    <label class="form-label small fw-bold text-secondary">Cancellation Policy Text</label>
                    <textarea name="cancellation_policy" class="form-control" rows="6" placeholder="e.g., Cancellations made 48 hours before check-in are fully refundable..."><?php echo htmlspecialchars($settings['cancellation_policy'] ?? ''); ?></textarea>
                    <div class="form-text">This text will be displayed to guests during checkout.</div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-secondary">Refund Percentage (%)</label>
                        <div class="input-group"><input type="number" name="refund_percentage" class="form-control" min="0" max="100" value="<?php echo htmlspecialchars($settings['refund_percentage'] ?? '100'); ?>"><span class="input-group-text">%</span></div>
                        <div class="form-text">Default amount to refund for approved cancellations.</div>
                    </div>
                </div>
                <hr class="my-4">
                <div class="text-end"><button type="submit" class="btn btn-primary rounded-pill shadow-sm px-4">Save Policies</button></div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<footer class="footer-dark"><div class="container"><div class="row align-items-center"><div class="col-md-6"><a href="#" class="footer-brand">ResortEase</a><div class="country-select d-inline-flex mb-3"><span>🇵🇭</span> <span class="ms-2">Philippines (Pilipinas)</span></div></div><div class="col-md-6 text-md-end"><a href="#" class="legal-link">Data Privacy Act</a></div></div><div class="text-center text-muted small mt-5 pt-4 border-top border-secondary">&copy; <?php echo date('Y'); ?> ResortEase Philippines. All rights reserved.</div></div></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>