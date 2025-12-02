// calendar.php // 
<?php
require_once __DIR__ . '/../config.php';
require_login();

$user = $_SESSION['user'];
if (!in_array($user['role'], ['resort_admin', 'super_admin'])) {
    header('Location: ../dashboard.php'); exit;
}

$room_id = intval($_GET['id'] ?? 0);
if ($room_id <= 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare('SELECT room_name FROM rooms WHERE id = ?');
$stmt->execute([$room_id]);
$room = $stmt->fetch();
?>
<!doctype html>
<html lang="en">
<head>
  <title>Manage Availability - ResortEase</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
  <style>
    :root { --primary: #7c3aed; --bg: #f8fafc; }
    body { background-color: var(--bg); font-family: 'Inter', sans-serif; }
    .calendar-card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .legend-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 5px; }
    /* Footer */
    .footer-dark { background-color: #191e1f; color: #efefef; padding: 40px 0; margin-top: 40px; }
    .footer-brand { font-weight: 800; font-size: 24px; color: white; text-decoration: none; display: block; margin-bottom: 15px; }
    .country-select { display: inline-flex; align-items: center; gap: 10px; border: 1px solid #4b5563; padding: 8px 12px; border-radius: 4px; color: #fff; cursor: pointer; }
    .social-icons a { color: white; margin-right: 15px; font-size: 1.2rem; opacity: 0.8; }
    .legal-link { color: #9ca3af; text-decoration: none; }
  </style>
</head>
<body>
<?php include __DIR__ . '/../navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark"><?php echo htmlspecialchars($room['room_name']); ?></h2>
        <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">Back to Rooms</a>
    </div>

    <div class="calendar-card">
        <div class="mb-3 text-center">
            <span class="me-3"><span class="legend-dot" style="background: #7c3aed;"></span> Booked</span>
            <span><span class="legend-dot" style="background: #64748b;"></span> Maintenance</span>
        </div>
        <div id="calendar"></div>
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
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var roomId = <?php echo $room_id; ?>;
    // Use absolute path to ensure it finds the file
    var actionUrl = '/resort_app/rooms/calendar_action.php';

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth' },
        events: actionUrl + '?action=fetch&room_id=' + roomId,
        
        dateClick: function(info) {
            if(confirm('Toggle blocked status for ' + info.dateStr + '?')) {
                fetch(actionUrl + '?action=toggle&room_id=' + roomId, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'date=' + info.dateStr
                })
                .then(response => response.json())
                .then(data => {
                    if(data.error) { alert('Error: ' + data.error); }
                    else { calendar.refetchEvents(); }
                })
                .catch(err => {
                    console.error(err);
                    alert('Connection Failed. Check Console.');
                });
            }
        }
    });
    calendar.render();
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
