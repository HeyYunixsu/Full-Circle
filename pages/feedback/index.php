<?php
require_once __DIR__ . '/../../core/bootstrap.php';
requireRole(['admin', 'super_admin']);

[$events, $event] = selectedEvent();
$eid = (int)($event['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $eid && isset($_POST['send_links'])) {
    checkCsrf();
    require_once __DIR__ . '/../../includes/automation.php';
    $r = sendFeedbackLinks($eid);
    logActivity('Feedback Links Sent', "Event #{$eid}: {$r['email_sent']} sent, {$r['email_failed']} failed");
    $msg = $r['email_sent'] + $r['email_failed'] === 0
        ? 'Everyone checked in has already received the feedback link.'
        : "Feedback link sent to {$r['email_sent']} attendee(s)" . ($r['email_failed'] ? ", {$r['email_failed']} failed (will retry automatically)" : '') . '.';
    // The event page sends return=event so the result shows where the button was pressed
    $back = ($_POST['return'] ?? '') === 'event'
        ? BASE_URL . '/pages/events/view.php?id=' . $eid
        : BASE_URL . '/pages/feedback/index.php?event_id=' . $eid;
    redirect($back, $msg, $r['email_failed'] ? 'warning' : 'success');
}

if ($eid) {
    $sum = $conn->query("SELECT COUNT(*) n, ROUND(AVG(rating), 1) avg FROM feedback WHERE event_id = $eid AND session_id IS NULL")->fetch_assoc();
    $checked_in = (int)$conn->query("SELECT COUNT(*) c FROM attendees WHERE event_id = $eid AND status = 'checked_in'")->fetch_assoc()['c'];
    $links_sent = (int)$conn->query("SELECT COUNT(DISTINCT q.attendee_id) c FROM email_queue q JOIN attendees a ON a.id = q.attendee_id
                                     WHERE a.event_id = $eid AND q.email_type = 'feedback' AND q.status = 'sent'")->fetch_assoc()['c'];
    $dist = array_fill(1, 5, 0);
    foreach ($conn->query("SELECT rating, COUNT(*) c FROM feedback WHERE event_id = $eid AND session_id IS NULL GROUP BY rating") as $r) {
        $dist[(int)$r['rating']] = (int)$r['c'];
    }
    $sessions = $conn->query("SELECT s.session_name,
                                (SELECT COUNT(*) FROM session_attendance sa WHERE sa.session_id = s.id) attended,
                                COUNT(f.id) responses, ROUND(AVG(f.rating), 1) avg
                              FROM sessions s LEFT JOIN feedback f ON f.session_id = s.id
                              WHERE s.event_id = $eid GROUP BY s.id ORDER BY s.session_date, s.start_time")->fetch_all(MYSQLI_ASSOC);
    $comments = $conn->query("SELECT f.rating, f.comment, f.submitted_at, a.full_name, a.company, s.session_name
                              FROM feedback f JOIN attendees a ON a.id = f.attendee_id LEFT JOIN sessions s ON s.id = f.session_id
                              WHERE f.event_id = $eid AND f.comment IS NOT NULL AND f.comment <> ''
                              ORDER BY f.submitted_at DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
}

$flash = getFlashMessage();
$page_title = 'Feedback';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $page_title ?> &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<style>
    .stars-txt { color: var(--warning); letter-spacing: 1px; white-space: nowrap; }
    .comment { padding: 12px 0; border-bottom: 1px solid var(--color-border-faint); }
    .comment:last-child { border-bottom: none; }
    .comment p { margin: 6px 0 0; font-size: 14px; color: var(--gray-dark); white-space: pre-line; }
    .comment small { color: var(--gray-mid); }
</style>
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?>"><?= $flash['message'] ?></div>
        <?php endif; ?>

        <?php if (!$event): ?>
            <div class="empty-note">No events yet. Create an event first.</div>
        <?php else: ?>
        <div class="toolbar">
            <form method="GET">
                <select name="event_id" onchange="this.form.submit()" aria-label="Event">
                    <?php foreach ($events as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $e['id'] == $eid ? 'selected' : '' ?>><?= htmlspecialchars($e['event_name']) ?> (<?= formatDate($e['event_date'], 'M j, Y') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </form>
            <span class="spacer"></span>
            <form method="POST" data-confirm="Every checked-in attendee who has not received the feedback link yet gets it by email." data-confirm-title="Send feedback links now?" data-confirm-ok="Send links">
                <?= csrfField() ?>
                <input type="hidden" name="event_id" value="<?= $eid ?>">
                <button class="btn-sm" name="send_links" value="1"><?= icon('send', ['class' => 'icon-svg icon-sm']) ?> Send feedback links now</button>
            </form>
        </div>

        <div class="kpi-grid">
            <div class="kpi accent"><b><?= $sum['avg'] ?: '—' ?></b><small>Avg rating (of 5)</small></div>
            <div class="kpi"><b><?= (int)$sum['n'] ?></b><small>Responses</small></div>
            <div class="kpi"><b><?= $checked_in ? round($sum['n'] / $checked_in * 100) . '%' : '—' ?></b><small>Response rate</small></div>
            <div class="kpi"><b><?= $links_sent ?> / <?= $checked_in ?></b><small>Links sent / checked in</small></div>
        </div>

        <div class="panel-grid">
            <div class="panel">
                <h3>Overall rating</h3>
                <table class="tbl">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <tr><td class="stars-txt" style="width:90px;"><?= str_repeat('★', $i) ?></td><td><?= meter($dist[$i], max($dist)) ?></td></tr>
                    <?php endfor; ?>
                </table>
            </div>
            <div class="panel">
                <h3>Sessions</h3>
                <?php if (!$sessions): ?>
                    <p class="muted">No sessions for this event.</p>
                <?php else: ?>
                    <table class="tbl">
                        <tr><th>Session</th><th class="num">Attended</th><th class="num">Responses</th><th class="num">Avg</th></tr>
                        <?php foreach ($sessions as $s): ?>
                            <tr><td><?= htmlspecialchars($s['session_name']) ?></td><td class="num"><?= $s['attended'] ?></td><td class="num"><?= $s['responses'] ?></td><td class="num"><?= $s['avg'] ?: '—' ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel">
            <h3>Comments</h3>
            <?php if (empty($comments)): ?>
                <p class="muted">No comments yet. Links are emailed automatically after the event, or use "Send feedback links now".</p>
            <?php else: foreach ($comments as $c): ?>
                <div class="comment">
                    <span class="stars-txt"><?= str_repeat('★', $c['rating']) ?></span>
                    <strong><?= htmlspecialchars($c['full_name']) ?></strong>
                    <small>&middot; <?= htmlspecialchars($c['company']) ?> &middot; <?= $c['session_name'] ? htmlspecialchars($c['session_name']) : 'Overall' ?> &middot; <?= date('M j, g:i A', strtotime($c['submitted_at'])) ?></small>
                    <p><?= htmlspecialchars($c['comment']) ?></p>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
