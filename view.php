<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: ../guest/browse.php'); exit; }

$stmt = $pdo->prepare('
    SELECT r.*, 
    GROUP_CONCAT(DISTINCT CONCAT(p.package_name, " (₱", p.package_price, ")") SEPARATOR "||") as packages_data
    FROM rooms r 
    LEFT JOIN room_packages p ON r.id = p.room_id
    WHERE r.id = ?
    GROUP BY r.id
');
$stmt->execute([$id]);
$room = $stmt->fetch();

if (!$room) { header('Location: ../guest/browse.php'); exit; }

$settings = $pdo->query("SELECT setting_key, setting_value FROM resort_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$resortName = $settings['resort_name'] ?? 'ResortEase';
$refundPct = $settings['refund_percentage'] ?? '100';

$stmt = $pdo->prepare('SELECT * FROM room_images WHERE room_id = ? ORDER BY is_primary DESC');
$stmt->execute([$id]);
$images = $stmt->fetchAll();
$coverImage = $images[0]['image_path'] ?? '';

$blockedDates = [];
$resStmt = $pdo->prepare('SELECT check_in, check_out FROM reservations WHERE room_id = ? AND status IN ("confirmed", "checked_in", "pending")');
$resStmt->execute([$id]);
while ($row = $resStmt->fetch()) {
    $period = new DatePeriod(new DateTime($row['check_in']), new DateInterval('P1D'), (new DateTime($row['check_out']))->modify('+1 day'));
    foreach ($period as $date) $blockedDates[] = $date->format('Y-m-d');
}
$blockStmt = $pdo->prepare('SELECT blocked_date FROM room_availability WHERE room_id = ?');
$blockStmt->execute([$id]);
while ($row = $blockStmt->fetch()) $blockedDates[] = $row['blocked_date'];
$blockedDatesJson = json_encode(array_unique($blockedDates));
?>
<!doctype html>
<html lang="en">
<head>
  <title><?php echo htmlspecialchars($room['room_name']); ?> - <?php echo htmlspecialchars($resortName); ?></title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <style>
    :root { --primary: #7c3aed; --bg: #f8fafc; }
    body { background-color: var(--bg); font-family: 'Inter', sans-serif; display: flex; flex-direction: column; min-height: 100vh; }
    .main-content { flex: 1; }
    .room-hero { height: 400px; position: relative; background-color: #ddd; overflow: hidden; }
    .room-hero img { width: 100%; height: 100%; object-fit: cover; }
    .room-hero-overlay { position: absolute; bottom: 0; left: 0; width: 100%; background: linear-gradient(to top, rgba(0,0,0,0.8), transparent); padding: 60px 0 30px; color: white; }
    .booking-card { background: white; padding: 30px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(0,0,0,0.08); position: sticky; top: 90px; }
    .price-tag { font-size: 2rem; font-weight: 800; color: var(--primary); }
    .amenity-badge { background: #f1f5f9; padding: 8px 15px; border-radius: 8px; margin-right: 10px; margin-bottom: 10px; display: inline-block; font-size: 0.9rem; color: #475569; }
    .policy-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 25px; margin-top: 30px; }
    .btn-primary { background-color: var(--primary); border: none; font-weight: 600; padding: 12px; width: 100%; }
    .btn-primary:hover { background-color: #6d28d9; }
    
    /* Footer */
    .footer-dark { background-color: #191e1f; color: #efefef; padding: 40px 0; font-size: 14px; margin-top: 60px; }
    .footer-brand { font-weight: 800; font-size: 24px; color: white; text-decoration: none; }
    .country-select { display: inline-flex; align-items: center; gap: 10px; border: 1px solid #4b5563; padding: 8px 12px; border-radius: 4px; color: #fff; cursor: pointer; }
    .social-icons a { color: white; margin-right: 15px; font-size: 1.2rem; opacity: 0.8; }
    .legal-link { color: #9ca3af; text-decoration: none; }
  </style>
</head>
<body>
<?php include __DIR__ . '/../navbar.php'; ?>

<div class="room-hero">
    <?php if ($coverImage): ?>
        <img src="../<?php echo htmlspecialchars($coverImage); ?>">
    <?php else: ?>
        <div class="d-flex align-items-center justify-content-center h-100 bg-secondary text-white">No Image Available</div>
    <?php endif; ?>
    <div class="room-hero-overlay">
        <div class="container">
            <h1 class="fw-bold mb-1"><?php echo htmlspecialchars($room['room_name']); ?></h1>
            <p class="mb-0"><i class="fas fa-map-marker-alt me-2 text-warning"></i><?php echo htmlspecialchars($room['location']); ?></p>
        </div>
    </div>
</div>

<div class="container py-5 main-content">
    <div class="row">
        <div class="col-lg-8">
            <h4 class="fw-bold text-dark mb-3">About this stay</h4>
            <p class="text-secondary leading-relaxed mb-5" style="line-height: 1.8;">
                <?php echo nl2br(htmlspecialchars($room['description'])); ?>
            </p>

            <h5 class="fw-bold text-dark mb-3">Amenities & Features</h5>
            <div class="mb-5">
                <?php if($room['has_parking']): ?>
                    <span class="amenity-badge"><i class="fas fa-car me-2 text-primary"></i>Free Parking</span>
                <?php endif; ?>
                <span class="amenity-badge"><i class="fas fa-user-friends me-2 text-primary"></i>Max <?php echo $room['capacity']; ?> Guests</span>
                
                <?php if($room['packages_data']): ?>
                    <div class="mt-3">
                        <h6 class="fw-bold small text-secondary mb-2">Available Packages:</h6>
                        <?php foreach(explode('||', $room['packages_data']) as $pkg): ?>
                            <span class="amenity-badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10">
                                <i class="fas fa-gift me-2"></i><?php echo htmlspecialchars($pkg); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="policy-box shadow-sm">
                <h5 class="fw-bold text-dark mb-4">
                    <i class="fas fa-hotel me-2 text-primary"></i><?php echo htmlspecialchars($resortName); ?>
                </h5>
                
                <div class="row g-4">
                    <div class="col-12">
                        <div class="bg-light p-3 rounded border">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold text-secondary small mb-0 text-uppercase">Cancellation Policy</h6>
                                <span class="badge bg-success">Refund: <?php echo htmlspecialchars($refundPct); ?>%</span>
                            </div>
                            <p class="text-muted small mb-0"><?php echo nl2br(htmlspecialchars($settings['cancellation_policy'] ?? 'Standard policy applies.')); ?></p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <h6 class="fw-bold text-dark small mb-2">CONTACT US</h6>
                        <div class="text-muted small">
                            <div class="mb-1"><i class="fas fa-envelope me-2 text-secondary"></i> <?php echo htmlspecialchars($settings['contact_email'] ?? 'N/A'); ?></div>
                            <div><i class="fas fa-phone me-2 text-secondary"></i> <?php echo htmlspecialchars($settings['phone'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                    
                    <?php if(!empty($settings['address'])): ?>
                    <div class="col-md-6">
                        <h6 class="fw-bold text-dark small mb-2">ADDRESS</h6>
                        <p class="text-muted small mb-0"><i class="fas fa-map-pin me-2 text-secondary"></i> <?php echo htmlspecialchars($settings['address']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if(count($images) > 1): ?>
                <h5 class="fw-bold text-dark mt-5 mb-3">Gallery</h5>
                <div class="row g-3">
                    <?php foreach(array_slice($images, 1) as $img): ?>
                    <div class="col-md-6">
                        <img src="../<?php echo htmlspecialchars($img['image_path']); ?>" class="img-fluid rounded-3 shadow-sm w-100" style="height: 200px; object-fit: cover;">
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="booking-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <span class="price-tag">₱<?php echo number_format((float)$room['price'], 2); ?></span>
                        <span class="text-muted small">/ night</span>
                    </div>
                    <?php if($room['discount_percent'] > 0): ?>
                        <span class="badge bg-danger rounded-pill px-3 py-2"><?php echo $room['discount_percent']; ?>% OFF</span>
                    <?php endif; ?>
                </div>

                <form action="book.php" method="get">
                    <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Check-in / Check-out</label>
                        <input type="text" id="date_range" class="form-control bg-white" placeholder="Select Dates" required>
                        <input type="hidden" name="check_in" id="check_in">
                        <input type="hidden" name="check_out" id="check_out">
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">Guests</label>
                        <input type="number" name="guests" class="form-control" min="1" max="<?php echo $room['capacity']; ?>" value="1">
                    </div>
                    <button type="submit" class="btn btn-primary rounded-pill">Reserve</button>
                    <div class="text-center mt-3"><small class="text-muted">You won't be charged yet</small></div>
                </form>
            </div>
        </div>
    </div>
</div>

<footer class="footer-dark">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <a href="#" class="footer-brand"><?php echo htmlspecialchars($resortName); ?></a>
                <div class="country-select d-inline-flex mb-3"><span>🇵🇭</span> <span class="ms-2">Philippines (Pilipinas)</span> <i class="fas fa-chevron-down ms-2 small"></i></div>
                <div class="social-icons"><a href="#"><i class="fab fa-facebook"></i></a><a href="#"><i class="fab fa-twitter"></i></a><a href="#"><i class="fab fa-instagram"></i></a></div>
            </div>
            <div class="col-md-6 text-md-end mt-4 mt-md-0">
                <h6 class="text-white fw-bold mb-2">Legal</h6>
                <a href="https://privacy.gov.ph/data-privacy-act/" target="_blank" class="legal-link">Data Privacy Act of 2012 <i class="fas fa-external-link-alt small ms-1"></i></a>
            </div>
        </div>
        <div class="text-center text-muted small mt-5 pt-4 border-top border-secondary">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($resortName); ?> Philippines. All rights reserved.</div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    const blockedDates = <?php echo $blockedDatesJson; ?>;
    flatpickr("#date_range", {
        mode: "range",
        minDate: "today",
        dateFormat: "Y-m-d",
        disable: blockedDates,
        onChange: function(selectedDates, dateStr, instance) {
            if (selectedDates.length === 2) {
                document.getElementById('check_in').value = instance.formatDate(selectedDates[0], "Y-m-d");
                document.getElementById('check_out').value = instance.formatDate(selectedDates[1], "Y-m-d");
            }
        }
    });
</script>
</body>
</html>