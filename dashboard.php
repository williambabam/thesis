<?php
require_once __DIR__ . '/config.php';
require_login();
$user = $_SESSION['user'];

$stats = ['total_bookings' => 0, 'pending' => 0, 'revenue' => 0.0];
$tripCount = 0;

if ($user['role'] === 'resort_admin' || $user['role'] === 'super_admin') {
    // Base Query
    $sql = "SELECT 
            COUNT(*) as total, 
            SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending, 
            SUM(CASE WHEN r.status IN ('confirmed', 'checked_in', 'checked_out') THEN r.total_price ELSE 0 END) as revenue 
            FROM reservations r";
            
    // Multi-Resort Filter: Only count bookings for rooms OWNED by this admin
    if ($user['role'] === 'resort_admin') {
        $sql .= " JOIN rooms ro ON r.room_id = ro.id WHERE ro.owner_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user['id']]);
    } else {
        // Super Admin sees all
        $stmt = $pdo->query($sql);
    }
    
    $data = $stmt->fetch();
    $stats['total_bookings'] = (int)($data['total'] ?? 0);
    $stats['pending']        = (int)($data['pending'] ?? 0);
    $stats['revenue']        = (float)($data['revenue'] ?? 0);

} else {
    // GUEST LOGIC
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE guest_id = ?");
    $stmt->execute([$user['id']]);
    $tripCount = $stmt->fetchColumn();
}

// Avatar Fix
$avatar = $user['avatar'] ?? '';
if (!empty($avatar) && !filter_var($avatar, FILTER_VALIDATE_URL)) {
    $cleanPath = str_replace('../', '', $avatar);
    $avatar = '/resort_app/' . ltrim($cleanPath, '/');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Dashboard - ResortEase</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary-color: #7c3aed; --secondary-color: #64748b; --bg-color: #f8fafc; --card-bg: #ffffff; --hero-gradient: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
    html, body { height: 100%; margin: 0; }
    body { font-family: 'Inter', sans-serif; background-color: var(--bg-color); color: #1e293b; display: flex; flex-direction: column; }
    .main-container { flex: 1 0 auto; width: 100%; }
    .hero-card { background: var(--hero-gradient); color: white; border-radius: 20px; padding: 2.5rem; margin-bottom: 2rem; position: relative; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(124, 58, 237, 0.25); }
    .hero-pattern { position: absolute; right: -20px; top: -40px; font-size: 12rem; opacity: 0.15; transform: rotate(10deg); color: #fff; }
    .stat-card { background: var(--card-bg); border-radius: 16px; padding: 1.5rem; border: 1px solid #ede9fe; height: 100%; box-shadow: 0 2px 10px rgba(0,0,0,0.02); transition: transform 0.2s; }
    .stat-card:hover { transform: translateY(-3px); border-color: #d8b4fe; }
    .action-card { background: var(--card-bg); border-radius: 16px; padding: 1.75rem; border: 1px solid #ede9fe; transition: 0.2s; text-decoration: none; color: inherit; display: flex; flex-direction: column; height: 100%; justify-content: center; align-items: center; text-align: center; }
    .action-card:hover { transform: translateY(-5px); border-color: var(--primary-color); box-shadow: 0 10px 20px rgba(124,58,237,0.1); }
    .action-icon, .icon-circle { width: 60px; height: 60px; background: #f3e8ff; color: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 15px; }
    .badge-role { background: rgba(255,255,255,0.25); padding: 0.3rem 0.8rem; border-radius: 30px; font-size: 0.8rem; text-transform: uppercase; font-weight: 600; }
    .btn-light-custom { background: rgba(255,255,255,0.9); color: var(--primary-color); border: none; transition: all 0.2s; }
    .btn-light-custom:hover { background: #fff; transform: scale(1.05); }
    .footer-dark { flex-shrink: 0; background-color: #191e1f; color: #efefef; padding: 40px 0; margin-top: 60px; width: 100%; }
    .footer-brand { font-weight: 800; font-size: 24px; color: white; text-decoration: none; }
    .social-icons a { color: white; margin-right: 15px; opacity: 0.8; }
    .legal-link { color: #9ca3af; text-decoration: none; }
    .avatar-circle { width: 70px; height: 70px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; border: 3px solid rgba(255,255,255,0.5); overflow: hidden; backdrop-filter: blur(5px); }
    .avatar-img { width: 100%; height: 100%; object-fit: cover; }
  </style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<div class="container py-4 main-container">

  <div class="hero-card">
    <i class="fas fa-umbrella-beach hero-pattern"></i>
    <div class="row align-items-center position-relative" style="z-index: 2;">
      <div class="col-md-8">
        <div class="d-flex align-items-center gap-3">
          <div class="avatar-circle">
             <?php if (!empty($avatar)): ?>
                <img src="<?php echo htmlspecialchars($avatar); ?>" class="avatar-img" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user['full_name']); ?>&background=random'">
            <?php else: ?>
                <i class="fas fa-user text-white"></i>
            <?php endif; ?>
          </div>
          <div>
            <h2 class="fw-bold mb-1">Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>!</h2>
            <div class="d-flex align-items-center gap-2 mt-2">
                <span class="badge-role"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></span>
                <span class="opacity-90 small"><i class="fas fa-user-circle ms-1"></i> @<?php echo htmlspecialchars($user['username'] ?? 'user'); ?></span>
            </div>
          </div>
        </div>
      </div>
      <?php if ($user['role'] !== 'guest'): ?>
      <div class="col-md-4 text-md-end mt-4 mt-md-0">
        <a href="/resort_app/admin/resort_cms.php" class="btn btn-light-custom fw-bold shadow-sm px-4 py-2 rounded-pill">
          <i class="fas fa-sliders-h me-1"></i> Settings
        </a>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($user['role'] === 'resort_admin' || $user['role'] === 'super_admin'): ?>
    <div class="row g-4 mb-5">
        <div class="col-md-4"><div class="stat-card d-flex align-items-center"><div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-calendar-check"></i></div><div class="ms-3"><h6 class="text-secondary fw-semibold mb-0">Total Bookings</h6><h2 class="fw-bold mb-0 text-dark"><?php echo number_format($stats['total_bookings']); ?></h2></div></div></div>
        <div class="col-md-4"><div class="stat-card d-flex align-items-center"><div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-clock"></i></div><div class="ms-3"><h6 class="text-secondary fw-semibold mb-0">Pending Actions</h6><h2 class="fw-bold mb-0 text-dark"><?php echo number_format($stats['pending']); ?></h2></div></div></div>
        <div class="col-md-4"><div class="stat-card d-flex align-items-center"><div class="stat-icon bg-success bg-opacity-10 text-success"><i class="fas fa-coins"></i></div><div class="ms-3"><h6 class="text-secondary fw-semibold mb-0">Total Revenue</h6><h2 class="fw-bold mb-0 text-dark">₱<?php echo number_format($stats['revenue'], 2); ?></h2></div></div></div>
    </div>

    <h5 class="fw-bold text-dark mb-3">Management Console</h5>
    
    <div class="row g-3">
        <div class="col-md-3"><a href="/resort_app/rooms/index.php" class="action-card"><div class="action-icon"><i class="fas fa-door-open"></i></div><h6 class="fw-bold mb-1">Rooms</h6><p class="small text-muted mb-0">Manage availability</p></a></div>
        <div class="col-md-3"><a href="/resort_app/admin/reservations.php" class="action-card"><div class="action-icon"><i class="fas fa-list-alt"></i></div><h6 class="fw-bold mb-1">Reservations</h6><p class="small text-muted mb-0">Check-ins & bookings</p></a></div>
        <div class="col-md-3"><a href="/resort_app/admin/refunds.php" class="action-card"><div class="action-icon"><i class="fas fa-undo"></i></div><h6 class="fw-bold mb-1">Refunds</h6><p class="small text-muted mb-0">Cancellations</p></a></div>
        <div class="col-md-3"><a href="/resort_app/admin/manage_staff.php" class="action-card"><div class="action-icon"><i class="fas fa-users"></i></div><h6 class="fw-bold mb-1">Staff Team</h6><p class="small text-muted mb-0">Manage access</p></a></div>
        <div class="col-md-3"><a href="/resort_app/admin/alerts.php" class="action-card"><div class="action-icon"><i class="fas fa-bell"></i></div><h6 class="fw-bold mb-1">Alerts</h6><p class="small text-muted mb-0">System Notifications</p></a></div>
        <div class="col-md-3"><a href="/resort_app/admin/reports.php" class="action-card"><div class="action-icon"><i class="fas fa-chart-pie"></i></div><h6 class="fw-bold mb-1">Analytics</h6><p class="small text-muted mb-0">Sales & Trends</p></a></div>
        <div class="col-md-3"><a href="/resort_app/admin/payment_settings.php" class="action-card"><div class="action-icon"><i class="fas fa-wallet"></i></div><h6 class="fw-bold mb-1">Payments</h6><p class="small text-muted mb-0">Methods & Receipts</p></a></div>
        <div class="col-md-3"><a href="/resort_app/admin/resort_cms.php" class="action-card"><div class="action-icon"><i class="fas fa-wrench"></i></div><h6 class="fw-bold mb-1">Settings</h6><p class="small text-muted mb-0">Resort Config</p></a></div>
    </div>

  <?php else: ?>
    <div class="row g-4">
      <div class="col-md-7">
        <div class="card h-100 border-0 shadow-sm overflow-hidden" style="border-radius: 20px;">
          <div class="card-body p-5 bg-white d-flex flex-column justify-content-center position-relative">
            <div class="position-absolute top-0 end-0 translate-middle p-5 bg-primary opacity-10 rounded-circle" style="margin-right: -50px; margin-top: -50px;"></div>
            <h3 class="fw-bold mb-3 text-dark">Find your perfect stay</h3>
            <p class="text-muted mb-4" style="max-width: 80%;">Discover amazing rooms, exclusive packages, and the best rates for your next vacation.</p>
            <a href="/resort_app/guest/browse.php" class="btn btn-primary btn-lg shadow-sm rounded-pill px-4" style="width: fit-content;">
              <i class="fas fa-search me-2"></i> Browse Resorts
            </a>
          </div>
        </div>
      </div>

      <div class="col-md-5">
          <div class="card h-100 border-0 shadow-sm bg-white p-4" style="border-radius: 20px;">
            <div class="d-flex align-items-center mb-3">
              <div class="action-icon" style="width: 50px; height: 50px; font-size: 1.5rem; background: #f3e8ff; color: #7c3aed; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-suitcase"></i>
              </div>
            </div>
            <h4 class="fw-bold text-dark">My Trips</h4>
            <p class="text-muted small">You have <strong><?php echo $tripCount; ?></strong> bookings</p>
            <a href="/resort_app/guest/my_bookings.php" class="btn btn-outline-primary w-100 rounded-pill mt-auto fw-bold py-2">
                View My Trips
            </a>
          </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<footer class="footer-dark">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6"><a href="#" class="footer-brand">ResortEase</a><div class="country-select d-inline-flex mb-3"><span>🇵🇭</span> <span class="ms-2">Philippines (Pilipinas)</span></div></div>
            <div class="col-md-6 text-md-end"><a href="#" class="legal-link">Data Privacy Act</a></div>
        </div>
        <div class="text-center text-muted small mt-4 border-top border-secondary pt-3">&copy; <?php echo date('Y'); ?> ResortEase Philippines. All rights reserved.</div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>