<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_login();

$user = $_SESSION['user'];

if (isset($_GET['mark_read'])) {
    $notif_id = intval($_GET['mark_read']);
    $stmt = $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ?');
    $stmt->execute([$notif_id, $user['id']]);
    header('Location: notifications.php');
    exit;
}

if (isset($_GET['mark_all'])) {
    $stmt = $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL');
    $stmt->execute([$user['id']]);
    header('Location: notifications.php');
    exit;
}

$stmt = $pdo->prepare('
    SELECT n.*, r.room_id, ro.room_name
    FROM notifications n
    LEFT JOIN reservations r ON n.reservation_id = r.id
    LEFT JOIN rooms ro ON r.room_id = ro.id
    WHERE n.user_id = ?
    ORDER BY n.sent_at DESC
');
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND read_at IS NULL');
$stmt->execute([$user['id']]);
$unreadCount = $stmt->fetch()['unread'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Notifications - ResortEase</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #7c3aed; --bg: #f8fafc; }
    
    body { 
        background-color: var(--bg); 
        font-family: 'Inter', sans-serif; 
        display: flex; 
        flex-direction: column; 
        min-height: 100vh; 
    }
    .main-content { flex: 1; }

    /* Notification Card */
    .notif-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
        display: flex;
        gap: 1.5rem;
        position: relative;
    }
    .notif-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        border-color: #d8b4fe;
    }
    .notif-card.unread {
        background-color: #fbfaff; /* Very light purple tint */
        border-left: 4px solid var(--primary);
    }
    
    /* Icon Box */
    .icon-box {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    
    /* Buttons */
    .btn-outline-primary {
        color: var(--primary);
        border-color: var(--primary);
    }
    .btn-outline-primary:hover {
        background-color: var(--primary);
        color: white;
    }
    
    /* Footer */
    .footer-dark { background-color: #191e1f; color: #efefef; padding: 40px 0; font-size: 14px; margin-top: 60px; }
    .footer-brand { font-weight: 800; font-size: 24px; color: white; text-decoration: none; display: block; margin-bottom: 15px; }
    .country-select { display: inline-flex; align-items: center; gap: 10px; border: 1px solid #4b5563; padding: 8px 12px; border-radius: 4px; color: #fff; font-size: 0.9rem; cursor: pointer; }
    .social-icons a { color: white; margin-right: 15px; font-size: 1.2rem; opacity: 0.8; transition: 0.2s; }
    .legal-link { color: #9ca3af; text-decoration: none; }
  </style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>

<div class="container py-4 main-content">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">Notifications</h2>
            <p class="text-muted small mb-0">Updates on your bookings and account</p>
        </div>
        <?php if ($unreadCount > 0): ?>
            <a href="?mark_all=1" class="btn btn-outline-primary rounded-pill px-4 text-sm fw-bold">
                <i class="fas fa-check-double me-2"></i> Mark All Read
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="text-center py-5">
            <div class="bg-white p-5 rounded-4 shadow-sm d-inline-block border">
                <i class="fas fa-bell-slash fa-3x text-muted mb-3 opacity-50"></i>
                <h5 class="fw-bold text-secondary">No notifications yet</h5>
                <p class="text-muted small">We'll let you know when something happens.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <?php foreach ($notifications as $notif): ?>
                    <?php
                        $isUnread = $notif['read_at'] === null;
                        
                        // Icon Logic
                        $icons = [
                            'booking_confirmation' => ['icon' => 'calendar-check', 'bg' => 'success', 'color' => 'success'],
                            'booking_update'       => ['icon' => 'info-circle',    'bg' => 'info',    'color' => 'info'],
                            'cancellation'         => ['icon' => 'times-circle',   'bg' => 'danger',  'color' => 'danger'],
                            'admin_notification'   => ['icon' => 'shield-alt',     'bg' => 'primary', 'color' => 'primary']
                        ];
                        // Default to bell if type unknown
                        $style = $icons[$notif['type']] ?? ['icon' => 'bell', 'bg' => 'secondary', 'color' => 'secondary'];
                        
                        // Fix bootstrap classes for custom purple
                        $bgClass = ($style['bg'] === 'primary') ? 'bg-primary bg-opacity-10 text-primary' : "bg-{$style['bg']} bg-opacity-10 text-{$style['color']}";
                    ?>
                    
                    <div class="notif-card <?php echo $isUnread ? 'unread' : ''; ?>">
                        <div class="icon-box <?php echo $bgClass; ?>">
                            <i class="fas fa-<?php echo $style['icon']; ?>"></i>
                        </div>
                        
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <h6 class="fw-bold mb-0 text-dark">
                                    <?php echo htmlspecialchars($notif['subject']); ?>
                                    <?php if ($isUnread): ?>
                                        <span class="badge bg-primary ms-2" style="font-size: 0.6rem; vertical-align: middle;">NEW</span>
                                    <?php endif; ?>
                                </h6>
                                <small class="text-muted" style="font-size: 0.75rem;">
                                    <?php echo (new DateTime($notif['sent_at']))->format('M d, g:i A'); ?>
                                </small>
                            </div>
                            
                            <p class="text-secondary mb-2" style="font-size: 0.9rem;">
                                <?php echo nl2br(htmlspecialchars($notif['message'])); ?>
                            </p>
                            
                            <?php if ($notif['room_name']): ?>
                                <p class="mb-2 small text-muted bg-light d-inline-block px-2 py-1 rounded border">
                                    <i class="fas fa-bed me-1"></i> <?php echo htmlspecialchars($notif['room_name']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="mt-2">
                                <?php if ($notif['reservation_id']): ?>
                                    <a href="<?php echo ($user['role'] === 'guest') ? 'guest/my_bookings.php' : 'admin/reservations.php'; ?>" class="btn btn-sm btn-light border text-dark fw-bold px-3 rounded-pill me-2">
                                        View Details
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($isUnread): ?>
                                    <a href="?mark_read=<?php echo $notif['id']; ?>" class="btn btn-sm text-muted ps-0 ms-2" style="font-size:0.85rem; text-decoration:underline;">
                                        Mark as read
                                    </a>
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