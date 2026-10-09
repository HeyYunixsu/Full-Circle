<?php
// Instant check-in used by the Scan QR page: one scan = checked in.
// With session_id (Sessions page > Scan) it also records attendance for that session.
// (The Attendees page uses api/checkin/checkin.php, which adds a verify step.)

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../modules/SessionManager.php';
requireLogin();

header('Content-Type: application/json');

$event_id = (int)($_POST['event_id'] ?? 0);
$code = trim($_POST['code'] ?? '');

if (!$event_id || $code === '') {
    echo json_encode(['success' => false, 'message' => 'Missing event or QR code.']);
    exit;
}

$event = findEvent($event_id);
$closed = $event ? checkinClosedReason($event) : 'Event not found.';
if ($closed) {
    echo json_encode(['success' => false, 'closed' => true, 'message' => $closed]);
    exit;
}

$sessions = new SessionManager($conn);
$session_id = (int)($_POST['session_id'] ?? 0);
$session = $session_id ? $sessions->find($session_id) : null;
if ($session_id && (!$session || (int)$session['event_id'] !== $event_id)) {
    echo json_encode(['success' => false, 'message' => 'This session does not belong to this event. Go back to Sessions and open its Scan button again.']);
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

$already = $attendee['status'] === 'checked_in';
if (!$already) {
    $now = date('Y-m-d H:i:s');
    $update = $conn->prepare("UPDATE attendees SET status = 'checked_in', check_in_time = ? WHERE id = ? AND status <> 'checked_in'");
    $update->bind_param("si", $now, $attendee['id']);
    if (!$update->execute()) {
        echo json_encode(['success' => false, 'message' => 'Could not save the check-in. Try again.']);
        exit;
    }
    logActivity('Check-in', 'Checked in: ' . $attendee['full_name']);
    markOngoingIfEventDay($event);
    $attendee['status'] = 'checked_in';
    $attendee['check_in_time'] = $now;
}

if ($session) {
    // A person can be recorded once per session; a second scan is only a reminder that they are already in
    $new = $sessions->recordScan($session['id'], $attendee['id'], currentUserId());
    if ($new) logActivity('Session Check-in', $attendee['full_name'] . ' at ' . $session['session_name']);
    echo json_encode([
        'success' => true,
        'already_checked_in' => !$new,
        'message' => ($new ? 'Recorded for ' : 'Already recorded for ') . $session['session_name'],
        'attendee' => $attendee,
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'already_checked_in' => $already,
    'message' => $already ? 'Already checked in at ' . date('g:i A', strtotime($attendee['check_in_time'])) : 'Checked in',
    'attendee' => $attendee,
]);
