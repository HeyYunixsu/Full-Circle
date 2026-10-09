<?php
// Event page > Badge design > Save: which design this event's badges print with (admins only)
require_once __DIR__ . '/../../core/bootstrap.php';
requireRole(['admin', 'super_admin']);

$event = findEvent((int)($_POST['event_id'] ?? 0));
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$event) {
    redirect(BASE_URL . '/pages/events/index.php', 'Event not found.', 'error');
}
$back = BASE_URL . '/pages/events/view.php?id=' . (int)$event['id'];

$tpl_id = (int)($_POST['badge_template_id'] ?? 0);
$s = $conn->prepare("SELECT template_name FROM badge_templates WHERE id = ? AND layout_json IS NOT NULL");
$s->bind_param("i", $tpl_id);
$s->execute();
$tpl = $s->get_result()->fetch_assoc();
if (!$tpl) {
    redirect($back, 'That badge design no longer exists. Pick another one.', 'error');
}

$u = $conn->prepare("UPDATE events SET badge_template_id = ? WHERE id = ?");
$u->bind_param("ii", $tpl_id, $event['id']);
$u->execute();
logActivity('Badge Design', $event['event_name'] . ': ' . $tpl['template_name']);
redirect($back, 'Badges for this event now print with "' . $tpl['template_name'] . '".');
