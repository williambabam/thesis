<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();

if ($_SESSION['user']['role'] !== 'super_admin') {
    header('Location: ../dashboard.php'); exit;
}

// Handle Actions (Approve/Reject)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $status = ($_GET['action'] === 'approve') ? 'approved' : 'rejected';
    $pdo->prepare("UPDATE users SET approval_status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?")->execute([$status, $_SESSION['user']['id'], $id]);
    $_SESSION['success'] = "Account $status.";
    header('Location: super_admin_approvals.php'); exit;
}

// Fetch Applicants + Documents
$stmt = $pdo->query("
    SELECT u.*, 
    GROUP_CONCAT(CONCAT(d.document_type, '::', d.file_path) SEPARATOR '||') as docs
    FROM users u 
    LEFT JOIN partner_documents d ON u.id = d.user_id
    WHERE u.role = 'resort_admin' AND u.approval_status = 'pending' 
    GROUP BY u.id 
    ORDER BY u.created_at DESC
");
$applicants = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <title>Partner Approvals - ResortEase</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #7c3aed; --bg: #f8fafc; }
    body { background-color: var(--bg); font-family: 'Inter', sans-serif; }
    .card-custom { background: white; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; }
    .btn-approve { background-color: #10b981; color: white; border: none; }
    .btn-reject { background-color: #ef4444; color: white; border: none; }
    .doc-thumb { width: 100%; height: 200px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; cursor: pointer; }
    /* Footer */
    .footer-dark { background-color: #191e1f; color: #efefef; padding: 40px 0; margin-top: 60px; }
    .footer-brand { font-weight: 800; font-size: 24px; color: white; text-decoration: none; }
  </style>
</head>
<body>
<?php include __DIR__ . '/../navbar.php'; ?>

<div class="container py-5">
    <h2 class="fw-bold text-dark mb-4">Partner Approvals</h2>
    <?php if (isset($_SESSION['success'])): ?><div class="alert alert-success mb-4"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div><?php endif; ?>

    <div class="card-custom">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light"><tr><th class="ps-4">Applicant</th><th>Email</th><th>Applied</th><th>Documents</th><th class="text-end pe-4">Action</th></tr></thead>
                <tbody>
                    <?php if (empty($applicants)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No pending applications.</td></tr>
                    <?php else: ?>
                        <?php foreach ($applicants as $app): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($app['full_name']); ?></div>
                                <small class="text-muted">@<?php echo htmlspecialchars($app['username']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($app['email']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#docsModal<?php echo $app['id']; ?>">
                                    <i class="fas fa-file-alt me-1"></i> View Files
                                </button>
                            </td>
                            <td class="text-end pe-4">
                                <a href="?action=reject&id=<?php echo $app['id']; ?>" class="btn btn-sm btn-reject rounded-pill px-3 me-1" onclick="return confirm('Reject?');">Reject</a>
                                <a href="?action=approve&id=<?php echo $app['id']; ?>" class="btn btn-sm btn-approve rounded-pill px-3" onclick="return confirm('Approve?');">Approve</a>
                            </td>
                        </tr>

                        <div class="modal fade" id="docsModal<?php echo $app['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title fw-bold">Documents: <?php echo htmlspecialchars($app['full_name']); ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body bg-light">
                                        <div class="row g-3">
                                            <?php 
                                            if ($app['docs']) {
                                                foreach (explode('||', $app['docs']) as $docStr) {
                                                    list($type, $path) = explode('::', $docStr);
                                                    echo '<div class="col-md-6">';
                                                    echo '<div class="card p-2 border-0 shadow-sm">';
                                                    echo '<h6 class="fw-bold small text-secondary mb-2">' . htmlspecialchars($type) . '</h6>';
                                                    echo '<a href="../' . htmlspecialchars($path) . '" target="_blank"><img src="../' . htmlspecialchars($path) . '" class="doc-thumb"></a>';
                                                    echo '</div></div>';
                                                }
                                            } else {
                                                echo '<p class="text-muted">No documents found.</p>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<footer class="footer-dark"><div class="container text-center"><span class="footer-brand">ResortEase</span><div class="mt-3 text-muted small">&copy; <?php echo date('Y'); ?> ResortEase.</div></div></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>