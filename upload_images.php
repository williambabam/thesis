<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();

$role = $_SESSION['user']['role'];
if ($role !== 'resort_admin' && $role !== 'super_admin') {
    http_response_code(403);
    exit('Access denied');
}

$room_id = intval($_GET['id'] ?? 0);
if ($room_id <= 0) {
    header('Location: index.php');
    exit;
}

// Get room details
$stmt = $pdo->prepare('SELECT * FROM rooms WHERE id = ?');
$stmt->execute([$room_id]);
$room = $stmt->fetch();

if (!$room) {
    header('Location: index.php');
    exit;
}

$success = '';
$errors = [];

// Handle image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['room_image'])) {
    verify_csrf();
    
    $file = $_FILES['room_image'];
    $is_primary = isset($_POST['is_primary']) ? 1 : 0;
    
    // Validate file
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed.';
    } elseif (!in_array($file['type'], $allowed_types)) {
        $errors[] = 'Only JPG, PNG, and WEBP images are allowed.';
    } elseif ($file['size'] > $max_size) {
        $errors[] = 'File size must be less than 5MB.';
    } else {
        // Create uploads directory if it doesn't exist
        $upload_dir = __DIR__ . '/../uploads/rooms/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'room_' . $room_id . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // If setting as primary, remove primary flag from other images
            if ($is_primary) {
                $pdo->prepare('UPDATE room_images SET is_primary = 0 WHERE room_id = ?')->execute([$room_id]);
            }
            
            // Save to database
            $stmt = $pdo->prepare('INSERT INTO room_images (room_id, image_path, is_primary) VALUES (?, ?, ?)');
            $stmt->execute([$room_id, 'uploads/rooms/' . $filename, $is_primary]);
            
            $success = 'Image uploaded successfully!';
        } else {
            $errors[] = 'Failed to save uploaded file.';
        }
    }
}

// Handle image deletion
if (isset($_GET['delete_image'])) {
    $image_id = intval($_GET['delete_image']);
    
    // Get image path
    $stmt = $pdo->prepare('SELECT image_path FROM room_images WHERE id = ? AND room_id = ?');
    $stmt->execute([$image_id, $room_id]);
    $image = $stmt->fetch();
    
    if ($image) {
        // Delete file
        $file_path = __DIR__ . '/../' . $image['image_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Delete from database
        $stmt = $pdo->prepare('DELETE FROM room_images WHERE id = ?');
        $stmt->execute([$image_id]);
        
        $success = 'Image deleted successfully!';
    }
}

// Handle set primary
if (isset($_GET['set_primary'])) {
    $image_id = intval($_GET['set_primary']);
    
    // Remove primary from all
    $pdo->prepare('UPDATE room_images SET is_primary = 0 WHERE room_id = ?')->execute([$room_id]);
    
    // Set new primary
    $stmt = $pdo->prepare('UPDATE room_images SET is_primary = 1 WHERE id = ? AND room_id = ?');
    $stmt->execute([$image_id, $room_id]);
    
    $success = 'Primary image updated!';
}

// Get all images for this room
$stmt = $pdo->prepare('SELECT * FROM room_images WHERE room_id = ? ORDER BY is_primary DESC, uploaded_at DESC');
$stmt->execute([$room_id]);
$images = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Manage Room Images - <?php echo htmlspecialchars($room['room_name']); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .image-card {
      position: relative;
      overflow: hidden;
      border-radius: 10px;
      transition: transform 0.3s;
    }
    .image-card:hover {
      transform: scale(1.05);
    }
    .image-card img {
      width: 100%;
      height: 200px;
      object-fit: cover;
    }
    .image-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.7);
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: opacity 0.3s;
    }
    .image-card:hover .image-overlay {
      opacity: 1;
    }
    .primary-badge {
      position: absolute;
      top: 10px;
      right: 10px;
      z-index: 10;
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1><i class="fas fa-images"></i> Manage Room Images</h1>
      <p class="text-muted"><?php echo htmlspecialchars($room['room_name']); ?></p>
    </div>
    <a href="index.php" class="btn btn-secondary">
      <i class="fas fa-arrow-left"></i> Back to Rooms
    </a>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <i class="fas fa-exclamation-triangle"></i>
      <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="row">
    <!-- Upload Form -->
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0"><i class="fas fa-upload"></i> Upload New Image</h5>
        </div>
        <div class="card-body">
          <form method="post" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            
            <div class="mb-3">
              <label class="form-label">Select Image</label>
              <input type="file" name="room_image" class="form-control" accept="image/jpeg,image/jpg,image/png,image/webp" required>
              <small class="text-muted">Max 5MB. Formats: JPG, PNG, WEBP</small>
            </div>

            <div class="mb-3 form-check">
              <input type="checkbox" name="is_primary" class="form-check-input" id="isPrimary">
              <label class="form-check-label" for="isPrimary">
                Set as primary image
              </label>
            </div>

            <button type="submit" class="btn btn-primary w-100">
              <i class="fas fa-upload"></i> Upload Image
            </button>
          </form>

          <hr>

          <div class="alert alert-info">
            <small>
              <strong><i class="fas fa-info-circle"></i> Tips:</strong>
              <ul class="mb-0 mt-2">
                <li>Use high-quality images</li>
                <li>Recommended: 1200x800px</li>
                <li>Primary image shows first</li>
                <li>Upload multiple angles</li>
              </ul>
            </small>
          </div>
        </div>
      </div>
    </div>

    <!-- Current Images -->
    <div class="col-md-8">
      <div class="card shadow-sm">
        <div class="card-header bg-success text-white">
          <h5 class="mb-0"><i class="fas fa-images"></i> Current Images (<?php echo count($images); ?>)</h5>
        </div>
        <div class="card-body">
          <?php if (empty($images)): ?>
            <div class="text-center py-5">
              <i class="fas fa-images fa-4x text-muted mb-3"></i>
              <p class="text-muted">No images uploaded yet. Upload your first image!</p>
            </div>
          <?php else: ?>
            <div class="row g-3">
              <?php foreach ($images as $image): ?>
                <div class="col-md-6">
                  <div class="image-card">
                    <?php if ($image['is_primary']): ?>
                      <span class="badge bg-warning primary-badge">
                        <i class="fas fa-star"></i> Primary
                      </span>
                    <?php endif; ?>
                    
                    <img src="../<?php echo htmlspecialchars($image['image_path']); ?>" 
                         alt="Room Image">
                    
                    <div class="image-overlay">
                      <div class="btn-group">
                        <?php if (!$image['is_primary']): ?>
                          <a href="?id=<?php echo $room_id; ?>&set_primary=<?php echo $image['id']; ?>" 
                             class="btn btn-sm btn-warning"
                             title="Set as primary">
                            <i class="fas fa-star"></i>
                          </a>
                        <?php endif; ?>
                        
                        <a href="../<?php echo htmlspecialchars($image['image_path']); ?>" 
                           target="_blank" 
                           class="btn btn-sm btn-info"
                           title="View full size">
                          <i class="fas fa-expand"></i>
                        </a>
                        
                        <a href="?id=<?php echo $room_id; ?>&delete_image=<?php echo $image['id']; ?>" 
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Delete this image?');"
                           title="Delete">
                          <i class="fas fa-trash"></i>
                        </a>
                      </div>
                    </div>
                  </div>
                  <small class="text-muted">
                    Uploaded: <?php echo (new DateTime($image['uploaded_at']))->format('M d, Y'); ?>
                  </small>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>