<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_SESSION['user'];
    $reservation_id = intval($_POST['reservation_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    if ($reservation_id > 0 && !empty($reason)) {
        // Verify Ownership
        $stmt = $pdo->prepare("SELECT total_price FROM reservations WHERE id = ? AND guest_id = ?");
        $stmt->execute([$reservation_id, $user['id']]);
        $booking = $stmt->fetch();

        if ($booking) {
            // Insert Request
            $stmt = $pdo->prepare("INSERT INTO refund_requests (reservation_id, user_id, reason, refund_amount, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->execute([$reservation_id, $user['id'], $reason, $booking['total_price']]);

            $pdo->prepare("INSERT INTO system_alerts (alert_type, title, message, related_id, priority) VALUES ('refund_request', 'Refund Request', ?, ?, 'high')")
                ->execute(["User {$user['full_name']} requested a refund.", $reservation_id]);

            header('Location: my_bookings.php?success=refund_sent');
            exit;
        }
    }
}
header('Location: my_bookings.php?error=invalid');
exit;