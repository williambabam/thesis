<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    // 2. Connection Check
    if (!file_exists(__DIR__ . '/../config.php')) {
        throw new Exception("Config file not found. Check path.");
    }
    require_once __DIR__ . '/../config.php';
    require_login();

    // 3. Security Check
    if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['resort_admin', 'super_admin'])) {
        throw new Exception("Access Denied");
    }

    $action = $_GET['action'] ?? '';
    $room_id = intval($_REQUEST['room_id'] ?? 0);

    if ($room_id <= 0) {
        throw new Exception("Invalid Room ID: " . $room_id);
    }

    // --- ACTION: FETCH EVENTS ---
    if ($action === 'fetch') {
        $events = [];

        // A. Get Maintenance Blocks
        $stmt = $pdo->prepare("SELECT blocked_date FROM room_availability WHERE room_id = ?");
        $stmt->execute([$room_id]);
        $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($blocks as $b) {
            $events[] = [
                'title' => 'Blocked',
                'start' => $b['blocked_date'],
                'display' => 'background',
                'color' => '#64748b', // Grey
                'overlap' => false
            ];
        }

        // B. Get Real Bookings
        $stmt2 = $pdo->prepare("SELECT check_in, check_out FROM reservations WHERE room_id = ? AND status IN ('confirmed', 'checked_in')");
        $stmt2->execute([$room_id]);
        $bookings = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        foreach ($bookings as $b) {
            // FullCalendar end date is exclusive, add 1 day
            $end = new DateTime($b['check_out']);
            $end->modify('+1 day');
            
            $events[] = [
                'title' => 'Booked',
                'start' => $b['check_in'],
                'end'   => $end->format('Y-m-d'),
                'color' => '#7c3aed', // Purple
                'textColor' => 'white'
            ];
        }

        echo json_encode($events);
        exit;
    }

    // --- ACTION: TOGGLE DATE ---
    if ($action === 'toggle') {
        $date = $_POST['date'] ?? '';
        if (!$date) throw new Exception("Date is missing");

        // Check if exists
        $check = $pdo->prepare("SELECT id FROM room_availability WHERE room_id = ? AND blocked_date = ?");
        $check->execute([$room_id, $date]);
        $exists = $check->fetch();

        if ($exists) {
            // DELETE (Unblock)
            $del = $pdo->prepare("DELETE FROM room_availability WHERE id = ?");
            $del->execute([$exists['id']]);
            echo json_encode(['status' => 'available']);
        } else {
            // INSERT (Block)
            $ins = $pdo->prepare("INSERT INTO room_availability (room_id, blocked_date, status) VALUES (?, ?, 'maintenance')");
            $ins->execute([$room_id, $date]);
            echo json_encode(['status' => 'blocked']);
        }
        exit;
    }

    throw new Exception("Invalid Action: " . $action);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>