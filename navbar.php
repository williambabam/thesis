<?php
if (!isset($_SESSION)) {
  session_start();
}
$user = $_SESSION['user'] ?? null;
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container">
    <a class="navbar-brand" href="/resort_app/dashboard.php">Resort System</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav me-auto">
        <?php if ($user && ($user['role'] === 'resort_admin' || $user['role'] === 'super_admin')): ?>
          <li class="nav-item"><a class="nav-link" href="/resort_app/rooms/index.php">Rooms</a></li>
        <?php endif; ?>
      </ul>
    </div>
    <?php if ($user): ?>
      <div class="d-flex align-items-center">
        <span class="navbar-text text-light me-3"><?php echo htmlspecialchars($user['full_name']); ?></span>
        <a class="btn btn-outline-light" href="/resort_app/logout.php">Logout</a>
      </div>
    <?php endif; ?>
  </div>
</nav>
