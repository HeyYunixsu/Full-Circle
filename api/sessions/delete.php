<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../modules/SessionManager.php';
requireRole(['super_admin', 'admin']);

$id = (int)($_GET['id'] ?? 0);
$mgr = new SessionManager($conn);
$s = $mgr->find($id);

if (!$s) redirect(BASE_URL . '/pages/events/index.php', 'Session not found.', 'error');
$back = BASE_URL . '/pages/sessions/index.php?event_id=' . $s['event_id'];
if ($s['event_status'] === 'archived') redirect($back, 'This event is archived and locked.', 'error');

if ($mgr->delete($id)) {
    logActivity('Session Deleted', "#{$id}: " . $s['session_name']);
    redirect($back, 'Session deleted.', 'success');
}
redirect($back, 'Delete failed.', 'error');
