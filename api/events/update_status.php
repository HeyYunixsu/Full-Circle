<?php

require_once __DIR__ . '/../../core/bootstrap.php';
requireLogin();

$event_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$new_status = $_GET['status'] ?? $_POST['status'] ?? '';

$allowed_statuses = ['upcoming', 'ongoing', 'completed', 'cancelled'];

if (!$event_id || !in_array($new_status, $allowed_statuses, true)) {
    redirect(BASE_URL . '/pages/events/index.php', 'Invalid request.', 'error');
}

$stmt = $conn->prepare("SELECT id, event_name, status FROM events WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) {
    redirect(BASE_URL . '/pages/events/index.php', 'Event not found.', 'error');
}

if ($event['status'] === 'archived') {
    redirect(BASE_URL . '/pages/events/view.php?id=' . $event_id,
            'This event is archived. Archived events are permanent and cannot be re-opened.', 'error');
}

$upd = $conn->prepare("UPDATE events SET status = ? WHERE id = ?");
$upd->bind_param("si", $new_status, $event_id);

if ($upd->execute()) {
    logActivity('Event Status Updated', "Event '{$event['event_name']}' changed from '{$event['status']}' to '{$new_status}'");
    redirect(BASE_URL . '/pages/events/view.php?id=' . $event_id,
            'Event marked as ' . ucfirst($new_status) . '.', 'success');
} else {
    redirect(BASE_URL . '/pages/events/view.php?id=' . $event_id,
            'Failed to update status.', 'error');
}
