<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();

$user = $_SESSION['user'];
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: my_bookings.php');
    exit;
}

// Verify the booking belongs to the user
$stmt = $pdo->prepare('
    SELECT r.*, ro.room_name 
    FROM reservations r
    JOIN rooms ro ON r.room_id = ro.id
    WHERE r.id = ? AND r.guest_id = ?
');
$stmt->execute([$id, $user['id']]);
$reservation = $stmt->fetch();

if (!$reservation) {
    header('Location: my_bookings.php');
    exit;
}

// Check if cancellation is allowed
$checkIn = new DateTime($reservation['check_in']);
$today = new DateTime();
$canCancel = $checkIn > $today && in_array($reservation['status'], ['pending', 'confirmed']);

if (!$canCancel) {
    $_SESSION['error'] = 'This booking cannot be cancelled.';
    header('Location: my_bookings.php');
    exit;
}

// Cancel the booking
$stmt = $pdo->prepare('UPDATE reservations SET status = "cancelled" WHERE id = ?');
$stmt->execute([$id]);

// Send notification to guest
$stmt = $pdo->prepare('
    INSERT INTO notifications 
    (user_id, reservation_id, type, subject, message) 
    VALUES (?, ?, "cancellation", ?, ?)
');
$subject = 'Booking Cancelled';
$message = "Your booking for {$reservation['room_name']} has been cancelled successfully. Reservation ID: {$id}";
$stmt->execute([$user['id'], $id, $subject, $message]);

// Notify admins
$adminStmt = $pdo->query('SELECT id FROM users WHERE role IN ("resort_admin", "super_admin")');
$admins = $adminStmt->fetchAll();
foreach ($admins as $admin) {
    $stmt = $pdo->prepare('
        INSERT INTO notifications 
        (user_id, reservation_id, type, subject, message) 
        VALUES (?, ?, "admin_notification", ?, ?)
    ');
    $adminSubject = 'Booking Cancelled';
    $adminMessage = "{$user['full_name']} cancelled their booking for {$reservation['room_name']}. Reservation ID: {$id}";
    $stmt->execute([$admin['id'], $id, $adminSubject, $adminMessage]);
}

$_SESSION['success'] = 'Booking cancelled successfully.';
header('Location: my_bookings.php');
exit;