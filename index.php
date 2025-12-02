<?php
require_once __DIR__ . '/../config.php';
require_login();

$user = $_SESSION['user'];
if (!in_array($user['role'], ['resort_admin', 'super_admin'])) {
  header('Location: ../dashboard.php'); exit;
}

// --- MULTI-TENANCY FILTER ---
if ($user['role'] === 'super_admin') {
    // Super Admin sees everything
    $stmt = $pdo->query('SELECT r.*, (SELECT image_path FROM room_images WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as cover_image FROM rooms r ORDER BY r.id DESC');
} else {
    // Resort Admin sees ONLY their rooms
    $stmt = $pdo->prepare('SELECT r.*, (SELECT image_path FROM room_images WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as cover_image FROM rooms r WHERE owner_id = ? ORDER BY r.id DESC');
    $stmt->execute([$user['id']]);
}
$rooms = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <title>Rooms - ResortEase</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #7c3aed; --bg: #f8fafc; }
    body { background-color: var(--bg); font-family: 'Inter', sans-serif; display: flex; flex-direction: column; min-height: 100vh; }
    .main-content { flex: 1; }
    .room-card { background: white; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; transition: transform 0.2s; height: 100%; display: flex; flex-direction: column; }
    .room-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.08); border-color: #d8b4fe; }
    .room-img { height: 200px; width: 100%; object-fit: cover; background: #f1f5f9; }
    .status-badge { position: absolute; top: 10px; right: 10px; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; background: rgba(255,255,255,0.9); box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    .room-content { padding: 1.5rem; flex-grow: 1; display: flex; flex-direction: column; }
    .btn-primary { background-color: var(--primary); border: none; }
    .footer-dark { background-color: #191e1f; color: #efefef; padding: 40px 0; margin-top: 60px; }
  </style>
</head>
<body>
<?php include __DIR__ . '/../navbar.php'; ?>
<div class="container py-4 main-content">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div><h2 class="fw-bold text-dark mb-1">Rooms Management</h2><p class="text-muted small mb-0">View and manage your resort inventory</p></div>
    <a href="create.php" class="btn btn-primary rounded-pill shadow-sm px-4"><i class="fas fa-plus me-2"></i> Add Room</a>
  </div>

  <?php if (empty($rooms)): ?>
    <div class="text-center py-5 text-muted">No rooms found. Add one to get started.</div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($rooms as $room): ?>
        <div class="col-md-6 col-lg-4">
          <div class="room-card">
            <div style="position: relative;">
                <img src="<?php echo $room['cover_image'] ? '../'.$room['cover_image'] : 'https://placehold.co/600x400?text=No+Image'; ?>" class="room-img">
                <span class="status-badge text-<?php echo $room['status'] === 'available' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($room['status']); ?></span>
            </div>
            <div class="room-content">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($room['room_name']); ?></h5>
                    <span class="fw-bold text-primary">₱<?php echo number_format((float)$room['price'], 2); ?></span>
                </div>
                <p class="small text-muted mb-3"><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($room['location']); ?></p>
                <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                    <a href="calendar.php?id=<?php echo $room['id']; ?>" class="btn btn-sm btn-light text-primary fw-bold rounded-pill px-3 border"><i class="fas fa-calendar-alt me-1"></i> Availability</a>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light border rounded-circle" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v text-secondary"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                            <li><a class="dropdown-item" href="edit.php?id=<?php echo $room['id']; ?>"><i class="fas fa-edit me-2 text-primary"></i> Edit Details</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="delete.php?id=<?php echo $room['id']; ?>" onclick="return confirm('Delete?');"><i class="fas fa-trash me-2"></i> Delete</a></li>
                        </ul>
                    </div>
                </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<footer class="footer-dark"><div class="container text-center text-muted small">&copy; <?php echo date('Y'); ?> ResortEase.</div></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>