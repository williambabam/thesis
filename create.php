<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../location_helper.php';
require_login();

$user = $_SESSION['user'];
// Security: Only Admins
if (!in_array($user['role'], ['resort_admin', 'super_admin'])) {
    header('Location: ../dashboard.php'); exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    $name = trim($_POST['room_name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $capacity = intval($_POST['capacity'] ?? 0);
    $price = floatval($_POST['price'] ?? 0.0);
    $discount = intval($_POST['discount'] ?? 0);
    $has_parking = intval($_POST['has_parking'] ?? 0);
    $status = $_POST['status'] ?? 'available';
    
    $region = trim($_POST['region'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $location = implode(', ', array_filter([$city, $province, $region]));

    if ($name === '') $errors[] = 'Room name is required.';
    if ($price <= 0) $errors[] = 'Price must be positive.';
    if (empty($city)) $errors[] = 'Location is required.';

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // --- MULTI-RESORT LOGIC: Save owner_id ---
            // We pass $user['id'] as the last parameter to link this room to YOU.
            $stmt = $pdo->prepare('INSERT INTO rooms (room_name, description, capacity, price, discount_percent, has_parking, status, location, owner_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $desc, $capacity, $price, $discount, $has_parking, $status, $location, $user['id']]);
            $room_id = $pdo->lastInsertId();

            // Image Upload
            $uploadDir = __DIR__ . '/../uploads/rooms/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            // Cover Image
            if (!empty($_FILES['cover_image']['name'])) {
                $filename = time() . '_cover_' . basename($_FILES['cover_image']['name']);
                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $uploadDir . $filename)) {
                    $pdo->prepare('INSERT INTO room_images (room_id, image_path, is_primary) VALUES (?, ?, 1)')->execute([$room_id, 'uploads/rooms/' . $filename]);
                }
            }

            // Gallery Images
            if (!empty($_FILES['gallery_images']['name'][0])) {
                foreach ($_FILES['gallery_images']['name'] as $key => $fname) {
                    if ($fname) {
                        $newF = time() . '_' . $key . '_' . basename($fname);
                        if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$key], $uploadDir . $newF)) {
                            $pdo->prepare('INSERT INTO room_images (room_id, image_path, is_primary) VALUES (?, ?, 0)')->execute([$room_id, 'uploads/rooms/' . $newF]);
                        }
                    }
                }
            }

            // Packages
            if (isset($_POST['pkg_name'])) {
                $pkgStmt = $pdo->prepare('INSERT INTO room_packages (room_id, package_name, package_price, features) VALUES (?, ?, ?, ?)');
                foreach ($_POST['pkg_name'] as $k => $v) {
                    if ($v) $pkgStmt->execute([$room_id, $v, $_POST['pkg_price'][$k]??0, $_POST['pkg_features'][$k]??'']);
                }
            }

            $pdo->commit();
            header('Location: index.php'); exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error saving room: " . $e->getMessage();
        }
    }
}
$regions = getAllRegions($pdo);
?>
<!doctype html>
<html lang="en">
<head>
  <title>Add Room - ResortEase</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #7c3aed; --bg: #f5f3ff; }
    body { background-color: var(--bg); font-family: 'Inter', sans-serif; }
    .form-card { background: white; border-radius: 16px; padding: 2rem; border: 1px solid #ede9fe; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
    .section-title { font-weight: 700; color: #374151; margin-bottom: 1.5rem; border-bottom: 2px solid #f3f4f6; padding-bottom: 0.5rem; margin-top: 1rem; }
    .btn-primary { background-color: var(--primary); border: none; padding: 12px 24px; font-weight: 600; }
    .btn-primary:hover { background-color: #6d28d9; }
    .package-item { background: #f8fafc; border: 1px dashed #94a3b8; padding: 20px; border-radius: 8px; margin-bottom: 15px; position: relative; }
    .remove-pkg { position: absolute; top: 10px; right: 10px; color: #ef4444; cursor: pointer; }
    .footer-dark { background-color: #191e1f; color: #efefef; padding: 40px 0; margin-top: 60px; }
    .country-select { display: inline-flex; align-items: center; gap: 10px; border: 1px solid #4b5563; padding: 8px 12px; border-radius: 4px; color: #fff; cursor: pointer; }
    .social-icons a { color: white; margin-right: 15px; font-size: 1.2rem; opacity: 0.8; }
    .legal-link { color: #9ca3af; text-decoration: none; }
  </style>
</head>
<body>
<?php include __DIR__ . '/../navbar.php'; ?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark">Add New Room</h2>
        <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
    </div>

    <?php if ($errors): ?><div class="alert alert-danger"><?php echo implode('<br>', $errors); ?></div><?php endif; ?>
    
    <form method="post" enctype="multipart/form-data" class="form-card shadow-sm">
        <?php echo csrf_field(); ?>
        
        <h5 class="section-title">1. Details</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-8"><label class="form-label small fw-bold">Room Name</label><input type="text" name="room_name" class="form-control" required></div>
            <div class="col-md-4">
                <label class="form-label small fw-bold">Status</label>
                <select name="status" class="form-select">
                    <option value="available">Available</option>
                    <option value="unavailable">Not Available</option>
                </select>
            </div>
            <div class="col-12"><label class="form-label small fw-bold">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
        </div>

        <h5 class="section-title">2. Location</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-4"><label class="form-label small fw-bold">Region</label><select name="region" id="region" class="form-select" required><option value="">Select Region</option><?php foreach($regions as $r) echo "<option value='$r'>$r</option>"; ?></select></div>
            <div class="col-md-4"><label class="form-label small fw-bold">Province</label><select name="province" id="province" class="form-select" disabled required><option value="">Select Province</option></select></div>
            <div class="col-md-4"><label class="form-label small fw-bold">City</label><select name="city" id="city" class="form-select" disabled required><option value="">Select City</option></select></div>
        </div>

        <h5 class="section-title">3. Pricing & Features</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-3"><label class="form-label small fw-bold">Price (₱)</label><input type="number" step="0.01" name="price" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label small fw-bold">Discount (%)</label><input type="number" name="discount" class="form-control" value="0"></div>
            <div class="col-md-3"><label class="form-label small fw-bold">Capacity</label><input type="number" name="capacity" class="form-control" value="2"></div>
            <div class="col-md-3"><label class="form-label small fw-bold">Parking</label><select name="has_parking" class="form-select"><option value="0">No</option><option value="1">Yes, Free</option></select></div>
        </div>

        <h5 class="section-title">4. Photos</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-6"><label class="form-label fw-bold text-primary">Cover Image</label><input type="file" name="cover_image" class="form-control" accept="image/*" required></div>
            <div class="col-md-6"><label class="form-label fw-bold text-secondary">Gallery</label><input type="file" name="gallery_images[]" class="form-control" multiple accept="image/*"></div>
        </div>

        <h5 class="section-title">5. Packages</h5>
        <div id="packages-container"></div>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addPackage()">+ Add Package</button>

        <hr class="my-4">
        <div class="text-end"><button type="submit" class="btn btn-primary btn-lg rounded-pill px-5">Save Room</button></div>
    </form>
</div>

<footer class="footer-dark"><div class="container text-center text-muted small mt-5 pt-4 border-top border-secondary">&copy; <?php echo date('Y'); ?> ResortEase.</div></footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const region = document.getElementById('region'), province = document.getElementById('province'), city = document.getElementById('city');
    
    // FIXED PATHS: Using ../get_locations.php
    region.addEventListener('change', function() {
        if(!this.value) { province.disabled = true; return; }
        fetch('../get_locations.php?action=get_provinces&region=' + encodeURIComponent(this.value)).then(res=>res.json()).then(data=>{
            province.innerHTML='<option value="">Select Province</option>';
            data.forEach(p=>{province.innerHTML+=`<option value="${p}">${p}</option>`});
            province.disabled=false;
        }).catch(e => console.error("Location Error:", e));
    });
    province.addEventListener('change', function() {
        if(!this.value) { city.disabled = true; return; }
        fetch('../get_locations.php?action=get_cities&province=' + encodeURIComponent(this.value)).then(res=>res.json()).then(data=>{
            city.innerHTML='<option value="">Select City</option>';
            data.forEach(c=>{city.innerHTML+=`<option value="${c}">${c}</option>`});
            city.disabled=false;
        });
    });
});

function addPackage() {
    const container = document.getElementById('packages-container');
    const div = document.createElement('div');
    div.className = 'row g-2 mb-2 p-2 border rounded bg-light';
    div.innerHTML = `<div class="col-4"><input type="text" name="pkg_name[]" class="form-control form-control-sm" placeholder="Name" required></div><div class="col-3"><input type="number" name="pkg_price[]" class="form-control form-control-sm" placeholder="Price"></div><div class="col-4"><input type="text" name="pkg_features[]" class="form-control form-control-sm" placeholder="Features"></div><div class="col-1"><button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">x</button></div>`;
    container.appendChild(div);
}
</script>
</body>
</html>