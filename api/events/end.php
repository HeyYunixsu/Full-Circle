<?php

require_once __DIR__ . '/../../core/bootstrap.php';
requireLogin();

if (!in_array($_SESSION['role'] ?? '', ['admin', 'event_manager', 'super_admin'])) {
    redirect(BASE_URL . '/pages/events/index.php', 'You do not have permission to end events.', 'error');
}

$event_id = (int)($_REQUEST['event_id'] ?? 0);
$archive  = isset($_REQUEST['archive']) && $_REQUEST['archive'] == '1';

if (!$event_id) {
    redirect(BASE_URL . '/pages/events/index.php', 'Invalid event.', 'error');
}

$stmt = $conn->prepare("SELECT id, event_name, status FROM events WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) {
    redirect(BASE_URL . '/pages/events/index.php', 'Event not found.', 'error');
}

if ($event['status'] === 'archived') {
    redirect(BASE_URL . '/pages/events/index.php', 'Event is already archived.', 'info');
}

$new_status = $archive ? 'archived' : 'completed';

if ($archive) {
    $archiver = (int)($_SESSION['user_id'] ?? 0);
    $upd = $conn->prepare("UPDATE events SET status = 'archived', archived_at = NOW(), archived_by = ?, updated_at = NOW() WHERE id = ?");
    $upd->bind_param("ii", $archiver, $event_id);
} else {
    $upd = $conn->prepare("UPDATE events SET status = ?, updated_at = NOW() WHERE id = ?");
    $upd->bind_param("si", $new_status, $event_id);
}

if (!$upd->execute()) {
    redirect(BASE_URL . '/pages/events/index.php', 'Failed to update event: ' . $conn->error, 'error');
}

$cancel = $conn->prepare("
    UPDATE email_queue eq
    JOIN attendees a ON a.id = eq.attendee_id
    SET eq.status = 'failed',
        eq.error_message = 'Event ended — sending cancelled'
    WHERE a.event_id = ? AND eq.status = 'pending'
");
$cancel->bind_param("i", $event_id);
$cancel->execute();
$cancelled = $cancel->affected_rows;

$action_label = $archive ? 'Event Archived' : 'Event Ended';
logActivity($action_label, "Event #{$event_id} ({$event['event_name']}) → {$new_status}");

$msg = "Event '" . htmlspecialchars($event['event_name']) . "' has been " .
       ($archive ? 'archived' : 'marked as completed') . ".";

if ($cancelled > 0) {
    $msg .= " ({$cancelled} pending email" . ($cancelled === 1 ? '' : 's') . " cancelled.)";
}

redirect(BASE_URL . '/pages/events/index.php', $msg, 'success');
