//browse.php//
<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../location_helper.php';
require_once __DIR__ . '/../navbar.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$region   = trim($_GET['region'] ?? '');
$province = trim($_GET['province'] ?? '');
$city     = trim($_GET['city'] ?? '');
$min_price= floatval($_GET['min_price'] ?? 0);
$max_price= floatval($_GET['max_price'] ?? 0);
$capacity = intval($_GET['capacity'] ?? 0);
$package  = trim($_GET['package'] ?? '');

$sql = 'SELECT r.*, 
        (SELECT image_path FROM room_images WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as image,
        (SELECT GROUP_CONCAT(package_name SEPARATOR ", ") FROM room_packages WHERE room_id = r.id) as packages
        FROM rooms r 
        LEFT JOIN room_packages rp ON r.id = rp.room_id
        WHERE (r.status = "available" OR r.status = "active")';

$params = [];

if (!empty($city)) { $sql .= ' AND r.location LIKE ?'; $params[] = "%$city%"; }
elseif (!empty($province)) { $sql .= ' AND r.location LIKE ?'; $params[] = "%$province%"; }
elseif (!empty($region)) { $sql .= ' AND r.location LIKE ?'; $params[] = "%$region%"; }

if ($min_price > 0) { $sql .= ' AND r.price >= ?'; $params[] = $min_price; }
if ($max_price > 0) { $sql .= ' AND r.price <= ?'; $params[] = $max_price; }
if ($capacity > 0)  { $sql .= ' AND r.capacity >= ?'; $params[] = $capacity; }

if (!empty($package)) { $sql .= ' AND rp.package_name LIKE ?'; $params[] = "%$package%"; }

$sql .= ' GROUP BY r.id ORDER BY r.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll();

$regions = getAllRegions($pdo);
$availPackages = $pdo->query("SELECT DISTINCT package_name FROM room_packages ORDER BY package_name ASC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!doctype html>
<html lang="en">
<head>
  <title>Browse Resorts - ResortEase</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #7c3aed; --bg: #f8fafc; }
    body { background-color: var(--bg); font-family: 'Inter', sans-serif; display: flex; flex-direction: column; min-height: 100vh; }
    .main-content { flex: 1; }
    .filter-card { background: white; border-radius: 16px; padding: 25px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.03); margin-bottom: 30px; }
    .room-card { background: white; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s; height: 100%; display: flex; flex-direction: column; }
    .room-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.08); border-color: #d8b4fe; }
    .room-img-wrapper { position: relative; height: 220px; overflow: hidden; background-color: #f1f5f9; }
    .room-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
    .room-card:hover .room-img { transform: scale(1.05); }
    .discount-badge { position: absolute; top: 15px; left: 15px; background: #ef4444; color: white; padding: 5px 12px; border-radius: 20px; font-weight: 700; font-size: 0.8rem; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
    .pkg-badge { background: #f3e8ff; color: var(--primary); font-size: 0.75rem; padding: 4px 10px; border-radius: 6px; font-weight: 600; display: inline-block; margin-right: 5px; margin-bottom: 5px; }
    .btn-primary { background-color: var(--primary); border: none; padding: 10px 20px; font-weight: 600; border-radius: 8px; }
    .btn-primary:hover { background-color: #6d28d9; }
    .footer-dark { background-color: #191e1f; color: #efefef; padding: 40px 0; font-size: 14px; margin-top: 60px; }
    .footer-brand { font-weight: 800; font-size: 24px; color: white; text-decoration: none; display: block; margin-bottom: 15px; }
    .country-select { display: inline-flex; align-items: center; gap: 10px; border: 1px solid #4b5563; padding: 8px 12px; border-radius: 4px; color: #fff; font-size: 0.9rem; cursor: pointer; }
    .social-icons a { color: white; margin-right: 15px; font-size: 1.2rem; opacity: 0.8; transition: 0.2s; }
    .legal-link { color: #9ca3af; text-decoration: none; }
  </style>
</head>
<body>

<div class="container py-4 main-content">
    <div class="filter-card">
        <form method="get">
            <h5 class="fw-bold text-dark mb-3"><i class="fas fa-sliders-h me-2 text-primary"></i>Filter Your Stay</h5>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Region</label>
                    <select name="region" id="region" class="form-select"><option value="">All Regions</option><?php foreach($regions as $r) echo "<option value='".htmlspecialchars($r)."' ".($region===$r?'selected':'').">$r</option>"; ?></select>
                </div>
                <div class="col-md-4"><label class="form-label">Province</label><select name="province" id="province" class="form-select" <?php if(empty($province)) echo 'disabled'; ?>><option value="">All Provinces</option><?php if(!empty($province)) echo "<option value='$province' selected>$province</option>"; ?></select></div>
                <div class="col-md-4"><label class="form-label">City</label><select name="city" id="city" class="form-select" <?php if(empty($city)) echo 'disabled'; ?>><option value="">All Cities</option><?php if(!empty($city)) echo "<option value='$city' selected>$city</option>"; ?></select></div>
            </div>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Package</label>
                    <select name="package" class="form-select"><option value="">Any</option><?php foreach($availPackages as $pkg) echo "<option value='".htmlspecialchars($pkg)."' ".($package===$pkg?'selected':'').">$pkg</option>"; ?></select>
                </div>
                <div class="col-md-2"><label class="form-label">Guests</label><input type="number" name="capacity" class="form-control" min="1" value="<?php echo $capacity ?: ''; ?>"></div>
                <div class="col-md-2"><label class="form-label">Max Price</label><input type="number" name="max_price" class="form-control" value="<?php echo $max_price ?: ''; ?>"></div>
                <div class="col-md-3 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100 me-2">Search</button><a href="browse.php" class="btn btn-light border w-50">Clear</a></div>
            </div>
        </form>
    </div>

    <h4 class="fw-bold text-dark mb-4">Available Rooms</h4>

    <?php if (empty($rooms)): ?>
        <div class="text-center py-5"><div class="bg-white p-5 rounded-4 shadow-sm d-inline-block border"><i class="fas fa-search fa-3x text-muted mb-3 opacity-50"></i><h5 class="fw-bold text-secondary">No resorts found</h5></div></div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($rooms as $room): ?>
            <div class="col-md-6 col-lg-4">
                <div class="room-card">
                    <div class="room-img-wrapper">
                        <?php if($room['discount_percent'] > 0): ?><div class="discount-badge">-<?php echo $room['discount_percent']; ?>% OFF</div><?php endif; ?>
                        <?php if($room['image']): ?><img src="/resort_app/<?php echo htmlspecialchars($room['image']); ?>" class="room-img"><?php else: ?><div class="w-100 h-100 d-flex align-items-center justify-content-center bg-light text-muted"><i class="fas fa-image fa-3x opacity-50"></i></div><?php endif; ?>
                    </div>
                    <div class="p-4 flex-grow-1 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2"><h5 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($room['room_name']); ?></h5><span class="badge bg-light text-dark border"><i class="fas fa-user-friends me-1"></i> <?php echo $room['capacity']; ?></span></div>
                        <p class="text-muted small mb-3"><i class="fas fa-map-marker-alt text-danger me-1"></i> <?php echo htmlspecialchars($room['location']); ?></p>
                        <?php if($room['packages']): ?><div class="mb-3"><?php foreach(explode(', ', $room['packages']) as $pkg): ?><span class="pkg-badge"><i class="fas fa-gift me-1"></i><?php echo htmlspecialchars($pkg); ?></span><?php endforeach; ?></div><?php endif; ?>
                        <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                            <div><small class="text-muted d-block">Price per night</small><span class="text-primary fw-bold fs-5">₱<?php echo number_format((float)$room['price'], 2); ?></span></div>
                            
                            <a href="/resort_app/rooms/view.php?id=<?php echo $room['id']; ?>" class="btn btn-outline-primary rounded-pill px-4 fw-bold">View Details</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
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
    const region = document.getElementById('region'), province = document.getElementById('province'), city = document.getElementById('city');
    region.addEventListener('change', function() {
        province.innerHTML='<option value="">Loading...</option>'; city.innerHTML='<option value="">All Cities</option>'; city.disabled=true;
        if(!this.value) { province.innerHTML='<option value="">All Provinces</option>'; province.disabled=true; return; }
        fetch('/resort_app/get_locations.php?action=get_provinces&region='+encodeURIComponent(this.value)).then(res=>res.json()).then(data=>{
            province.innerHTML='<option value="">All Provinces</option>'; data.forEach(p=>{province.innerHTML+=`<option value="${p}">${p}</option>`}); province.disabled=false;
        });
    });
    province.addEventListener('change', function() {
        city.innerHTML='<option value="">Loading...</option>';
        if(!this.value) { city.innerHTML='<option value="">All Cities</option>'; city.disabled=true; return; }
        fetch('/resort_app/get_locations.php?action=get_cities&province='+encodeURIComponent(this.value)).then(res=>res.json()).then(data=>{
            city.innerHTML='<option value="">All Cities</option>'; data.forEach(c=>{city.innerHTML+=`<option value="${c}">${c}</option>`}); city.disabled=false;
        });
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

