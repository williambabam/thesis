<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../navbar.php';
require_login();
$user = $_SESSION['user'];

$stmt = $pdo->prepare('SELECT r.*, ro.room_name, ro.location, ro.price, (SELECT status FROM refund_requests WHERE reservation_id = r.id LIMIT 1) as refund_status FROM reservations r JOIN rooms ro ON r.room_id = ro.id WHERE r.guest_id = ? ORDER BY r.created_at DESC');
$stmt->execute([$user['id']]);
$reservations = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <title>My Trips - ResortEase</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #7c3aed; --bg: #f8fafc; }
    body { background-color: var(--bg); font-family: 'Inter', sans-serif; display: flex; flex-direction: column; min-height: 100vh; }
    .main-content { flex: 1; }
    .trip-card { background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 25px; margin-bottom: 20px; transition: transform 0.2s; }
    .trip-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); border-color: #d8b4fe; }
    .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
    .bg-confirmed { background: #dcfce7; color: #166534; }
    .bg-pending { background: #fef9c3; color: #854d0e; }
    .bg-cancelled { background: #fee2e2; color: #991b1b; }
    .btn-primary { background-color: var(--primary); border: none; }
    .footer-dark { background-color: #191e1f; color: #efefef; padding: 40px 0; margin-top: 60px; }
    .footer-brand { font-weight: 800; font-size: 24px; color: white; text-decoration: none; }
    .legal-link { color: #d1d5db; text-decoration: none; }
  </style>
</head>
<body>
<div class="container py-5 main-content">
    <h2 class="fw-bold text-dark mb-4">My Trips</h2>
    <?php if (isset($_GET['success'])): ?><div class="alert alert-success rounded-3 mb-4">Request sent successfully.</div><?php endif; ?>
    
    <?php if (empty($reservations)): ?>
        <div class="text-center py-5"><div class="bg-white p-5 rounded-4 border shadow-sm d-inline-block"><i class="fas fa-suitcase fa-3x text-muted mb-3 opacity-50"></i><h5 class="fw-bold text-secondary">No trips yet</h5><a href="browse.php" class="btn btn-primary rounded-pill px-4 mt-2">Start Exploring</a></div></div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($reservations as $res): ?>
                <div class="col-md-6">
                    <div class="trip-card">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div><h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($res['room_name']); ?></h5><p class="text-muted small mb-0"><i class="fas fa-map-marker-alt me-1 text-danger"></i> <?php echo htmlspecialchars($res['location']); ?></p></div>
                            <span class="status-badge bg-<?php echo $res['status']; ?>"><?php echo ucfirst($res['status']); ?></span>
                        </div>
                        <div class="d-flex gap-4 mb-3 border-top border-bottom py-3 bg-light rounded px-3">
                            <div><small class="text-muted d-block">Check-in</small><span class="fw-bold text-dark"><?php echo date('M d, Y', strtotime($res['check_in'])); ?></span></div>
                            <div><small class="text-muted d-block">Check-out</small><span class="fw-bold text-dark"><?php echo date('M d, Y', strtotime($res['check_out'])); ?></span></div>
                            <div class="ms-auto text-end"><small class="text-muted d-block">Total</small><span class="fw-bold text-primary">₱<?php echo number_format((float)$res['total_price'], 2); ?></span></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted">ID: #<?php echo $res['id']; ?></span>
                            <?php if (in_array($res['status'], ['pending', 'confirmed'])): ?>
                                <?php if ($res['refund_status']): ?><span class="badge bg-secondary">Refund: <?php echo ucfirst($res['refund_status']); ?></span>
                                <?php else: ?><button class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#refundModal<?php echo $res['id']; ?>">Request Refund</button><?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal fade" id="refundModal<?php echo $res['id']; ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg rounded-4"><div class="modal-header border-0"><h5 class="modal-title fw-bold text-danger">Request Refund</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body pt-0"><form action="refund_action.php" method="post"><input type="hidden" name="reservation_id" value="<?php echo $res['id']; ?>"><p class="small text-muted">Reason for cancellation:</p><div class="mb-3"><textarea name="reason" class="form-control bg-light" rows="3" required></textarea></div><div class="d-grid"><button type="submit" class="btn btn-danger rounded-pill py-2 fw-bold">Submit Request</button></div></form></div></div></div></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<footer class="footer-dark"><div class="container"><div class="row align-items-center"><div class="col-md-6"><a href="#" class="footer-brand">ResortEase</a></div><div class="col-md-6 text-md-end"><a href="#" class="legal-link">Data Privacy Act</a></div></div><div class="text-center text-muted small mt-4 border-top border-secondary pt-3">&copy; <?php echo date('Y'); ?> ResortEase.</div></div></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>