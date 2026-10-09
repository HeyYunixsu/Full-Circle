<?php
// Undo a check-in (the wrong person was scanned). Admins: any time. Staff: only within 5 minutes of the check-in.
// Called by the Scan page (ajax=1, JSON reply) and the Attendees list (form post, back to the list).
require_once __DIR__ . '/../../core/bootstrap.php';
requireLogin();

$ajax = !empty($_POST['ajax']);
function done($ok, $msg, $event_id = 0) {
    global $ajax;
    if ($ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $ok, 'message' => $msg]);
        exit;
    }
    redirect(BASE_URL . '/pages/attendees/index.php' . ($event_id ? '?event_id=' . $event_id : ''), $msg, $ok ? 'success' : 'error');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') done(false, 'Invalid request.');

$s = $conn->prepare("SELECT a.id, a.event_id, a.full_name, a.status, e.status AS event_status,
                            a.check_in_time >= NOW() - INTERVAL 5 MINUTE AS recent
                     FROM attendees a JOIN events e ON e.id = a.event_id WHERE a.id = ?");
$id = (int)($_POST['attendee_id'] ?? 0);
$s->bind_param("i", $id);
$s->execute();
$a = $s->get_result()->fetch_assoc();

if (!$a) done(false, 'Attendee not found.');
$ev = (int)$a['event_id'];
if ($a['event_status'] === 'archived') done(false, 'This event is archived. Attendee records are locked.', $ev);
if ($a['status'] !== 'checked_in') done(false, $a['full_name'] . ' is not checked in.', $ev);
if (!in_array(currentRole(), ['admin', 'super_admin']) && !$a['recent']) {
    done(false, 'Only an admin can undo a check-in after 5 minutes.', $ev);
}

$reason = mb_substr(trim($_POST['reason'] ?? ''), 0, 200) ?: 'No reason given';
$u = $conn->prepare("UPDATE attendees SET status = 'not_yet', check_in_time = NULL WHERE id = ? AND status = 'checked_in'");
$u->bind_param("i", $id);
$u->execute();
logActivity('Check-in Undone', $a['full_name'] . ': ' . $reason);
done(true, 'Check-in undone for ' . $a['full_name'] . '. They show as Not yet again.', $ev);
