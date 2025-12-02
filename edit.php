<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../location_helper.php';
require_login();

// 1. Security Check
$user = $_SESSION['user'];
if (!in_array($user['role'], ['resort_admin', 'super_admin'])) {
    // If not admin, send to dashboard
    header('Location: ../dashboard.php'); 
    exit;
}

// 2. Get Room ID
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    // If ID invalid, go back to ROOM LIST (not dashboard)
    header('Location: index.php'); 
    exit;
}

// 3. Fetch Room Data
$stmt = $pdo->prepare('SELECT * FROM rooms WHERE id = ?');
$stmt->execute([$id]);
$room = $stmt->fetch();

if (!$room) {
    header('Location: index.php');
    exit;
}

// Fetch Images & Packages
$imgStmt = $pdo->prepare('SELECT * FROM room_images WHERE room_id = ?');
$imgStmt->execute([$id]);
$images = $imgStmt->fetchAll();

$pkgStmt = $pdo->prepare('SELECT * FROM room_packages WHERE room_id = ?');
$pkgStmt->execute([$id]);
$packages = $pkgStmt->fetchAll();

// 4. Handle Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); // Security Token Check

    // Update Basic Info
    $sql = "UPDATE rooms SET room_name=?, description=?, capacity=?, price=?, discount_percent=?, has_parking=?, status=? WHERE id=?";
    $pdo->prepare($sql)->execute([
        $_POST['room_name'], 
        $_POST['description'], 
        $_POST['capacity'], 
        $_POST['price'], 
        $_POST['discount'], 
        $_POST['has_parking'], 
        $_POST['status'], 
        $id
    ]);

    // Update Location (Only if changed)
    if (!empty($_POST['region'])) {
        $loc = implode(', ', array_filter([$_POST['city'], $_POST['province'], $_POST['region']]));
        $pdo->prepare("UPDATE rooms SET location=? WHERE id=?")->execute([$loc, $id]);
    }

    // Handle New Images
    if (!empty($_FILES['new_images']['name'][0])) {
        $uploadDir = __DIR__ . '/../uploads/rooms/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        foreach ($_FILES['new_images']['name'] as $key => $name) {
            if ($name) {
                $newName = time() . '_' . $key . '_' . basename($name);
                if (move_uploaded_file($_FILES['new_images']['tmp_name'][$key], $uploadDir . $newName)) {
                    $pdo->prepare("INSERT INTO room_images (room_id, image_path) VALUES (?, ?)")
                        ->execute([$id, 'uploads/rooms/' . $newName]);
                }
            }
        }
    }

    // Handle Image Deletion
    if (isset($_POST['delete_image'])) {
        foreach ($_POST['delete_image'] as $imgId) {
            $pdo->prepare("DELETE FROM room_images WHERE id = ?")->execute([$imgId]);
        }
    }

    // Handle Packages (Delete Old, Insert New)
    $pdo->prepare("DELETE FROM room_packages WHERE room_id = ?")->execute([$id]);
    if (isset($_POST['pkg_name'])) {
        $insPkg = $pdo->prepare("INSERT INTO room_packages (room_id, package_name, package_price, features) VALUES (?,?,?,?)");
        foreach ($_POST['pkg_name'] as $k => $v) {
            if ($v) {
                $insPkg->execute([
                    $id, 
                    $v, 
                    $_POST['pkg_price'][$k] ?? 0, 
                    $_POST['pkg_features'][$k] ?? ''
                ]);
            }
        }
    }
    
    $_SESSION['success'] = "Room updated successfully!";
    header("Location: edit.php?id=$id"); // Reload page
    exit;
}

$regions = getAllRegions($pdo);
?>
<!doctype html>
<html lang="en">
<head>
  <title>Edit Room - ResortEase</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #7c3aed; --bg: #f5f3ff; }
    body { background-color: var(--bg); font-family: 'Inter', sans-serif; }
    .form-card { background: white; border-radius: 16px; padding: 2rem; border: 1px solid #ede9fe; margin-bottom: 2rem; }
    .btn-primary { background-color: var(--primary); border: none; }
    .img-thumbnail-wrapper { position: relative; display: inline-block; margin: 5px; }
    .btn-delete-img { position: absolute; top: -5px; right: -5px; background: red; color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; }
    .footer-dark { background-color: #191e1f; color: #efefef; padding: 40px 0; margin-top: 60px; }
    .footer-brand { font-weight: 800; font-size: 24px; color: white; text-decoration: none; }
  </style>
</head>
<body>
<?php include __DIR__ . '/../navbar.php'; ?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark">Edit Room</h2>
        <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">Back</a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        
        <div class="form-card">
            <h5 class="fw-bold text-primary mb-3">Details & Pricing</h5>
            <div class="row g-3">
                <div class="col-md-8"><label class="small fw-bold">Name</label><input type="text" name="room_name" class="form-control" value="<?php echo htmlspecialchars($room['room_name']); ?>" required></div>
                <div class="col-md-4">
                    <label class="small fw-bold">Status</label>
                    <select name="status" class="form-select">
                        <option value="available" <?php if($room['status']=='available') echo 'selected'; ?>>Available</option>
                        <option value="unavailable" <?php if($room['status']=='unavailable') echo 'selected'; ?>>Not Available</option>
                    </select>
                </div>
                <div class="col-md-3"><label class="small fw-bold">Price (₱)</label><input type="number" name="price" class="form-control" value="<?php echo $room['price']; ?>"></div>
                <div class="col-md-3"><label class="small fw-bold">Discount (%)</label><input type="number" name="discount" class="form-control" value="<?php echo $room['discount_percent']; ?>"></div>
                <div class="col-md-3"><label class="small fw-bold">Capacity</label><input type="number" name="capacity" class="form-control" value="<?php echo $room['capacity']; ?>"></div>
                <div class="col-md-3"><label class="small fw-bold">Parking</label><select name="has_parking" class="form-select"><option value="1" <?php if($room['has_parking']) echo 'selected'; ?>>Yes</option><option value="0" <?php if(!$room['has_parking']) echo 'selected'; ?>>No</option></select></div>
                <div class="col-12"><label class="small fw-bold">Description</label><textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($room['description']); ?></textarea></div>
            </div>
        </div>

        <div class="form-card">
            <h5 class="fw-bold text-primary mb-3">Location (Current: <?php echo htmlspecialchars($room['location']); ?>)</h5>
            <div class="row g-3">
                <div class="col-md-4"><select name="region" id="region" class="form-select"><option value="">Change Region</option><?php foreach($regions as $r) echo "<option value='$r'>$r</option>"; ?></select></div>
                <div class="col-md-4"><select name="province" id="province" class="form-select" disabled><option value="">Province</option></select></div>
                <div class="col-md-4"><select name="city" id="city" class="form-select" disabled><option value="">City</option></select></div>
            </div>
        </div>

        <div class="form-card">
            <h5 class="fw-bold text-primary mb-3">Images</h5>
            <div class="mb-3">
                <?php foreach($images as $img): ?>
                    <div class="img-thumbnail-wrapper">
                        <img src="../<?php echo $img['image_path']; ?>" style="width:80px;height:80px;object-fit:cover;border-radius:8px;">
                        <label class="btn-delete-img"><input type="checkbox" name="delete_image[]" value="<?php echo $img['id']; ?>" style="display:none;"><i class="fas fa-times" onclick="this.parentElement.parentElement.style.opacity='0.3'"></i></label>
                    </div>
                <?php endforeach; ?>
            </div>
            <label class="small fw-bold">Add New Images</label>
            <input type="file" name="new_images[]" class="form-control" multiple accept="image/*">
        </div>

        <div class="form-card">
            <h5 class="fw-bold text-primary mb-3">Packages</h5>
            <div id="pkg-container">
                <?php foreach($packages as $p): ?>
                <div class="row g-2 mb-2">
                    <div class="col-4"><input type="text" name="pkg_name[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p['package_name']); ?>"></div>
                    <div class="col-3"><input type="number" name="pkg_price[]" class="form-control form-control-sm" value="<?php echo $p['package_price']; ?>"></div>
                    <div class="col-4"><input type="text" name="pkg_features[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p['features']); ?>"></div>
                    <div class="col-1"><button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">x</button></div>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addPkg()">+ Add Package</button>
        </div>

        <div class="text-end"><button type="submit" class="btn btn-primary rounded-pill px-5 shadow">Update Room</button></div>
    </form>
</div>

<footer class="footer-dark">
    <div class="container text-center text-muted small">&copy; <?php echo date('Y'); ?> ResortEase.</div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const region = document.getElementById('region'), province = document.getElementById('province'), city = document.getElementById('city');
    region.addEventListener('change', function() {
        if(!this.value) return;
        fetch('../get_locations.php?action=get_provinces&region=' + encodeURIComponent(this.value)).then(res=>res.json()).then(data=>{province.innerHTML='<option>Select Province</option>';data.forEach(p=>{province.innerHTML+=`<option value="${p}">${p}</option>`});province.disabled=false;});
    });
    province.addEventListener('change', function() {
        if(!this.value) return;
        fetch('../get_locations.php?action=get_cities&province=' + encodeURIComponent(this.value)).then(res=>res.json()).then(data=>{city.innerHTML='<option>Select City</option>';data.forEach(c=>{city.innerHTML+=`<option value="${c}">${c}</option>`});city.disabled=false;});
    });
});
function addPkg() {
    const div = document.createElement('div'); div.className = 'row g-2 mb-2';
    div.innerHTML = `<div class="col-4"><input type="text" name="pkg_name[]" class="form-control form-control-sm" placeholder="Name"></div><div class="col-3"><input type="number" name="pkg_price[]" class="form-control form-control-sm" placeholder="Price"></div><div class="col-4"><input type="text" name="pkg_features[]" class="form-control form-control-sm" placeholder="Features"></div><div class="col-1"><button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">x</button></div>`;
    document.getElementById('pkg-container').appendChild(div);
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

