<?php
// Instant check-in used by the Scan QR page: one scan = checked in.
// (The Attendees page uses api/checkin/checkin.php, which adds a verify step.)

require_once __DIR__ . '/../../core/bootstrap.php';
requireLogin();

header('Content-Type: application/json');

$event_id = (int)($_POST['event_id'] ?? 0);
$code = trim($_POST['code'] ?? '');

if (!$event_id || $code === '') {
    echo json_encode(['success' => false, 'message' => 'Missing event or QR code.']);
    exit;
}

// QR value, or the 9-digit attendee code typed in by hand
$stmt = $conn->prepare("SELECT * FROM attendees WHERE event_id = ? AND (qr_code = ? OR attendee_code = ?) LIMIT 1");
$stmt->bind_param("iss", $event_id, $code, $code);
$stmt->execute();
$attendee = $stmt->get_result()->fetch_assoc();

if (!$attendee) {
    echo json_encode(['success' => false, 'message' => 'Not registered for this event. Check the code or register them as a walk-in.']);
    exit;
}

if ($attendee['status'] === 'checked_in') {
    echo json_encode([
        'success' => true,
        'already_checked_in' => true,
        'message' => 'Already checked in at ' . date('g:i A', strtotime($attendee['check_in_time'])),
        'attendee' => $attendee,
    ]);
    exit;
}

$now = date('Y-m-d H:i:s');
$update = $conn->prepare("UPDATE attendees SET status = 'checked_in', check_in_time = ? WHERE id = ? AND status <> 'checked_in'");
$update->bind_param("si", $now, $attendee['id']);

if (!$update->execute()) {
    echo json_encode(['success' => false, 'message' => 'Could not save the check-in. Try again.']);
    exit;
}

logActivity('Check-in', 'Checked in: ' . $attendee['full_name']);
$attendee['status'] = 'checked_in';
$attendee['check_in_time'] = $now;

echo json_encode([
    'success' => true,
    'already_checked_in' => false,
    'message' => 'Checked in',
    'attendee' => $attendee,
]);
