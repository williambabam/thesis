<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();

if (!in_array($_SESSION['user']['role'], ['resort_admin', 'super_admin'])) {
    header('Location: ../dashboard.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refund_id'])) {
    verify_csrf();
    $refund_id = intval($_POST['refund_id']);
    $action = $_POST['action']; 
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    
    $refund_method = trim($_POST['refund_method'] ?? '');
    $refund_ref = trim($_POST['refund_reference'] ?? '');
    
    if (in_array($action, ['approve', 'reject'])) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        
        $stmt = $pdo->prepare('
            UPDATE refund_requests 
            SET status = ?, admin_notes = ?, refund_method = ?, refund_reference = ?, processed_at = NOW(), processed_by = ? 
            WHERE id = ?
        ');
        $stmt->execute([$status, $admin_notes, $refund_method, $refund_ref, $_SESSION['user']['id'], $refund_id]);
        
        $_SESSION['success'] = "Refund processed as {$status}.";
        header('Location: refunds.php'); exit;
    }
}

$refunds = $pdo->query('SELECT rr.*, u.full_name, r.total_price, ro.room_name FROM refund_requests rr JOIN users u ON rr.user_id = u.id JOIN reservations r ON rr.reservation_id = r.id JOIN rooms ro ON r.room_id = ro.id ORDER BY rr.requested_at DESC')->fetchAll();
$stats = $pdo->query('SELECT COUNT(*) as total, SUM(CASE WHEN status="pending" THEN 1 ELSE 0 END) as pending, SUM(CASE WHEN status="approved" THEN 1 ELSE 0 END) as approved, SUM(CASE WHEN status="approved" THEN refund_amount ELSE 0 END) as refunded_amount FROM refund_requests')->fetch();
?>
<!doctype html>
<html lang="en">
<head>
  <title>Refunds - ResortEase</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #7c3aed; --bg: #f8fafc; }
    body { background-color: var(--bg); font-family: 'Inter', sans-serif; display: flex; flex-direction: column; min-height: 100vh; }
    .main-content { flex: 1; }
    .stat-card { background: white; border-radius: 12px; padding: 1.5rem; border: 1px solid #e2e8f0; height: 100%; }
    .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin-bottom: 1rem; }
    .content-card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; }
    .table thead th { background-color: #f8fafc; color: #64748b; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; padding: 1rem; }
    .table tbody td { padding: 1rem; vertical-align: middle; }
    .btn-primary { background-color: var(--primary); border: none; }
    /* Footer Styles */
    .footer-dark { background-color: #191e1f; color: #efefef; padding: 40px 0; font-size: 14px; margin-top: 60px; }
    .footer-brand { font-weight: 800; font-size: 24px; color: white; text-decoration: none; display: block; margin-bottom: 15px; }
    .legal-link { color: #d1d5db; text-decoration: none; }
    .country-select { display: inline-flex; align-items: center; gap: 10px; border: 1px solid #4b5563; padding: 8px 12px; border-radius: 4px; color: #fff; font-size: 0.9rem; cursor: pointer; }
    .social-icons a { color: white; margin-right: 15px; font-size: 1.2rem; opacity: 0.8; transition: 0.2s; }
  </style>
</head>
<body>
<?php include __DIR__ . '/../navbar.php'; ?>

<div class="container py-4 main-content">
    <h2 class="fw-bold text-dark mb-4">Refund Requests</h2>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success mb-4"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="stat-card"><div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-receipt"></i></div><h6 class="text-secondary small fw-bold text-uppercase">Total Requests</h6><h2 class="fw-bold text-dark mb-0"><?php echo (int)($stats['total'] ?? 0); ?></h2></div></div>
        <div class="col-md-3"><div class="stat-card"><div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-hourglass-half"></i></div><h6 class="text-secondary small fw-bold text-uppercase">Pending</h6><h2 class="fw-bold text-dark mb-0"><?php echo (int)($stats['pending'] ?? 0); ?></h2></div></div>
        <div class="col-md-3"><div class="stat-card"><div class="stat-icon bg-success bg-opacity-10 text-success"><i class="fas fa-check-circle"></i></div><h6 class="text-secondary small fw-bold text-uppercase">Approved</h6><h2 class="fw-bold text-dark mb-0"><?php echo (int)($stats['approved'] ?? 0); ?></h2></div></div>
        <div class="col-md-3"><div class="stat-card"><div class="stat-icon bg-dark bg-opacity-10 text-dark"><i class="fas fa-coins"></i></div><h6 class="text-secondary small fw-bold text-uppercase">Total Refunded</h6><h2 class="fw-bold text-dark mb-0">₱<?php echo number_format((float)($stats['refunded_amount'] ?? 0), 2); ?></h2></div></div>
    </div>

    <div class="content-card">
        <table class="table table-hover mb-0">
            <thead><tr><th class="ps-4">Guest</th><th>Booking</th><th>Amount</th><th>Reason</th><th>Status</th><th class="text-end pe-4">Action</th></tr></thead>
            <tbody>
                <?php if(empty($refunds)): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">No refund requests found.</td></tr>
                <?php else: ?>
                    <?php foreach($refunds as $ref): ?>
                    <tr>
                        <td class="ps-4"><div class="fw-bold"><?php echo htmlspecialchars($ref['full_name']); ?></div></td>
                        <td><?php echo htmlspecialchars($ref['room_name']); ?></td>
                        <td class="fw-bold text-dark">₱<?php echo number_format((float)$ref['refund_amount'], 2); ?></td>
                        <td class="text-muted small text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($ref['reason']); ?></td>
                        <td><span class="badge bg-<?php echo $ref['status']=='pending'?'warning':'light text-dark border'; ?>"><?php echo ucfirst($ref['status']); ?></span></td>
                        <td class="text-end pe-4">
                            <?php if($ref['status'] === 'pending'): ?>
                                <button class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#processModal<?php echo $ref['id']; ?>">Review</button>
                            <?php else: ?>
                                <span class="text-muted small"><i class="fas fa-check-double"></i> Done</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <div class="modal fade" id="processModal<?php echo $ref['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content border-0 shadow-lg rounded-4">
                                <div class="modal-header border-0"><h5 class="fw-bold">Refund Review</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body pt-0">
                                    <div class="bg-light p-3 rounded border mb-3"><strong>Reason:</strong><br><?php echo nl2br(htmlspecialchars($ref['reason'])); ?></div>
                                    
                                    <form method="post" id="form<?php echo $ref['id']; ?>">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="refund_id" value="<?php echo $ref['id']; ?>">
                                        <input type="hidden" name="action" id="action<?php echo $ref['id']; ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold">Refund Method</label>
                                            <select name="refund_method" class="form-select">
                                                <option value="GCash">GCash</option>
                                                <option value="Bank Transfer">Bank Transfer</option>
                                                <option value="Cash">Cash</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold">Reference Number</label>
                                            <input type="text" name="refund_reference" class="form-control" placeholder="e.g. Ref No. 12345">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold">Admin Notes</label>
                                            <textarea name="admin_notes" class="form-control" rows="2"></textarea>
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-outline-danger flex-grow-1" onclick="document.getElementById('action<?php echo $ref['id']; ?>').value='reject'; document.getElementById('form<?php echo $ref['id']; ?>').submit();">Reject</button>
                                            <button type="button" class="btn btn-primary flex-grow-1" onclick="document.getElementById('action<?php echo $ref['id']; ?>').value='approve'; document.getElementById('form<?php echo $ref['id']; ?>').submit();">Approve Refund</button>
                                        </div>
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