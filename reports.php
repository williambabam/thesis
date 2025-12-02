<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();

$user = $_SESSION['user'];
if (!in_array($user['role'], ['resort_admin', 'super_admin'])) {
    header('Location: ../dashboard.php'); exit;
}

// --- ADVANCED METRICS CALCULATION ---
// 1. Basic Totals
$totalRev = $pdo->query('SELECT SUM(total_price) FROM reservations WHERE status IN ("confirmed","checked_in","checked_out")')->fetchColumn();
$totalBookings = $pdo->query('SELECT COUNT(*) FROM reservations')->fetchColumn();

// 2. Cancellation Rate
$cancelCount = $pdo->query('SELECT COUNT(*) FROM reservations WHERE status = "cancelled"')->fetchColumn();
$cancelRate = ($totalBookings > 0) ? ($cancelCount / $totalBookings) * 100 : 0;

// 3. Average Lead Time (Days before check-in)
$avgLeadTime = $pdo->query('SELECT AVG(DATEDIFF(check_in, created_at)) FROM reservations')->fetchColumn();

// 4. Popular Room
$popularRoom = $pdo->query('SELECT ro.room_name FROM reservations r JOIN rooms ro ON r.room_id = ro.id GROUP BY r.room_id ORDER BY COUNT(*) DESC LIMIT 1')->fetchColumn();

// 5. Chart Data (Last 6 Months)
$chartData = $pdo->query('
    SELECT DATE_FORMAT(created_at, "%Y-%m") as m, 
           SUM(CASE WHEN status IN ("confirmed","checked_in","checked_out") THEN total_price ELSE 0 END) as rev, 
           COUNT(*) as cnt 
    FROM reservations 
    GROUP BY m 
    ORDER BY m ASC 
    LIMIT 6
')->fetchAll();

$labels = json_encode(array_column($chartData, 'm'));
$revenueData = json_encode(array_column($chartData, 'rev'));
$bookingData = json_encode(array_column($chartData, 'cnt'));
?>
<!doctype html>
<html lang="en">
<head>
  <title>Analytics - ResortEase</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <style>
    :root { --primary: #7c3aed; --bg: #f5f3ff; }
    body { background-color: var(--bg); font-family: 'Inter', sans-serif; display: flex; flex-direction: column; min-height: 100vh; }
    .main-content { flex: 1; }
    
    /* Cards */
    .stat-card { background: white; padding: 20px; border-radius: 16px; border: 1px solid #ede9fe; box-shadow: 0 2px 10px rgba(0,0,0,0.02); height: 100%; display: flex; flex-direction: column; justify-content: center; }
    .stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 10px; }
    
    /* Graph Card */
    .chart-card { background: white; border-radius: 16px; padding: 25px; border: 1px solid #ede9fe; box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-bottom: 30px; }
    
    /* Footer */
    .footer-dark { background-color: #191e1f; color: #efefef; padding: 40px 0; font-size: 14px; margin-top: 60px; }
    .footer-brand { font-weight: 800; font-size: 24px; color: white; text-decoration: none; display: block; margin-bottom: 20px; }
    .country-select { display: inline-flex; align-items: center; gap: 10px; border: 1px solid #4b5563; padding: 8px 12px; border-radius: 4px; color: #fff; cursor: pointer; }
    .social-icons a { color: white; margin-right: 15px; font-size: 1.2rem; opacity: 0.8; }
    .legal-link { color: #9ca3af; text-decoration: none; }
  </style>
</head>
<body>
<?php include __DIR__ . '/../navbar.php'; ?>

<div class="container py-4 main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">Performance Reports</h2>
            <p class="text-muted small mb-0">Financial overview and occupancy analytics</p>
        </div>
        <button class="btn btn-outline-secondary rounded-pill btn-sm" onclick="window.print()"><i class="fas fa-print me-2"></i>Print Report</button>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card border-start border-4 border-success">
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="fas fa-coins"></i></div>
                <small class="text-muted fw-bold text-uppercase">Total Revenue</small>
                <h3 class="fw-bold text-dark mb-0">₱<?php echo number_format((float)($totalRev ?? 0), 2); ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card border-start border-4 border-primary">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-calendar-check"></i></div>
                <small class="text-muted fw-bold text-uppercase">Total Bookings</small>
                <h3 class="fw-bold text-dark mb-0"><?php echo number_format((int)($totalBookings ?? 0)); ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card border-start border-4 border-danger">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="fas fa-user-times"></i></div>
                <small class="text-muted fw-bold text-uppercase">Cancellation Rate</small>
                <h3 class="fw-bold text-dark mb-0"><?php echo number_format($cancelRate, 1); ?>%</h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card border-start border-4 border-warning">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-clock"></i></div>
                <small class="text-muted fw-bold text-uppercase">Avg Lead Time</small>
                <h3 class="fw-bold text-dark mb-0"><?php echo number_format((float)$avgLeadTime, 0); ?> Days</h3>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="chart-card">
                <h5 class="fw-bold text-dark mb-4">Revenue Trend (Last 6 Months)</h5>
                <canvas id="revenueChart" height="300"></canvas> </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-card h-100">
                <h5 class="fw-bold text-dark mb-4">Top Performing Room</h5>
                <div class="text-center py-5">
                    <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex p-4 mb-3 text-primary">
                        <i class="fas fa-trophy fa-3x"></i>
                    </div>
                    <h4 class="fw-bold text-dark"><?php echo htmlspecialchars($popularRoom ?: 'No Data'); ?></h4>
                    <p class="text-muted small">Most booked room type</p>
                </div>
            </div>
        </div>
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

<script>
    const ctx = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo $labels; ?>,
            datasets: [{
                label: 'Revenue (₱)',
                data: <?php echo $revenueData; ?>,
                borderColor: '#7c3aed',
                backgroundColor: 'rgba(124, 58, 237, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>