<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../includes/qrcode.php';
requireLogin();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
$event_id  = (int)($_POST['event_id'] ?? 0);
$code      = trim($_POST['code'] ?? '');
$confirmed = isset($_POST['confirmed']) && $_POST['confirmed'] === '1';
$new_name  = capitalizeWords($_POST['full_name'] ?? '');
$new_comp  = capitalizeWords($_POST['company'] ?? '');
$new_designation = capitalizeWords($_POST['designation'] ?? '');
$new_mobile     = trim($_POST['mobile_number'] ?? '');
if (!$event_id || !$code) {
    echo json_encode(['success' => false, 'message' => 'Missing event_id or code']);
    exit;
}
$stmt = $conn->prepare("
    SELECT a.*, e.event_name
    FROM attendees a
    JOIN events e ON a.event_id = e.id
    WHERE a.event_id = ? AND (a.qr_code = ? OR a.attendee_code = ?)
    LIMIT 1
");
$stmt->bind_param("iss", $event_id, $code, $code);
$stmt->execute();
$attendee = $stmt->get_result()->fetch_assoc();
if (!$attendee) {
    echo json_encode([
        'success' => false,
        'message' => 'QR code not recognized. Not registered for this event.'
    ]);
    exit;
}
$qr_url = getQRCodeImageUrl($attendee['qr_code'], $attendee['qr_image_path']);
if (!$confirmed) {
    echo json_encode([
        'success'            => true,
        'mode'               => 'preview',
        'already_checked_in' => ($attendee['status'] === 'checked_in'),
        'message'            => $attendee['status'] === 'checked_in'
            ? 'Already checked in at ' . date('h:i A', strtotime($attendee['check_in_time']))
            : 'Attendee found. Please verify identity before confirming.',
        'attendee'           => $attendee,
        'qr_url'             => $qr_url,
    ]);
    exit;
}
if ($attendee['status'] === 'checked_in') {
    echo json_encode([
        'success'            => false,
        'already_checked_in' => true,
        'message'            => 'Already checked in at ' . date('h:i A', strtotime($attendee['check_in_time'])),
        'attendee'           => $attendee,
        'qr_url'             => $qr_url,
    ]);
    exit;
}
$edits = [];
if ($new_name !== '' && $new_name !== $attendee['full_name']) {
    $edits[] = "name: '{$attendee['full_name']}' → '{$new_name}'";
    $attendee['full_name'] = $new_name;
}
if ($new_comp !== '' && $new_comp !== $attendee['company']) {
    $edits[] = "company: '{$attendee['company']}' → '{$new_comp}'";
    $attendee['company'] = $new_comp;
}
if ($new_designation !== '' && $new_designation !== ($attendee['designation'] ?? '')) {
    $edits[] = "designation: '" . ($attendee['designation'] ?? '') . "' → '{$new_designation}'";
    $attendee['designation'] = $new_designation;
}
if ($new_mobile !== '' && $new_mobile !== ($attendee['mobile_number'] ?? '')) {
    $edits[] = "mobile: '" . ($attendee['mobile_number'] ?? '') . "' → '{$new_mobile}'";
    $attendee['mobile_number'] = $new_mobile;
}
$now = date('Y-m-d H:i:s');
if (!empty($edits)) {
    $upd = $conn->prepare("
        UPDATE attendees
        SET status = 'checked_in',
            check_in_time = ?,
            full_name = ?,
            company = ?,
            designation = ?,
            mobile_number = ?,
            name_edited_by = ?,
            name_edited_at = ?,
            badge_printed = 1
        WHERE id = ? AND status != 'checked_in'
    ");
    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $upd->bind_param("sssssssii", $now, $attendee['full_name'], $attendee['company'], $attendee['designation'], $attendee['mobile_number'], $user_id, $now, $attendee['id']);
} else {
    $upd = $conn->prepare("
        UPDATE attendees
        SET status = 'checked_in',
            check_in_time = ?,
            badge_printed = 1
        WHERE id = ? AND status != 'checked_in'
    ");
    $upd->bind_param("si", $now, $attendee['id']);
}
if (!$upd->execute()) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: could not update check-in status.'
    ]);
    exit;
}
if ($conn->affected_rows === 0) {
    $recheck = $conn->prepare("SELECT * FROM attendees WHERE id = ?");
    $recheck->bind_param("i", $attendee['id']);
    $recheck->execute();
    $fresh = $recheck->get_result()->fetch_assoc();
    echo json_encode([
        'success'            => false,
        'already_checked_in' => true,
        'message'            => 'Already checked in at ' . date('h:i A', strtotime($fresh['check_in_time'])),
        'attendee'           => $fresh,
        'qr_url'             => $qr_url,
    ]);
    exit;
}
logActivity('Check-in', 'Checked in: ' . $attendee['full_name']);
if (!empty($edits)) {
    logActivity('Attendee Edited at Check-in',
        "Attendee #{$attendee['id']} ({$attendee['attendee_code']}): " . implode(' · ', $edits)
    );
}
$attendee['status']        = 'checked_in';
$attendee['check_in_time'] = $now;
$attendee['badge_printed'] = 1;
echo json_encode([
    'success'         => true,
    'mode'            => 'confirmed',
    'message'         => 'Checked in successfully!',
    'attendee'        => $attendee,
    'qr_url'          => $qr_url,
    'edits_applied'   => $edits,
]);