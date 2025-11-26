<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config.php';
$user = $_SESSION['user'] ?? null;

$unreadCount = 0;
if ($user && isset($pdo)) {
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND read_at IS NULL');
        $stmt->execute([$user['id']]);
        $unreadCount = $stmt->fetch()['unread'];
    } catch (Exception $e) {}
}
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap');
    
    .navbar-brand-text { font-family: 'Inter', sans-serif; font-weight: 800; font-size: 1.5rem; color: #374151; letter-spacing: -0.5px; }
    .navbar-brand-text span { color: #7c3aed; }
    .navbar-custom { background-color: #ffffff; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 0.8rem 0; }
    .nav-link { font-family: 'Inter', sans-serif; font-weight: 500; color: #4b5563 !important; transition: color 0.2s; margin: 0 5px; }
    .nav-link:hover { color: #7c3aed !important; }
    .avatar-nav { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 2px solid #e5e7eb; }
    .notification-icon { color: #64748b; transition: color 0.2s; position: relative; padding: 5px; display: flex; align-items: center; }
    .notification-icon:hover { color: #7c3aed; }
    .badge-notification { font-size: 0.6rem; position: absolute; top: 0; right: -2px; padding: 0.25em 0.4em; }
</style>

<nav class="navbar navbar-expand-lg navbar-light navbar-custom sticky-top">
  <div class="container">
    <a class="navbar-brand navbar-brand-text" href="<?php echo ($user) ? $root . '/dashboard.php' : $root . '/index.php'; ?>">
      Resort<span>Ease</span>
    </a>
    
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
        
        <?php if (!$user): ?>
            <li class="nav-item"><a class="nav-link" href="<?php echo $root; ?>/guest/browse.php">Browse Resorts</a></li>
        <?php endif; ?>

        <?php if ($user && $user['role'] === 'guest'): ?>
            <li class="nav-item"><a class="nav-link" href="<?php echo $root; ?>/dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="<?php echo $root; ?>/guest/browse.php">Browse</a></li>
            <li class="nav-item"><a class="nav-link" href="<?php echo $root; ?>/guest/my_bookings.php">My Trips</a></li>
        <?php endif; ?>

        <?php if ($user && ($user['role'] === 'resort_admin' || $user['role'] === 'super_admin')): ?>
            <li class="nav-item"><a class="nav-link" href="<?php echo $root; ?>/dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="<?php echo $root; ?>/rooms/index.php">Rooms</a></li>
            <li class="nav-item"><a class="nav-link" href="<?php echo $root; ?>/admin/reservations.php">Bookings</a></li>
            
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Manage</a>
              <ul class="dropdown-menu shadow-sm border-0 mt-2">
                <li><a class="dropdown-item" href="<?php echo $root; ?>/admin/manage_staff.php">Staff</a></li>
                <li><a class="dropdown-item" href="<?php echo $root; ?>/admin/resort_cms.php">Settings</a></li>
                <li><a class="dropdown-item" href="<?php echo $root; ?>/admin/payment_settings.php">Payments</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?php echo $root; ?>/admin/reports.php">Reports</a></li>
              </ul>
            </li>
        <?php endif; ?>
      </ul>

      <?php if ($user): ?>
        <div class="d-flex align-items-center gap-3">
            <a href="<?php echo $root; ?>/notifications.php" class="notification-icon me-2" title="Notifications">
                <i class="fas fa-bell fa-lg"></i>
                <?php if ($unreadCount > 0): ?><span class="badge rounded-pill bg-danger badge-notification"><?php echo $unreadCount; ?></span><?php endif; ?>
            </a>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                    <?php if (!empty($user['avatar'])): ?><img src="<?php echo htmlspecialchars($user['avatar']); ?>" class="avatar-nav me-2"><?php else: ?><div class="avatar-nav bg-light d-flex align-items-center justify-content-center me-2 text-secondary"><i class="fas fa-user"></i></div><?php endif; ?>
                    <span class="d-none d-lg-inline fw-bold text-dark small"><?php echo htmlspecialchars($user['full_name']); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2">
                    <li><a class="dropdown-item py-2 text-danger fw-bold" href="<?php echo $root; ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
      <?php else: ?>
        <div class="d-flex gap-2">
            <a href="<?php echo $root; ?>/login.php" class="btn btn-outline-primary btn-sm fw-bold px-3 rounded-pill" style="border-color:#7c3aed; color:#7c3aed;">Log in</a>
            <a href="<?php echo $root; ?>/register.php" class="btn btn-primary btn-sm fw-bold px-3 rounded-pill" style="background-color:#7c3aed; border:none;">Register</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</nav>