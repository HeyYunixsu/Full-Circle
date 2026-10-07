<?php
require_once __DIR__ . '/../../core/bootstrap.php';
requireRole(['admin', 'super_admin']);

[$events, $event] = selectedEvent();
$eid = (int)($event['id'] ?? 0);

if ($eid) {
    $k = $conn->query("SELECT COUNT(*) total,
                          SUM(status = 'checked_in') checked_in,
                          SUM(registration_type = 'pre-registered') pre_reg,
                          SUM(registration_type = 'walk-in') walk_ins,
                          SUM(badge_printed = 1) badges
                       FROM attendees WHERE event_id = $eid")->fetch_assoc();
    $k = array_map('intval', $k);
    $rate   = $k['total'] ? round($k['checked_in'] / $k['total'] * 100, 1) : 0;
    $fb     = $conn->query("SELECT ROUND(AVG(rating), 1) r, COUNT(*) n FROM feedback WHERE event_id = $eid AND session_id IS NULL")->fetch_assoc();
    $rating = $fb['r'];

    $by_company = $conn->query("SELECT company, COUNT(*) total, SUM(status = 'checked_in') checked_in
                                FROM attendees WHERE event_id = $eid GROUP BY company ORDER BY total DESC, company")->fetch_all(MYSQLI_ASSOC);
    $by_hour = $conn->query("SELECT HOUR(check_in_time) h, COUNT(*) c FROM attendees
                             WHERE event_id = $eid AND check_in_time IS NOT NULL GROUP BY h ORDER BY h")->fetch_all(MYSQLI_ASSOC);
    $sessions = $conn->query("SELECT s.session_name, s.session_date, s.start_time, s.end_time,
                                (SELECT COUNT(*) FROM session_attendance sa WHERE sa.session_id = s.id) attended,
                                (SELECT ROUND(AVG(rating), 1) FROM feedback f WHERE f.session_id = s.id) avg
                              FROM sessions s WHERE s.event_id = $eid ORDER BY s.session_date, s.start_time")->fetch_all(MYSQLI_ASSOC);
    $notif = [];
    foreach (["SELECT 'Email' channel, q.email_type type, q.status, COUNT(*) c FROM email_queue q JOIN attendees a ON a.id = q.attendee_id WHERE a.event_id = $eid GROUP BY q.email_type, q.status",
              "SELECT 'SMS' channel, q.sms_type type, q.status, COUNT(*) c FROM sms_queue q JOIN attendees a ON a.id = q.attendee_id WHERE a.event_id = $eid GROUP BY q.sms_type, q.status"] as $sql) {
        foreach ($conn->query($sql) as $r) {
            $key = $r['channel'] . ' · ' . str_replace('_', ' ', $r['type']);
            $notif[$key][$r['status']] = (int)$r['c'];
        }
    }
}

$overview = $conn->query("SELECT e.id, e.event_name, e.event_date, e.status, COUNT(a.id) total, SUM(a.status = 'checked_in') checked_in
                          FROM events e LEFT JOIN attendees a ON a.event_id = e.id
                          GROUP BY e.id ORDER BY e.event_date DESC, e.id DESC")->fetch_all(MYSQLI_ASSOC);

$page_title = 'Reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $page_title ?> &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<style>
    .print-head { display: none; }
    /* Six KPI tiles: 6 / 3 / 2 per row so no tile is ever left alone on a row */
    .kpi-six { grid-template-columns: repeat(6, minmax(0, 1fr)); }
    .kpi-six .kpi { display: flex; flex-direction: column; }
    .kpi-six .kpi small { margin: 0 0 6px; }
    .kpi-six .kpi b em { font-style: normal; font-size: 15px; font-weight: 600; color: var(--color-text-muted); letter-spacing: 0; margin-left: 2px; }
    .kpi-six .sub { font-size: 12px; color: var(--color-text-muted); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .kpi-six .stat-bar { display: block; margin-top: 8px; }
    @media (max-width: 1280px) { .kpi-six { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 768px) { .kpi-six { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media print { .print-head { display: block; margin-bottom: 16px; } }
</style>
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/../../includes/header.php'; ?>

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
            <a class="btn-sm light" href="<?= BASE_URL ?>/api/attendees/export.php?event_id=<?= $eid ?>"><?= icon('download', ['class' => 'icon-svg icon-sm']) ?> Export attendees CSV</a>
            <button class="btn-sm" onclick="window.print()"><?= icon('printer', ['class' => 'icon-svg icon-sm']) ?> Print report</button>
        </div>

        <div class="print-head">
            <h2><?= htmlspecialchars($event['event_name']) ?></h2>
            <p class="muted"><?= formatDate($event['event_date']) ?> &middot; <?= htmlspecialchars($event['location']) ?> &middot; Generated <?= date('M j, Y g:i A') ?></p>
        </div>

        <div class="kpi-grid kpi-six">
            <div class="kpi"><small>Registered</small><b><?= $k['total'] ?></b><span class="sub"><?= $k['pre_reg'] ?> pre-registered</span></div>
            <div class="kpi accent"><small>Checked in</small><b><?= $k['checked_in'] ?></b><span class="sub"><?= $rate ?>% attendance</span><span class="stat-bar"><i style="width: <?= min(100, $rate) ?>%"></i></span></div>
            <div class="kpi"><small>Not yet in</small><b><?= $k['total'] - $k['checked_in'] ?></b><span class="sub">Still expected</span></div>
            <div class="kpi"><small>Walk-ins</small><b><?= $k['walk_ins'] ?></b><span class="sub">Registered on site</span></div>
            <div class="kpi"><small>Badges printed</small><b><?= $k['badges'] ?></b><span class="sub">of <?= $k['checked_in'] ?> checked in</span></div>
            <div class="kpi"><small>Avg feedback</small><b><?= $rating ? $rating . '<em>/5</em>' : '—' ?></b><span class="sub"><?= (int)$fb['n'] ? (int)$fb['n'] . ' response' . ((int)$fb['n'] === 1 ? '' : 's') : 'No responses yet' ?></span></div>
        </div>

        <div class="panel-grid">
            <div class="panel">
                <h3>Attendance by company</h3>
                <?php if (!$by_company): ?>
                    <p class="muted">No attendees yet.</p>
                <?php else: ?>
                <table class="tbl">
                    <tr><th>Company</th><th class="num">Registered</th><th class="num">Checked in</th><th>Rate</th></tr>
                    <?php foreach ($by_company as $c): $pct = $c['total'] ? round($c['checked_in'] / $c['total'] * 100) : 0; ?>
                        <tr><td><?= htmlspecialchars($c['company'] ?: '—') ?></td><td class="num"><?= $c['total'] ?></td><td class="num"><?= (int)$c['checked_in'] ?></td><td><?= meter($pct, 100, $pct . '%') ?></td></tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>
            </div>
            <div class="panel">
                <h3>Check-ins by hour</h3>
                <?php if (!$by_hour): ?>
                    <p class="muted">No check-ins yet.</p>
                <?php else: $max = max(array_column($by_hour, 'c')); ?>
                <table class="tbl">
                    <?php foreach ($by_hour as $h): ?>
                        <tr><td style="width:110px;"><?= date('g A', mktime($h['h'], 0)) ?> – <?= date('g A', mktime($h['h'] + 1, 0)) ?></td><td><?= meter($h['c'], $max) ?></td></tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel-grid">
            <div class="panel">
                <h3>Sessions</h3>
                <?php if (!$sessions): ?>
                    <p class="muted">No sessions for this event.</p>
                <?php else: ?>
                <table class="tbl">
                    <tr><th>Session</th><th>When</th><th class="num">Attended</th><th class="num">Avg rating</th></tr>
                    <?php foreach ($sessions as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['session_name']) ?></td>
                            <td class="muted"><?= $s['session_date'] ? date('M j', strtotime($s['session_date'])) : '' ?> <?= $s['start_time'] ? date('g:i A', strtotime($s['start_time'])) : '' ?></td>
                            <td class="num"><?= $s['attended'] ?></td>
                            <td class="num"><?= $s['avg'] ?: '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>
            </div>
            <div class="panel">
                <h3>Notifications</h3>
                <?php if (!$notif): ?>
                    <p class="muted">No emails or SMS sent for this event yet.</p>
                <?php else: ?>
                <table class="tbl">
                    <tr><th>Message</th><th class="num">Sent</th><th class="num">Failed</th><th class="num">Pending</th></tr>
                    <?php foreach ($notif as $label => $n): ?>
                        <tr><td><?= ucwords($label) ?></td><td class="num"><?= $n['sent'] ?? 0 ?></td><td class="num"><?= $n['failed'] ?? 0 ?></td><td class="num"><?= $n['pending'] ?? 0 ?></td></tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($overview): ?>
        <div class="panel">
            <h3>All events</h3>
            <table class="tbl">
                <tr><th>Event</th><th>Date</th><th>Status</th><th class="num">Registered</th><th class="num">Checked in</th><th>Attendance</th></tr>
                <?php foreach ($overview as $o): $pct = $o['total'] ? round($o['checked_in'] / $o['total'] * 100) : 0; ?>
                    <tr>
                        <td><a href="?event_id=<?= $o['id'] ?>"><?= htmlspecialchars($o['event_name']) ?></a></td>
                        <td><?= formatDate($o['event_date'], 'M j, Y') ?></td>
                        <td><span class="<?= ['ongoing' => 'pill-ok', 'upcoming' => 'pill-brand'][$o['status']] ?? 'pill-muted' ?>"><?= ucfirst($o['status']) ?></span></td>
                        <td class="num"><?= $o['total'] ?></td>
                        <td class="num"><?= (int)$o['checked_in'] ?></td>
                        <td><?= meter($pct, 100, $pct . '%') ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
