// book.php //
<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login(); 

$user = $_SESSION['user'];
$errors = [];

$room_id      = intval($_REQUEST['room_id'] ?? 0);
$check_in     = $_REQUEST['check_in'] ?? '';
$check_out    = $_REQUEST['check_out'] ?? '';
$guests       = intval($_REQUEST['guests'] ?? 1);
$arrival_time = $_REQUEST['arrival_time'] ?? '14:00';

// 2. Fetch Room Details
$stmt = $pdo->prepare('SELECT * FROM rooms WHERE id = ?');
$stmt->execute([$room_id]);
$room = $stmt->fetch();

if (!$room) { header('Location: ../guest/browse.php'); exit; }

// 3. Fetch Payment Methods
$payMethods = $pdo->query("SELECT * FROM payment_methods WHERE is_active = 1")->fetchAll();
$hasPaymentMethods = count($payMethods) > 0;

// 4. Calculations
try {
    $checkInDate  = new DateTime($check_in);
    $checkOutDate = new DateTime($check_out);
    $nights       = $checkInDate->diff($checkOutDate)->days;
} catch (Exception $e) {
    $nights = 0; // Fallback for invalid dates
}
if ($nights < 1) $nights = 1;

// Price Logic
$base_price = floatval($room['price'] ?? 0);
$subtotal = $nights * $base_price;
$discount_amount = 0;
if ($room['discount_percent'] > 0) {
    $discount_amount = ($subtotal * $room['discount_percent']) / 100;
}
$total_price = $subtotal - $discount_amount;

// 5. Handle CONFIRMATION Submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $special_req = trim($_POST['special_requests'] ?? '');
    
    // Validation
    if ($checkInDate < new DateTime('today')) $errors[] = "Check-in cannot be in the past.";
    
    // Check Availability
    $checkStmt = $pdo->prepare('
        SELECT 1 FROM reservations WHERE room_id = ? AND status IN ("confirmed", "checked_in", "pending") AND (check_in < ? AND check_out > ?)
        UNION SELECT 1 FROM room_availability WHERE room_id = ? AND (blocked_date BETWEEN ? AND ?)
    ');
    $checkStmt->execute([$room_id, $check_out, $check_in, $room_id, $check_in, $check_out]);
    
    if ($checkStmt->fetch()) {
        $errors[] = "Sorry, these dates are no longer available.";
    }

    // Check Payment Proof (Required if price > 0)
    if ($total_price > 0 && $hasPaymentMethods && empty($_FILES['payment_proof']['name'])) {
        $errors[] = "Please upload a screenshot of your payment receipt.";
    }

    if (!$errors) {
        // Upload Logic
        $dbPath = null;
        if (!empty($_FILES['payment_proof']['name'])) {
            $uploadDir = __DIR__ . '/../uploads/receipts/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $filename = time() . '_' . basename($_FILES['payment_proof']['name']);
            if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $uploadDir . $filename)) {
                $dbPath = 'uploads/receipts/' . $filename;
            }
        }

        // Create Reservation
        $stmt = $pdo->prepare('
            INSERT INTO reservations 
            (room_id, guest_id, check_in, check_out, arrival_time, guests_count, total_price, status, special_requests, payment_proof, payment_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, "pending", ?, ?, "pending")
        ');
        $stmt->execute([$room_id, $user['id'], $check_in, $check_out, $arrival_time, $guests, $total_price, $special_req, $dbPath]);
        
        // Notification
        $res_id = $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO notifications (user_id, reservation_id, type, subject, message) VALUES (?, ?, "booking_confirmation", "Booking Sent", "Your booking is pending approval.")')
            ->execute([$user['id'], $res_id]);

        header('Location: ../guest/my_bookings.php?success=1'); exit;
    }
  
}
?>
<!doctype html>
<html lang="en">
<head>
  <title>Confirm & Pay - ResortEase</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #7c3aed; --bg: #f8fafc; }
    body { background-color: var(--bg); font-family: 'Inter', sans-serif; }
    .summary-row { display: flex; justify-content: space-between; margin-bottom: 10px; color: #64748b; }
    .summary-total { display: flex; justify-content: space-between; margin-top: 20px; padding-top: 20px; border-top: 2px dashed #e2e8f0; font-weight: 700; font-size: 1.2rem; color: #1e293b; }
    .payment-method-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin-bottom: 15px; background: #f8fafc; display: flex; align-items: center; gap: 15px; }
    .qr-thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; cursor: pointer; }
    .btn-primary { background-color: var(--primary); border: none; padding: 12px; font-weight: 600; }
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

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <h3 class="fw-bold text-dark mb-4">Complete Payment</h3>
            
            <?php if ($errors): ?>
                <div class="alert alert-danger"><?php echo implode('<br>', $errors); ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-md-7">
                    <div class="card p-4 border-0 shadow-sm h-100">
                        
                        <?php if (!$hasPaymentMethods && $total_price > 0): ?>
                            <div class="alert alert-warning border-0 shadow-sm">
                                <i class="fas fa-exclamation-triangle fa-2x mb-2 d-block text-warning"></i>
                                <h5 class="alert-heading fw-bold">Payment Unavailable</h5>
                                <p class="mb-0 small">The resort has not set up any payment methods yet.</p>
                            </div>
                        <?php else: ?>
                            <h5 class="fw-bold mb-3">1. Send Payment</h5>
                            <p class="text-muted small mb-3">Please transfer the total amount to:</p>
                            
                            <?php foreach($payMethods as $pm): ?>
                                <div class="payment-method-card">
                                    <?php if($pm['qr_code_path']): ?>
                                        <img src="../<?php echo htmlspecialchars($pm['qr_code_path']); ?>" class="qr-thumb" onclick="window.open(this.src)">
                                    <?php else: ?>
                                        <div class="qr-thumb d-flex align-items-center justify-content-center bg-white"><i class="fas fa-wallet text-secondary"></i></div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($pm['provider_name']); ?></div>
                                        <div class="small text-primary fw-bold"><?php echo htmlspecialchars($pm['account_number']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($pm['account_name']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <h5 class="fw-bold mb-3 mt-4">2. Upload Proof</h5>
                            <form method="post" enctype="multipart/form-data">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
                                <input type="hidden" name="check_in" value="<?php echo $check_in; ?>">
                                <input type="hidden" name="check_out" value="<?php echo $check_out; ?>">
                                <input type="hidden" name="guests" value="<?php echo $guests; ?>">
                                <input type="hidden" name="arrival_time" value="<?php echo $arrival_time; ?>">

                                <div class="mb-3">
                                    <label class="small fw-bold text-secondary">Upload Receipt / Screenshot *</label>
                                    <input type="file" name="payment_proof" class="form-control" accept="image/*" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="small fw-bold text-secondary">Special Requests (Optional)</label>
                                    <textarea name="special_requests" class="form-control" rows="2"></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 rounded-pill mt-2">
                                    <i class="fas fa-check-circle me-2"></i> Submit Payment & Book
                                </button>
                                <a href="view.php?id=<?php echo $room_id; ?>" class="btn btn-link text-muted w-100 mt-2 text-decoration-none">Cancel</a>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-5">
                    <div class="card p-4 bg-white border-0 shadow-sm">
                        <h6 class="fw-bold mb-3 text-secondary">Booking Summary</h6>
                        <h5 class="mb-3 text-primary"><?php echo htmlspecialchars($room['room_name']); ?></h5>
                        
                        <div class="summary-row"><span>Check-in</span><span class="fw-bold text-dark"><?php echo $checkInDate->format('M d, Y'); ?></span></div>
                        <div class="summary-row"><span>Check-out</span><span class="fw-bold text-dark"><?php echo $checkOutDate->format('M d, Y'); ?></span></div>
                        <div class="summary-row"><span>Arrival</span><span class="fw-bold text-dark"><?php echo date('h:i A', strtotime($arrival_time)); ?></span></div>
                        <div class="summary-row"><span>Guests</span><span class="fw-bold text-dark"><?php echo $guests; ?></span></div>
                        <hr>
                        <div class="summary-row"><span>Nights</span><span>x<?php echo $nights; ?></span></div>
                        <div class="summary-row"><span>Rate</span><span>₱<?php echo number_format($base_price, 2); ?></span></div>
                        <?php if($discount_amount > 0): ?>
                            <div class="summary-row text-success"><span>Discount</span><span>-₱<?php echo number_format($discount_amount, 2); ?></span></div>
                        <?php endif; ?>
                        <div class="summary-total"><span>Total to Pay</span><span class="text-primary">₱<?php echo number_format($total_price, 2); ?></span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="footer-dark">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6"><a href="#" class="footer-brand">ResortEase</a><div class="country-select d-inline-flex mb-3"><span>🇵🇭</span> <span class="ms-2">Philippines (Pilipinas)</span></div></div>
            <div class="col-md-6 text-md-end"><a href="#" class="legal-link">Data Privacy Act</a></div>
        </div>
        <div class="text-center text-muted small mt-4 pt-3 border-top border-secondary">&copy; <?php echo date('Y'); ?> ResortEase.</div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
