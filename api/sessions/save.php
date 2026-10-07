<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../modules/SessionManager.php';
requireRole(['super_admin', 'admin']);

$event_id = (int)($_POST['event_id'] ?? 0);
if (!$event_id) redirect(BASE_URL . '/pages/events/index.php', 'Invalid event.', 'error');

$ev = $conn->prepare("SELECT status FROM events WHERE id = ?");
$ev->bind_param("i", $event_id);
$ev->execute();
$event = $ev->get_result()->fetch_assoc();
$back = BASE_URL . '/pages/sessions/index.php?event_id=' . $event_id;

if (!$event) redirect(BASE_URL . '/pages/events/index.php', 'Event not found.', 'error');
if ($event['status'] === 'archived') redirect($back, 'This event is archived and locked.', 'error');

$mgr = new SessionManager($conn);
$res = $mgr->save($_POST);

if ($res['ok']) {
    logActivity('Session Saved', "Event #{$event_id}: " . trim($_POST['session_name']));
    redirect($back, $res['msg'], 'success');
}
redirect($back, $res['msg'], 'error');
