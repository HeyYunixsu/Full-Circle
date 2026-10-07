<?php

require_once __DIR__ . '/../../core/bootstrap.php';
requireLogin();

$event_id = (int)($_GET['event_id'] ?? $_GET['id'] ?? 0);

if (!$event_id) {
    redirect(BASE_URL . '/pages/events/index.php', 'Invalid event.', 'error');
}

$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) {
    redirect(BASE_URL . '/pages/events/index.php', 'Event not found.', 'error');
}

if ($event['status'] === 'archived') {
    redirect(BASE_URL . '/pages/events/view.php?id=' . $event_id,
            'This event is already archived. Use Re-download CSV instead.', 'info');
}

if ($event['status'] !== 'completed') {
    redirect(BASE_URL . '/pages/events/view.php?id=' . $event_id,
            'Event must be marked as Completed before archiving.', 'error');
}

$sql = "SELECT
            a.attendee_code,
            a.full_name,
            a.email,
            a.company,
            a.status,
            a.registration_type,
            a.check_in_time,
            a.badge_printed,
            a.email_sent,
            a.created_at AS registered_at
        FROM attendees a
        WHERE a.event_id = ?
        ORDER BY a.full_name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

$stats = getEventStats($event_id);

$safe_event_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $event['event_name']);
$filename = $safe_event_name . '_ARCHIVE_' . date('Ymd_His') . '.csv';

$archiver = (int)($_SESSION['user_id'] ?? 0);
$upd = $conn->prepare("UPDATE events SET status = 'archived', archived_at = NOW(), archived_by = ? WHERE id = ?");
$upd->bind_param("ii", $archiver, $event_id);
$upd->execute();

logActivity('Event Archived',
    "Archived event '{$event['event_name']}' (Total: {$stats['total']}, Checked-in: {$stats['checked_in']})");

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, ['EVENT ARCHIVE REPORT']);
fputcsv($out, ['Event Name', $event['event_name']]);
fputcsv($out, ['Event Date', formatDate($event['event_date'])]);
fputcsv($out, ['Event Time', formatTime($event['event_time'])]);
fputcsv($out, ['Location', $event['location']]);
fputcsv($out, ['Event Manager', $event['event_manager'] ?: '-']);
fputcsv($out, ['Description', $event['description'] ?: '-']);
fputcsv($out, ['Archived On', date('F j, Y g:i A')]);
fputcsv($out, ['Archived By', ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')]);
fputcsv($out, []);

fputcsv($out, ['ATTENDANCE SUMMARY']);
fputcsv($out, ['Total Registered', $stats['total']]);
fputcsv($out, ['Checked In', $stats['checked_in']]);
fputcsv($out, ['Not Checked In', $stats['not_yet']]);
fputcsv($out, ['Walk-ins', $stats['walk_ins']]);
fputcsv($out, ['Attendance Rate', $stats['percentage'] . '%']);
fputcsv($out, []);

fputcsv($out, ['ATTENDEE LIST']);
fputcsv($out, [
    'Code',
    'Full Name',
    'Email',
    'Company',
    'Status',
    'Registration Type',
    'Check-in Time',
    'Badge Printed',
    'Email Sent',
    'Registered At'
]);

while ($a = $result->fetch_assoc()) {
    fputcsv($out, [
        $a['attendee_code'],
        $a['full_name'],
        $a['email'],
        $a['company'],
        ucfirst(str_replace('_', ' ', $a['status'])),
        ucfirst($a['registration_type']),
        $a['check_in_time'] ? date('Y-m-d H:i:s', strtotime($a['check_in_time'])) : '',
        $a['badge_printed'] ? 'Yes' : 'No',
        $a['email_sent'] ? 'Yes' : 'No',
        $a['registered_at'] ? date('Y-m-d H:i:s', strtotime($a['registered_at'])) : ''
    ]);
}

fclose($out);
exit;
