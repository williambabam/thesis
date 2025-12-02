<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_admin_login(); // Use the new admin-specific login check
$user = $_SESSION['user'];

// --- 1. HANDLE STATUS UPDATES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_id'])) {
    verify_csrf();
    $id = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? ''; // Default to empty string if not set
    
    // --- Improved Validation ---
    $allowed_statuses = ['confirmed', 'checked_in', 'checked_out', 'cancelled', 'pending'];
    if ($id && in_array($status, $allowed_statuses)) {
        // If confirming, automatically mark payment as verified
        // Note: 'pending' is added to allowed statuses but doesn't trigger payment verification.
        // This allows admins to revert a status back to pending if needed.
        $paymentUpdate = ($status === 'confirmed') ? ', payment_status = "verified"' : '';
        
        $stmt = $pdo->prepare("UPDATE reservations SET status = ? $paymentUpdate WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        // Send Notification to Guest
        $res = $pdo->query("SELECT guest_id FROM reservations WHERE id = $id")->fetch();
        if ($res) {
            $pdo->prepare('INSERT INTO notifications (user_id, reservation_id, type, subject, message) VALUES (?, ?, "booking_update", "Booking Updated", ?)')
                ->execute([$res['guest_id'], $id, "Status changed to: " . ucfirst($status)]);
        }
        
        $_SESSION['success'] = 'Reservation updated successfully.';
        header('Location: reservations.php'); exit;
    }
}

// --- 2. BUILD QUERY (MULTI-RESORT FILTER) ---
$sql = 'SELECT r.*, ro.room_name, u.full_name, u.email 
        FROM reservations r 
        JOIN rooms ro ON r.room_id = ro.id 
        JOIN users u ON r.guest_id = u.id 
        WHERE 1=1';

$params = [];

// Filter: If Resort Admin, ONLY show bookings for THEIR rooms
if ($user['role'] === 'resort_admin') {
    $sql .= ' AND ro.owner_id = ?';
    $params[] = $user['id'];
}

// Search & Filter Inputs
if (!empty($_GET['status'])) { 
    $sql .= ' AND r.status = ?'; 
    $params[] = $_GET['status']; 
}
if (!empty($_GET['search'])) { 
    $sql .= ' AND (u.full_name LIKE ? OR ro.room_name LIKE ?)'; 
    $params[] = "%{$_GET['search']}%"; 
    $params[] = "%{$_GET['search']}%"; 
}

$sql .= ' ORDER BY r.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

// --- 3. CALCULATE STATS (FILTERED) ---
$statSql = "SELECT 
            COUNT(*) as total, 
            SUM(CASE WHEN r.status='pending' THEN 1 ELSE 0 END) as pending, 
            SUM(CASE WHEN r.status='confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN r.status='cancelled' THEN 1 ELSE 0 END) as cancelled
            FROM reservations r 
            JOIN rooms ro ON r.room_id = ro.id 
            WHERE 1=1";

$statParams = [];
if ($user['role'] === 'resort_admin') { 
    $statSql .= " AND ro.owner_id = ?"; 
    $statParams[] = $user['id']; 
}

$statStmt = $pdo->prepare($statSql);
$statStmt->execute($statParams);
$stats = $statStmt->fetch();
?>
<!doctype html>
<html lang="en">
<head>
  <title>Reservations - ResortEase</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #7c3aed; --bg: #f8fafc; }
    body { background-color: var(--bg); font-family: 'Inter', sans-serif; display: flex; flex-direction: column; min-height: 100vh; }
    .main-content { flex: 1; }
    .stat-card { background: white; border-radius: 12px; padding: 1.5rem; border: 1px solid #e2e8f0; }
    .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin-bottom: 1rem; }
    .content-card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; margin-top: 20px; }
    .table thead th { background-color: #f8fafc; color: #64748b; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; padding: 1rem; }
    .table tbody td { padding: 1rem; vertical-align: middle; }
    .badge-status { padding: 6px 12px; border-radius: 30px; font-size: 0.75rem; }
    .bg-confirmed { background: #dcfce7; color: #166534; }
    .bg-pending { background: #fef9c3; color: #854d0e; }
    .bg-cancelled { background: #fee2e2; color: #991b1b; }
    .bg-checked_in { background: #dbeafe; color: #1e40af; }
    .receipt-img { width: 100%; max-height: 300px; object-fit: contain; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 15px; background: #f9fafb; }
    .btn-primary { background-color: var(--primary); border: none; }
    
    /* Footer */
    .footer-dark { background-color: #191e1f; color: #efefef; padding: 40px 0; margin-top: 60px; }
    .footer-brand { font-weight: 800; font-size: 24px; color: white; text-decoration: none; }
    .legal-link { color: #d1d5db; text-decoration: none; }
    .country-select { display: inline-flex; align-items: center; gap: 10px; border: 1px solid #4b5563; padding: 8px 12px; border-radius: 4px; color: #fff; font-size: 0.9rem; cursor: pointer; }
    .social-icons a { color: white; margin-right: 15px; font-size: 1.2rem; opacity: 0.8; }
  </style>
</head>
<body>
<?php include __DIR__ . '/../navbar.php'; ?>

<div class="container py-4 main-content">
    <h2 class="fw-bold text-dark mb-4">Reservations</h2>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success mb-4"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="stat-card"><div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-calendar-alt"></i></div><h6 class="text-secondary small fw-bold text-uppercase">Total</h6><h2 class="fw-bold text-dark mb-0"><?php echo (int)($stats['total'] ?? 0); ?></h2></div></div>
        <div class="col-md-3"><div class="stat-card"><div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-clock"></i></div><h6 class="text-secondary small fw-bold text-uppercase">Pending</h6><h2 class="fw-bold text-dark mb-0"><?php echo (int)($stats['pending'] ?? 0); ?></h2></div></div>
        <div class="col-md-3"><div class="stat-card"><div class="stat-icon bg-success bg-opacity-10 text-success"><i class="fas fa-check-circle"></i></div><h6 class="text-secondary small fw-bold text-uppercase">Confirmed</h6><h2 class="fw-bold text-dark mb-0"><?php echo (int)($stats['confirmed'] ?? 0); ?></h2></div></div>
        <div class="col-md-3"><div class="stat-card"><div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="fas fa-times-circle"></i></div><h6 class="text-secondary small fw-bold text-uppercase">Cancelled</h6><h2 class="fw-bold text-dark mb-0"><?php echo (int)($stats['cancelled'] ?? 0); ?></h2></div></div>
    </div>

    <div class="content-card p-4 mb-4">
        <form class="row g-3">
            <div class="col-md-5">
                <label class="small fw-bold text-secondary">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Guest name or room..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
            </div>
            <div class="col-md-4">
                <label class="small fw-bold text-secondary">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php if(isset($_GET['status']) && $_GET['status']=='pending') echo 'selected'; ?>>Pending</option>
                    <option value="confirmed" <?php if(isset($_GET['status']) && $_GET['status']=='confirmed') echo 'selected'; ?>>Confirmed</option>
                    <option value="cancelled" <?php if(isset($_GET['status']) && $_GET['status']=='cancelled') echo 'selected'; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 me-2">Filter</button>
                <a href="reservations.php" class="btn btn-light border w-100">Reset</a>
            </div>
        </form>
    </div>

    <div class="content-card p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th class="ps-4">Guest</th><th>Room</th><th>Dates</th><th>Receipt</th><th>Status</th><th class="text-end pe-4">Action</th></tr></thead>
                <tbody>
                    <?php if (empty($reservations)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No reservations found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($reservations as $res): ?>
                        <?php $statusClass = 'bg-' . $res['status']; if(!in_array($res['status'],['confirmed','pending','cancelled','checked_in','checked_out'])) $statusClass='bg-light border'; ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($res['full_name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($res['email']); ?></small>
                            </td>
                            <td>
                                <div class="fw-bold text-primary"><?php echo htmlspecialchars($res['room_name']); ?></div>
                                <small class="text-muted"><?php echo $res['guests_count']; ?> Guests</small>
                            </td>
                            <td>
                                <div class="small"><?php echo date('M d', strtotime($res['check_in'])); ?> - <?php echo date('M d', strtotime($res['check_out'])); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($res['arrival_time'] ?? '2:00 PM'); ?></small>
                            </td>
                            <td>
                                <?php if($res['payment_proof']): ?>
                                    <span class="badge bg-primary"><i class="fas fa-paperclip"></i> View</span>
                                <?php else: ?>
                                    <span class="text-muted small">None</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-status <?php echo $statusClass; ?>"><?php echo ucfirst(str_replace('_', ' ', $res['status'])); ?></span></td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#manageModal<?php echo $res['id']; ?>">Manage</button>
                            </td>
                        </tr>

                        <div class="modal fade" id="manageModal<?php echo $res['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content rounded-4 border-0 shadow-lg">
                                    <div class="modal-header border-0"><h5 class="fw-bold">Reservation #<?php echo $res['id']; ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body pt-0">
                                        
                                        <div class="mb-3">
                                            <label class="small fw-bold text-secondary mb-2 d-block">Payment Proof</label>
                                            <?php if($res['payment_proof']): ?>
                                                <a href="../<?php echo htmlspecialchars($res['payment_proof']); ?>" target="_blank">
                                                    <img src="../<?php echo htmlspecialchars($res['payment_proof']); ?>" class="receipt-img">
                                                </a>
                                                <div class="text-center mb-2"><small class="text-muted">Click image to enlarge</small></div>
                                            <?php else: ?>
                                                <div class="alert alert-light border text-center small text-muted">No receipt uploaded by guest.</div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if($res['special_requests']): ?>
                                            <div class="alert alert-info small mb-3">
                                                <strong>Request:</strong> <?php echo htmlspecialchars($res['special_requests']); ?>
                                            </div>
                                        <?php endif; ?>

                                        <form method="post">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="reservation_id" value="<?php echo $res['id']; ?>">
                                            <label class="small fw-bold text-secondary">Update Status</label>
                                            <select name="status" class="form-select mb-3">
                                                <option value="pending" <?php if($res['status']=='pending') echo 'selected'; ?>>Pending</option>
                                                <option value="confirmed" <?php if($res['status']=='confirmed') echo 'selected'; ?>>Confirm (Verified)</option>
                                                <option value="checked_in" <?php if($res['status']=='checked_in') echo 'selected'; ?>>Check In</option>
                                                <option value="checked_out" <?php if($res['status']=='checked_out') echo 'selected'; ?>>Check Out</option>
                                                <option value="cancelled" <?php if($res['status']=='cancelled') echo 'selected'; ?>>Cancel</option>
                                            </select>
                                            <button type="submit" class="btn btn-primary w-100 rounded-pill">Save Changes</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<footer class="footer-dark">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <a href="#" class="footer-brand">ResortEase</a>
                <div class="country-select d-inline-flex mb-3"><span>🇵🇭</span> <span class="ms-2">Philippines (Pilipinas)</span></div>
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