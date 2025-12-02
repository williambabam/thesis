// alerts.php //

<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();

if (isset($_GET['mark_read'])) {
    $pdo->prepare('UPDATE system_alerts SET is_read = 1 WHERE id = ?')->execute([$_GET['mark_read']]);
    header('Location: alerts.php'); exit;
}

$alerts = $pdo->query('SELECT * FROM system_alerts ORDER BY created_at DESC')->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <title>Alerts - ResortEase</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #7c3aed; --bg: #f8fafc; }
    body { background-color: var(--bg); font-family: 'Inter', sans-serif; display: flex; flex-direction: column; min-height: 100vh; }
    .main-content { flex: 1; }
    .alert-card { background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 15px; display: flex; gap: 20px; transition: 0.2s; }
    .alert-card:hover { border-color: var(--primary); transform: translateX(5px); }
    .alert-card.unread { border-left: 4px solid var(--primary); background: #fdfaff; }
    .icon-box { width: 45px; height: 45px; background: #f3e8ff; color: var(--primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
    /* Footer reused */
    .footer-dark { background-color: #191e1f; color: #efefef; padding: 40px 0; font-size: 14px; margin-top: 60px; }
    .footer-brand { font-weight: 800; font-size: 24px; color: white; text-decoration: none; display: block; margin-bottom: 20px; }
    .country-select { display: inline-flex; align-items: center; gap: 10px; border: 1px solid #4b5563; padding: 8px 12px; border-radius: 4px; color: #fff; font-size: 0.9rem; cursor: pointer; }
    .social-icons a { color: white; margin-right: 15px; font-size: 1.2rem; opacity: 0.8; transition: 0.2s; }
    .legal-link { color: #d1d5db; text-decoration: none; }
  </style>
</head>
<body>
<?php include __DIR__ . '/../navbar.php'; ?>

<div class="container py-4 main-content">
    <h2 class="fw-bold text-dark mb-4">System Alerts</h2>

    <?php if(empty($alerts)): ?>
        <div class="text-center py-5 text-muted">No new notifications.</div>
    <?php else: ?>
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <?php foreach($alerts as $alert): ?>
                <div class="alert-card <?php echo !$alert['is_read'] ? 'unread' : ''; ?>">
                    <div class="icon-box">
                        <?php if($alert['alert_type']=='cancellation'): ?><i class="fas fa-times-circle text-danger"></i>
                        <?php elseif($alert['alert_type']=='new_booking'): ?><i class="fas fa-check-circle text-success"></i>
                        <?php else: ?><i class="fas fa-bell"></i><?php endif; ?>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between mb-1">
                            <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($alert['title']); ?></h6>
                            <small class="text-muted"><?php echo date('M d, g:i A', strtotime($alert['created_at'])); ?></small>
                        </div>
                        <p class="text-secondary small mb-2"><?php echo htmlspecialchars($alert['message']); ?></p>
                        
                        <div class="d-flex gap-2">
                            <?php if($alert['related_id']): ?>
                                <?php if($alert['alert_type'] === 'new_booking'): ?>
                                    <a href="reservations.php" class="btn btn-sm btn-light border text-primary">View Booking</a>
                                <?php elseif($alert['alert_type'] === 'cancellation'): ?>
                                    <a href="refunds.php" class="btn btn-sm btn-light border text-danger">View Refund</a>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if(!$alert['is_read']): ?>
                                <a href="?mark_read=<?php echo $alert['id']; ?>" class="btn btn-sm btn-light text-secondary">Mark as Read</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
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
