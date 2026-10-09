<?php

require_once __DIR__ . '/../../core/bootstrap.php';
requireLogin();

if (!in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Access Denied'];
    header("Location: " . BASE_URL . "/pages/dashboard/");
    exit;
}

$flash = getFlashMessage();
$status_filter = $_GET['status'] ?? 'all';

$sql = "SELECT e.*,
        (SELECT COUNT(*) FROM attendees WHERE event_id = e.id) as attendee_count,
        (SELECT COUNT(*) FROM attendees WHERE event_id = e.id AND status = 'checked_in') as checked_in_count
        FROM events e";

if ($status_filter === 'all') {
    $sql .= " WHERE e.status != 'archived'";
} else {
    $sql .= " WHERE e.status = '" . $conn->real_escape_string($status_filter) . "'";
}
$sql .= " ORDER BY e.event_date DESC";
$events_result = $conn->query($sql);
$page_title = $status_filter === 'archived' ? 'Archived Events' : 'Events';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $page_title ?> &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<style>
    .events-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 16px; }
    .events-tabs { display: inline-flex; gap: 2px; padding: 3px; background: var(--white); border: 1px solid var(--gray-light); border-radius: var(--radius-sm); flex-wrap: wrap; }
    .events-tab { padding: 6px 16px; border-radius: 6px; color: var(--gray-dark); font-size: 13px; font-weight: 600; transition: background 0.15s, color 0.15s; }
    .events-tab:hover { color: var(--wine-mid); }
    .events-tab.active, .archived-tab.active { background: var(--wine-mid) !important; color: var(--white); }
    .events-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
    .events-grid > a { display: block; border-radius: var(--radius-lg); }
    .event-card { background: var(--white); border: 1px solid var(--gray-light); border-radius: var(--radius-lg); padding: 18px; height: 100%; transition: border-color 0.15s, box-shadow 0.15s; }
    .events-grid > a:hover .event-card { border-color: var(--magenta); box-shadow: var(--shadow-sm); }
    .event-card.is-archived { background: var(--off-white); }
    .event-card-head { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 12px; }
    .date-badge { flex-shrink: 0; width: 48px; padding: 5px 0; border-radius: var(--radius-sm); background: var(--pink-soft); color: var(--wine-mid); text-align: center; line-height: 1.1; }
    .date-badge b { display: block; font-size: 20px; font-weight: 700; font-variant-numeric: tabular-nums; }
    .date-badge span { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .event-card-title { flex: 1; min-width: 0; }
    .event-status { display: inline-block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 2px 8px; border-radius: var(--radius-full); white-space: nowrap; }
    .status-upcoming { background: var(--pink-soft); color: var(--wine-mid); }
    .status-ongoing { background: rgba(47, 184, 114, 0.14); color: #1b7a4b; }
    .status-completed, .status-archived { background: var(--shell-bg); color: var(--gray-dark); }
    .event-card-name { font-size: 15px; font-weight: 600; color: var(--wine-darkest); margin-bottom: 4px; }
    .event-meta-row { display: flex; align-items: center; gap: 8px; color: var(--gray-dark); font-size: 13px; margin-bottom: 4px; }
    .event-meta-row .icon-svg { width: 14px; height: 14px; color: var(--gray-mid); }
    .event-stats { display: flex; gap: 24px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--gray-light); }
    .event-stat-value { font-size: 20px; font-weight: 700; color: var(--wine-darkest); line-height: 1.2; font-variant-numeric: tabular-nums; }
    .event-stat-label { font-size: 11px; color: var(--gray-dark); text-transform: uppercase; letter-spacing: 0.5px; }
    .empty-state { text-align: center; padding: 60px 20px; color: var(--gray-dark); }
    .empty-state h3 { color: var(--wine-darkest); margin-bottom: 8px; display: inline-flex; align-items: center; gap: 8px; }
    .btn-add { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: var(--wine-mid) !important; color: var(--white) !important; border-radius: var(--radius-full); font-weight: 600; font-size: 14px; border: none; box-shadow: none !important; }
    .btn-add:hover { background: var(--magenta) !important; box-shadow: none !important; filter: none; }
</style>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/mobile.css?v=<?= ASSET_VER ?>" media="(max-width: 768px)">
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/../../includes/header.php'; ?>
        <div class="events-header">
            <div class="events-tabs">
                <a href="?status=all" class="events-tab <?= $status_filter === 'all' ? 'active' : '' ?>">All</a>
                <a href="?status=upcoming" class="events-tab <?= $status_filter === 'upcoming' ? 'active' : '' ?>">Upcoming</a>
                <a href="?status=ongoing" class="events-tab <?= $status_filter === 'ongoing' ? 'active' : '' ?>">Ongoing</a>
                <a href="?status=completed" class="events-tab <?= $status_filter === 'completed' ? 'active' : '' ?>">Completed</a>
                <?php
                    $archived_count_result = $conn->query("SELECT COUNT(*) AS c FROM events WHERE status = 'archived'");
                    $archived_count = $archived_count_result ? (int)$archived_count_result->fetch_assoc()['c'] : 0;
                ?>
                <a href="?status=archived" class="events-tab archived-tab <?= $status_filter === 'archived' ? 'active' : '' ?>">
                    Archived<?= $archived_count > 0 ? ' (' . $archived_count . ')' : '' ?>
                </a>
            </div>
        </div>
        <?php if ($events_result->num_rows === 0): ?>
            <div class="card">
                <div class="empty-state">
                    <?php if ($status_filter === 'archived'): ?>
                        <h3>
                            <?= icon('briefcase', ['class' => 'icon-svg icon-md']) ?>
                            No archived events
                        </h3>
                        <p>Completed events that you archive will appear here.</p>
                    <?php else: ?>
                        <h3>
                            <?= icon('calendar', ['class' => 'icon-svg icon-md']) ?>
                            No events yet
                        </h3>
                        <p>Use <strong>Add Event</strong> at the top to create your first event.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="events-grid">
                <?php while ($event = $events_result->fetch_assoc()): ?>
                    <a href="<?= BASE_URL ?>/pages/events/view.php?id=<?= $event['id'] ?>" style="text-decoration: none;">
                        <div class="event-card <?= $event['status'] === 'archived' ? 'is-archived' : '' ?>">
                            <div class="event-card-head">
                                <div class="date-badge"><b><?= date('j', strtotime($event['event_date'])) ?></b><span><?= date('M', strtotime($event['event_date'])) ?></span></div>
                                <div class="event-card-title">
                                    <div class="event-card-name"><?= htmlspecialchars($event['event_name']) ?></div>
                                    <span class="event-status status-<?= $event['status'] ?>"><?= $event['status'] ?></span>
                                </div>
                            </div>
                            <div class="event-card-body">
                                <div class="event-meta-row">
                                    <?= icon('calendar', ['class' => 'icon-svg icon-sm']) ?>
                                    <?= formatDate($event['event_date']) ?>
                                </div>
                                <div class="event-meta-row">
                                    <?= icon('map-pin', ['class' => 'icon-svg icon-sm']) ?>
                                    <?= htmlspecialchars($event['location']) ?>
                                </div>
                                <div class="event-stats">
                                    <div class="event-stat">
                                        <div class="event-stat-value"><?= $event['attendee_count'] ?></div>
                                        <div class="event-stat-label">Total</div>
                                    </div>
                                    <div class="event-stat">
                                        <div class="event-stat-value"><?= $event['checked_in_count'] ?></div>
                                        <div class="event-stat-label">Checked In</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>