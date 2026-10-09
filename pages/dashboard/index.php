<?php

require_once __DIR__ . '/../../core/bootstrap.php';
requireLogin();

// Overview tiles and the hourly chart count every live event together (or the next event when none is live)
[$ov_events, $ov_kind] = overviewEvents();
// Switcher above the tiles when several events are live: ?ov=<event id> shows one event, no ?ov shows all together
$ov_pick = (int)($_GET['ov'] ?? 0);
$ov_shown = array_values(array_filter($ov_events, fn($e) => (int)$e['id'] === $ov_pick)) ?: $ov_events;
$ov_ids = array_map('intval', array_column($ov_shown, 'id'));
$stats = $ov_ids ? getEventStats($ov_ids) : null;
$active_event = $ov_shown[0] ?? null;
$ov_multi = count($ov_ids) > 1;
$is_admin = in_array($_SESSION['role'] ?? 'staff', ['admin', 'super_admin']);

$ongoing   = $conn->query("SELECT * FROM events WHERE status = 'ongoing' ORDER BY event_date ASC LIMIT 4")->fetch_all(MYSQLI_ASSOC);
$upcoming  = $conn->query("SELECT * FROM events WHERE status = 'upcoming' ORDER BY event_date ASC LIMIT 4")->fetch_all(MYSQLI_ASSOC);
$completed = $conn->query("SELECT * FROM events WHERE status = 'completed' ORDER BY event_date DESC LIMIT 4")->fetch_all(MYSQLI_ASSOC);

// Attendee counts + a few names for the avatar stacks (max 4 events each, so a query per event is fine)
foreach ([&$ongoing, &$upcoming] as &$list) {
    foreach ($list as &$e) {
        $eid = (int)$e['id'];
        $e['people'] = $conn->query("SELECT full_name FROM attendees WHERE event_id = $eid ORDER BY id LIMIT 4")->fetch_all(MYSQLI_ASSOC);
        $e['count']  = (int)$conn->query("SELECT COUNT(*) c FROM attendees WHERE event_id = $eid")->fetch_assoc()['c'];
        $e['in']     = (int)$conn->query("SELECT COUNT(*) c FROM attendees WHERE event_id = $eid AND status = 'checked_in'")->fetch_assoc()['c'];
    }
    unset($e);
}
unset($list);

$activity = $conn->query("SELECT l.action, l.details, l.created_at, u.first_name, u.last_name
                          FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id
                          WHERE l.action NOT IN ('User Login', 'User Logout')   -- sign-ins stay in the log, not on the dashboard
                          ORDER BY l.id DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// Check-ins by hour for the active event (sparkline in the right rail)
$by_hour = [];
if ($ov_ids) {
    $by_hour = $conn->query("SELECT HOUR(check_in_time) h, COUNT(*) c FROM attendees
                             WHERE event_id IN (" . implode(',', $ov_ids) . ") AND check_in_time IS NOT NULL GROUP BY h ORDER BY h")->fetch_all(MYSQLI_ASSOC);
}
$ov_name = $ov_multi ? count($ov_ids) . ' live events' : ($active_event['event_name'] ?? '');
$ov_checkin_url = BASE_URL . '/pages/checkin/' . ($ov_multi ? 'choose.php' : 'index.php?event_id=' . (int)($active_event['id'] ?? 0));

$initials = fn($n) => strtoupper(mb_substr(trim($n), 0, 1) . (strpos(trim($n), ' ') ? mb_substr(trim($n), strpos(trim($n), ' ') + 1, 1) : ''));
$ago = function ($ts) {
    $d = time() - strtotime($ts);
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . ' min ago';
    if ($d < 86400) return floor($d / 3600) . ' hr ago';
    return floor($d / 86400) . ' d ago';
};
$page_title = 'Dashboard';
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page_title ?> &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<style>
    .dash { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 20px; align-items: start; }
    .dash-main > * + * { margin-top: 20px; }
    .dash-rail > * + * { margin-top: 20px; }
    .dash .panel { margin-bottom: 0; }

    /* Stat tiles: coloured badge + label + highlighted count */
    .tiles { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
    .tile > div { min-width: 0; }
    .tile small { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tile { display: flex; align-items: center; gap: 12px; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 16px 18px; }
    .tile-badge { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; font-size: 12px; font-weight: 800; letter-spacing: .5px; flex-shrink: 0; }
    .tile-badge.wine { background: var(--color-primary-soft); color: var(--wine-mid); }
    .tile-badge.ok { background: var(--success-bg); color: var(--success-text); }
    .tile-badge.warn { background: var(--warning-bg); color: var(--warning-text); }
    .tile-badge.info { background: var(--info-bg); color: var(--info-text); }
    .tile b { display: block; font-size: 14px; white-space: nowrap; color: var(--color-text-strong); }
    .tile small { font-size: 12px; color: var(--color-text-muted); }
    .tile small em { font-style: normal; font-weight: 700; color: var(--magenta); font-variant-numeric: tabular-nums; }

    .section-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 14px; }
    .section-head h3 { font-size: 16px; font-weight: 700; color: var(--color-text-strong); letter-spacing: -.2px; }
    .section-head a { font-size: 13px; font-weight: 600; color: var(--magenta); }
    .section-head a:hover { text-decoration: underline; }

    /* Ongoing: cover cards */
    .covers { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; }
    .cover-card { display: block; border: 1px solid var(--color-border); border-radius: var(--radius-md); overflow: hidden; background: var(--color-surface); transition: border-color var(--dur-fast); }
    .cover-card:hover { border-color: var(--magenta); }
    .cover { position: relative; aspect-ratio: 16 / 9; background: linear-gradient(135deg, var(--wine-darkest) 0%, var(--wine-mid) 60%, var(--magenta) 140%); display: grid; place-items: center; color: rgba(255, 255, 255, .85); font-size: 28px; font-weight: 800; letter-spacing: 1px; overflow: hidden; }
    .cover img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .cover::after { content: ''; position: absolute; inset: 0; background-image: radial-gradient(rgba(255, 255, 255, .12) 1px, transparent 1px); background-size: 14px 14px; }
    .cover .pill-live { position: absolute; top: 8px; left: 8px; z-index: 1; background: var(--success); color: var(--white); font-size: 10px; font-weight: 800; letter-spacing: .6px; text-transform: uppercase; padding: 3px 8px; border-radius: var(--radius-full); display: inline-flex; align-items: center; gap: 5px; }
    .cover .pill-live::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--white); }
    .cover-body { padding: 12px 14px 14px; }
    .cover-body b { display: block; font-size: 14px; color: var(--color-text-strong); margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .meta { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--color-text-muted); }
    .meta + .meta { margin-top: 3px; }
    .meta .icon-svg { width: 13px; height: 13px; color: var(--color-text-faint); }
    .cover-foot { display: flex; align-items: center; justify-content: space-between; margin-top: 10px; }

    /* Upcoming: date pill cards */
    .up-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; }
    .up-card { display: block; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 14px; transition: border-color var(--dur-fast); }
    .up-card:hover { border-color: var(--magenta); }
    .date-pill { display: inline-block; background: var(--color-primary-soft); color: var(--wine-mid); font-size: 12px; font-weight: 700; padding: 5px 12px; border-radius: var(--radius-sm); margin-bottom: 10px; }
    .up-card b { display: block; font-size: 14px; color: var(--color-text-strong); margin-bottom: 4px; }

    /* Avatar stacks */
    .stack { display: flex; align-items: center; margin-top: 12px; }
    .stack span { width: 26px; height: 26px; border-radius: 50%; border: 2px solid var(--color-surface); margin-left: -7px; display: grid; place-items: center; font-size: 9px; font-weight: 800; color: var(--white); }
    .stack span:first-child { margin-left: 0; }
    .stack .c0 { background: var(--wine-mid); } .stack .c1 { background: var(--magenta); } .stack .c2 { background: var(--wine-light); } .stack .c3 { background: var(--pink-bright); }
    .stack .more { background: var(--color-bg); color: var(--color-text-muted); font-size: 10px; }
    .stack small { margin-left: 8px; font-size: 12px; color: var(--color-text-muted); }

    /* Completed: compact rows */
    .done-list .list-row { text-decoration: none; }
    .done-list .list-row b { font-size: 14px; }

    /* Rail */
    .activity .list-row { gap: 10px; padding: 9px 0; }
    .activity .avatar { width: 28px; height: 28px; font-size: 10px; }
    .act-body { flex: 1; min-width: 0; }
    .act-body p, .act-detail { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .act-body p { font-size: 13px; line-height: 1.35; color: var(--color-text-muted); }
    .act-body p em { font-style: normal; font-weight: 600; color: var(--color-text-strong); }
    .act-detail { display: block; font-size: 12px; color: var(--color-text-muted); margin-top: 1px; }
    .act-time { flex-shrink: 0; align-self: flex-start; font-size: 11px; color: var(--color-text-muted); white-space: nowrap; padding-top: 1px; }

    .spark { width: 100%; height: auto; display: block; overflow: visible; }
    .spark-axis { display: flex; justify-content: space-between; font-size: 11px; color: var(--color-text-muted); margin-top: 6px; font-variant-numeric: tabular-nums; }
    .spark-total { font-size: 26px; font-weight: 700; color: var(--color-text-strong); letter-spacing: -.8px; line-height: 1; margin-bottom: 10px; font-variant-numeric: tabular-nums; }
    .spark-total small { font-size: 12px; font-weight: 500; color: var(--color-text-muted); letter-spacing: 0; margin-left: 6px; }
    .spark-table { margin-top: 10px; font-size: 12px; }
    .spark-table summary { cursor: pointer; color: var(--color-text-muted); font-weight: 600; }

    .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; text-align: center; }
    .calendar-day-header { font-size: 11px; font-weight: 600; color: var(--color-text-muted); padding: 6px 0; text-transform: uppercase; }
    .calendar-day { width: 30px; height: 30px; margin: 1px auto; display: grid; place-items: center; font-size: 13px; color: var(--color-text-muted); border-radius: 50%; font-variant-numeric: tabular-nums; }
    /* Today: bold magenta number (no fill), so it matches the event-day circles; an event day gets the pink ring */
    .calendar-day.today, .calendar-day.today.has-event { color: var(--magenta); font-weight: 700; }
    .calendar-day.has-event { box-shadow: inset 0 0 0 2px var(--pink-bright); color: var(--color-text-strong); font-weight: 600; }

    .overview-label { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; font-size: 13px; color: var(--color-text-muted); }
    .overview-label b { color: var(--color-text-strong); font-weight: 600; }
    .live-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--success); box-shadow: 0 0 0 3px var(--success-bg); }
    .ov-switch { display: inline-flex; gap: 2px; padding: 3px; max-width: 100%; overflow-x: auto; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 999px; }
    .ov-switch a { padding: 4px 12px; border-radius: 999px; font-size: 13px; font-weight: 500; color: var(--color-text-muted); white-space: nowrap; max-width: 200px; overflow: hidden; text-overflow: ellipsis; transition: background var(--dur-fast), color var(--dur-fast); }
    .ov-switch a:hover { background: var(--color-bg); color: var(--color-text-strong); }
    .ov-switch a[aria-current] { background: var(--wine-mid); color: var(--white); }
    @media (max-width: 1100px) { .dash { grid-template-columns: 1fr; } }
    @media (max-width: 900px) { .tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/mobile.css?v=<?= ASSET_VER ?>" media="(max-width: 768px)">
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/../../includes/header.php'; ?>

        <div class="dash">
        <div class="dash-main">

            <?php if ($stats): ?>
            <div class="overview-label">
                <?php if ($ov_kind === 'live'): ?><span class="live-dot" aria-hidden="true"></span><b>Live now</b><?php else: ?><b>Next event</b><?php endif; ?>
                <?php if (count($ov_events) > 1): ?>
                    <nav class="ov-switch" aria-label="Show numbers for">
                        <a href="?"<?= $ov_multi ? ' aria-current="true"' : '' ?>>All <?= count($ov_events) ?></a>
                        <?php foreach ($ov_events as $e): $on = !$ov_multi && (int)$e['id'] === $ov_ids[0]; ?>
                            <a href="?ov=<?= (int)$e['id'] ?>" title="<?= htmlspecialchars($e['event_name']) ?>"<?= $on ? ' aria-current="true"' : '' ?>><?= htmlspecialchars($e['event_name']) ?></a>
                        <?php endforeach; ?>
                    </nav>
                <?php else: ?>
                    <span><?= htmlspecialchars($ov_events[0]['event_name']) ?></span>
                <?php endif; ?>
            </div>
            <div class="tiles">
                <div class="tile"><span class="tile-badge wine">TA</span><div><b>Attendees</b><small>Registered <em><?= $stats['total'] ?></em></small></div></div>
                <div class="tile"><span class="tile-badge ok">CI</span><div><b>Checked In</b><small>Arrived <em><?= $stats['checked_in'] ?></em>/<?= $stats['total'] ?></small></div></div>
                <div class="tile"><span class="tile-badge warn">PD</span><div><b>Pending</b><small>Not in yet <em><?= $stats['not_yet'] ?></em></small></div></div>
                <div class="tile"><span class="tile-badge info">WI</span><div><b>Walk-ins</b><small>On-site <em><?= $stats['walk_ins'] ?></em></small></div></div>
            </div>
            <?php endif; ?>

            <div class="panel">
                <div class="section-head">
                    <h3>Ongoing Events</h3>
                    <a href="<?= BASE_URL ?>/pages/events/index.php?status=ongoing">View all</a>
                </div>
                <?php if (!$ongoing): ?>
                    <div class="empty-state">
                        <?= icon('zap', ['class' => 'icon-svg']) ?>
                        <strong>Nothing is live right now</strong>
                        <p>Events switch to ongoing on their day; check-in opens from the event page.</p>
                    </div>
                <?php else: ?>
                <div class="covers">
                    <?php foreach ($ongoing as $e): ?>
                    <a class="cover-card" href="<?= BASE_URL ?>/pages/events/view.php?id=<?= $e['id'] ?>">
                        <div class="cover">
                            <?php if (!empty($e['event_image'])): ?><img src="<?= htmlspecialchars($e['event_image']) ?>" alt=""><?php endif; ?>
                            <?= htmlspecialchars($initials($e['event_name'])) ?>
                            <span class="pill-live">Live</span>
                        </div>
                        <div class="cover-body">
                            <b><?= htmlspecialchars($e['event_name']) ?></b>
                            <div class="meta"><?= icon('clock', ['class' => 'icon-svg']) ?> <?= formatDate($e['event_date'], 'M j, Y') ?>, <?= formatTime($e['event_time']) ?></div>
                            <div class="meta"><?= icon('map-pin', ['class' => 'icon-svg']) ?> <?= htmlspecialchars($e['location']) ?></div>
                            <div class="cover-foot">
                                <span class="pill-ok"><?= $e['in'] ?>/<?= $e['count'] ?> in</span>
                                <span class="meta"><?= icon('users', ['class' => 'icon-svg']) ?> <?= $e['count'] ?></span>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="panel">
                <div class="section-head">
                    <h3>Upcoming Events</h3>
                    <a href="<?= BASE_URL ?>/pages/events/index.php?status=upcoming">View all</a>
                </div>
                <?php if (!$upcoming): ?>
                    <div class="empty-state">
                        <?= icon('calendar', ['class' => 'icon-svg']) ?>
                        <strong>No upcoming events</strong>
                        <?php if ($is_admin): ?>
                            <p>Create an event to start registering and checking in attendees.</p>
                            <a href="<?= BASE_URL ?>/pages/events/create.php" class="btn-sm"><?= icon('plus', ['class' => 'icon-svg icon-sm']) ?> Create event</a>
                        <?php else: ?>
                            <p>Scheduled events will show up here once an admin adds them.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                <div class="up-grid">
                    <?php foreach ($upcoming as $e): ?>
                    <a class="up-card" href="<?= BASE_URL ?>/pages/events/view.php?id=<?= $e['id'] ?>">
                        <span class="date-pill"><?= formatDate($e['event_date'], 'd M Y') ?></span>
                        <b><?= htmlspecialchars($e['event_name']) ?></b>
                        <div class="meta"><?= icon('clock', ['class' => 'icon-svg']) ?> <?= formatTime($e['event_time']) ?></div>
                        <div class="meta"><?= icon('map-pin', ['class' => 'icon-svg']) ?> <?= htmlspecialchars($e['location']) ?></div>
                        <div class="stack">
                            <?php foreach ($e['people'] as $i => $p): ?><span class="c<?= $i ?>" title="<?= htmlspecialchars($p['full_name']) ?>"><?= htmlspecialchars($initials($p['full_name'])) ?></span><?php endforeach; ?>
                            <?php if ($e['count'] > 4): ?><span class="more">+<?= $e['count'] - 4 ?></span><?php endif; ?>
                            <small><?= $e['count'] ?> registered</small>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($completed): ?>
            <div class="panel done-list">
                <div class="section-head">
                    <h3>Recently Completed</h3>
                    <a href="<?= BASE_URL ?>/pages/events/index.php?status=completed">View all</a>
                </div>
                <?php foreach ($completed as $e): ?>
                <a class="list-row" href="<?= BASE_URL ?>/pages/events/view.php?id=<?= $e['id'] ?>">
                    <div><b><?= htmlspecialchars($e['event_name']) ?></b><small><?= formatDate($e['event_date']) ?> &middot; <?= htmlspecialchars($e['location']) ?></small></div>
                    <span class="pill-muted">Completed</span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <aside class="dash-rail">
            <div class="panel">
                <div class="section-head"><h3>Check-ins by Hour</h3><?php if ($active_event): ?><a href="<?= $ov_checkin_url ?>">Live view</a><?php endif; ?></div>
                <?php if (!$by_hour): ?>
                    <p class="muted"><?= $active_event ? 'No check-ins yet for ' . htmlspecialchars($ov_name) . '.' : 'No active event.' ?></p>
                <?php else:
                    $w = 300; $h = 90; $n = count($by_hour); $max = max(array_column($by_hour, 'c')) ?: 1;
                    $pts = [];
                    foreach ($by_hour as $i => $r) {
                        $x = $n > 1 ? 10 + $i * ($w - 20) / ($n - 1) : $w / 2;
                        $y = ($h - 12) - ((int)$r['c'] / $max) * ($h - 30);
                        $pts[] = [round($x, 1), round($y, 1), (int)$r['h'], (int)$r['c']];
                    }
                    $line = implode(' ', array_map(fn($p) => "$p[0],$p[1]", $pts));
                    $area = $pts[0][0] . ',' . ($h - 12) . ' ' . $line . ' ' . end($pts)[0] . ',' . ($h - 12);
                ?>
                <div class="spark-total"><?= array_sum(array_column($by_hour, 'c')) ?><small>checked in &middot; <?= htmlspecialchars(mb_strimwidth($ov_name, 0, 24, '…')) ?></small></div>
                <svg class="spark" viewBox="0 0 <?= $w ?> <?= $h ?>" role="img" aria-label="Check-ins per hour">
                    <line x1="0" y1="<?= $h - 12 ?>" x2="<?= $w ?>" y2="<?= $h - 12 ?>" stroke="var(--color-border)" stroke-width="1"/>
                    <polygon points="<?= $area ?>" fill="rgba(194, 59, 142, 0.12)"/>
                    <polyline points="<?= $line ?>" fill="none" stroke="var(--magenta)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
                    <?php foreach ($pts as $p): ?>
                    <circle cx="<?= $p[0] ?>" cy="<?= $p[1] ?>" r="4" fill="var(--magenta)" stroke="var(--color-surface)" stroke-width="2"><title><?= date('g A', mktime($p[2], 0)) ?>: <?= $p[3] ?> check-in<?= $p[3] === 1 ? '' : 's' ?></title></circle>
                    <?php endforeach; ?>
                </svg>
                <div class="spark-axis"><span><?= date('g A', mktime($pts[0][2], 0)) ?></span><?php if ($n > 1): ?><span><?= date('g A', mktime(end($pts)[2], 0)) ?></span><?php endif; ?></div>
                <details class="spark-table">
                    <summary>Show as table</summary>
                    <table class="tbl"><tr><th>Hour</th><th class="num">Check-ins</th></tr>
                    <?php foreach ($pts as $p): ?><tr><td><?= date('g A', mktime($p[2], 0)) ?></td><td class="num"><?= $p[3] ?></td></tr><?php endforeach; ?>
                    </table>
                </details>
                <?php endif; ?>
            </div>

            <div class="panel">
                <div class="section-head"><h3><?= date('F Y') ?></h3></div>
                <div class="calendar-grid">
                    <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?><div class="calendar-day-header"><?= $d ?></div><?php endforeach; ?>
                    <?php
                    $event_days = array_map(fn($r) => (int)$r['d'], $conn->query("SELECT DAY(event_date) d FROM events WHERE YEAR(event_date) = YEAR(CURDATE()) AND MONTH(event_date) = MONTH(CURDATE()) AND status <> 'archived'")->fetch_all(MYSQLI_ASSOC));
                    $first_day = date('w', strtotime(date('Y-m-01')));
                    $days_in_month = date('t');
                    $today = date('j');
                    for ($i = 0; $i < $first_day; $i++) echo '<div></div>';
                    for ($day = 1; $day <= $days_in_month; $day++) {
                        $is_today = $day == $today;
                        $has_event = in_array($day, $event_days);
                        $class = 'calendar-day' . ($is_today ? ' today' : '') . ($has_event ? ' has-event' : '');
                        $tip = implode(' · ', array_filter([$is_today ? 'Today' : '', $has_event ? 'Event day' : '']));
                        echo "<div class='$class'" . ($tip ? " title='$tip'" : '') . ">$day</div>";
                    }
                    ?>
                </div>
            </div>
            <div class="panel activity">
                <div class="section-head"><h3>Recent Activity</h3></div>
                <?php if (!$activity): ?>
                    <p class="muted">No event activity yet.</p>
                <?php else: foreach ($activity as $a): $who = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?: 'System'; ?>
                <div class="list-row">
                    <div class="avatar" title="<?= htmlspecialchars($who) ?>"><?= htmlspecialchars($initials($who)) ?></div>
                    <div class="act-body">
                        <p><em><?= htmlspecialchars($a['action']) ?></em> &middot; <?= htmlspecialchars($who) ?></p>
                        <?php if ($a['details']): ?><small class="act-detail" title="<?= htmlspecialchars($a['details']) ?>"><?= htmlspecialchars($a['details']) ?></small><?php endif; ?>
                    </div>
                    <small class="act-time"><?= $ago($a['created_at']) ?></small>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </aside>
        </div>
    </main>
</div>
</body>
</html>
