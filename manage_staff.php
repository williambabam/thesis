<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();

if (!in_array($_SESSION['user']['role'], ['resort_admin', 'super_admin'])) {
    header('Location: ../dashboard.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $pass = $_POST['password'];
    
    if($name && $username && $pass) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $email = $username . '@resort.staff';
        
        try {
            $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 'resort_admin', 1)");
            $stmt->execute([$name, $username, $email, $hash]);
            $_SESSION['success'] = "Staff member added successfully.";
        } catch (Exception $e) {
            $_SESSION['error'] = "Error: Username already exists.";
        }
        header('Location: manage_staff.php'); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = intval($_POST['staff_id']);
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $pass = $_POST['password'];
    
    $query = "UPDATE users SET full_name = ?, username = ?";
    $params = [$name, $username];
    
    // Only update password if typed
    if (!empty($pass)) {
        $query .= ", password_hash = ?";
        $params[] = password_hash($pass, PASSWORD_DEFAULT);
    }
    
    $query .= " WHERE id = ?";
    $params[] = $id;
    
    try {
        $pdo->prepare($query)->execute($params);
        $_SESSION['success'] = "Staff updated successfully.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating staff.";
    }
    header('Location: manage_staff.php'); exit;
}

// --- HANDLE DELETE ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // Prevent deleting yourself
    if ($id !== $_SESSION['user']['id']) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = "Staff removed.";
    }
    header('Location: manage_staff.php'); exit;
}

// Fetch Staff List
$staffMembers = $pdo->query("SELECT * FROM users WHERE role = 'resort_admin' ORDER BY id DESC")->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <title>Staff Team - ResortEase</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { 
      --primary: #7c3aed; 
      --bg: #f8fafc; 
    }
    
    /* Sticky Footer Layout */
    html, body { height: 100%; margin: 0; }
    body { 
        font-family: 'Inter', sans-serif; 
        background-color: var(--bg); 
        display: flex; 
        flex-direction: column; 
    }
    .main-content { flex: 1 0 auto; width: 100%; }
    
    /* Card Design */
    .content-card { 
        background: white; 
        border-radius: 16px; 
        border: 1px solid #e2e8f0; 
        box-shadow: 0 4px 12px rgba(0,0,0,0.02); 
        overflow: hidden; 
    }
    
    /* Table Styling */
    .table thead th { 
        background-color: #f8fafc; 
        color: #64748b; 
        font-weight: 600; 
        text-transform: uppercase; 
        font-size: 0.75rem; 
        letter-spacing: 0.5px; 
        border-bottom: 1px solid #e2e8f0; 
        padding: 1rem; 
    }
    .table tbody td { padding: 1rem; color: #334155; vertical-align: middle; }
    
    /* Buttons */
    .btn-primary { background-color: var(--primary); border: none; font-weight: 600; padding: 8px 20px; }
    .btn-primary:hover { background-color: #6d28d9; }
    
    /* Avatar */
    .avatar-circle { 
        width: 40px; height: 40px; 
        background: #f3e8ff; color: var(--primary); 
        border-radius: 50%; 
        display: flex; align-items: center; justify-content: center; 
        font-weight: bold; 
    }

    /* Footer */
    .footer-dark { 
        flex-shrink: 0;
        background-color: #191e1f; 
        color: #efefef; 
        padding: 40px 0; 
        font-size: 14px; 
        margin-top: 60px; 
    }
    .footer-brand { font-weight: 800; font-size: 24px; color: white; text-decoration: none; }
    .legal-link { color: #d1d5db; text-decoration: none; }
    .country-select { display: inline-flex; align-items: center; gap: 10px; border: 1px solid #4b5563; padding: 8px 12px; border-radius: 4px; color: #fff; font-size: 0.9rem; cursor: pointer; }
    .social-icons a { color: white; margin-right: 15px; font-size: 1.2rem; opacity: 0.8; }
  </style>
</head>
<body>
<?php include __DIR__ . '/../navbar.php'; ?>

<div class="container py-4 main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">Staff Team</h2>
            <p class="text-muted small mb-0">Manage administrative access</p>
        </div>
        <button class="btn btn-primary rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#addStaffModal">
            <i class="fas fa-user-plus me-2"></i> Add New Staff
        </button>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success py-2 small fw-bold rounded-3 mb-4"><i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger py-2 small fw-bold rounded-3 mb-4"><i class="fas fa-exclamation-triangle me-2"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="content-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Member</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffMembers as $staff): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle me-3">
                                    <?php echo strtoupper(substr($staff['full_name'], 0, 1)); ?>
                                </div>
                                <span class="fw-bold text-dark"><?php echo htmlspecialchars($staff['full_name']); ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="text-secondary">@<?php echo htmlspecialchars($staff['username']); ?></span>
                        </td>
                        <td>
                            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill">Administrator</span>
                        </td>
                        <td class="text-end pe-4">
                            <button class="btn btn-sm btn-light border me-2" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $staff['id']; ?>">
                                <i class="fas fa-edit text-secondary"></i>
                            </button>
                            
                            <?php if ($staff['id'] !== $_SESSION['user']['id']): ?>
                            <a href="?delete=<?php echo $staff['id']; ?>" class="btn btn-sm btn-outline-danger border-0" onclick="return confirm('Are you sure you want to remove this staff member?');">
                                <i class="fas fa-trash"></i>
                            </a>
                            <?php else: ?>
                            <span class="badge bg-light text-secondary border">You</span>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <div class="modal fade" id="editModal<?php echo $staff['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow-lg rounded-4">
                                <div class="modal-header border-0 pb-0">
                                    <h5 class="modal-title fw-bold">Edit Staff</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form method="post">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold text-secondary">Full Name</label>
                                            <input type="text" name="name" class="form-control rounded-3" value="<?php echo htmlspecialchars($staff['full_name']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold text-secondary">Username</label>
                                            <input type="text" name="username" class="form-control rounded-3" value="<?php echo htmlspecialchars($staff['username']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold text-secondary">New Password</label>
                                            <input type="password" name="password" class="form-control rounded-3" placeholder="Leave blank to keep current password">
                                        </div>
                                        
                                        <div class="d-grid mt-4">
                                            <button type="submit" class="btn btn-primary rounded-pill py-2 fw-bold">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addStaffModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">New Staff Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Full Name</label>
                        <input type="text" name="name" class="form-control rounded-3" required placeholder="e.g. Sarah Smith">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Username</label>
                        <input type="text" name="username" class="form-control rounded-3" required placeholder="e.g. sarah_admin">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Password</label>
                        <input type="password" name="password" class="form-control rounded-3" required placeholder="********">
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary rounded-pill py-2 fw-bold">Create Account</button>
                    </div>
                </form>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>