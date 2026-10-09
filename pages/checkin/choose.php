<?php
// "Which event?" for the sidebar's Open check-in button when more than one event is live.
require_once __DIR__ . '/../../core/bootstrap.php';
requireLogin();

$live_events = $conn->query("SELECT * FROM events WHERE status = 'ongoing' ORDER BY event_date, id")->fetch_all(MYSQLI_ASSOC);
if (count($live_events) < 2) {
    redirect(BASE_URL . '/pages/checkin/index.php' . ($live_events ? '?event_id=' . (int)$live_events[0]['id'] : ''));
}
$page_title = 'Open Check-in';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $page_title ?> &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<style>
    .choose-row { display: flex; align-items: center; gap: 14px; padding: 14px 0; border-bottom: 1px solid var(--color-border); }
    .choose-row:last-child { border-bottom: 0; padding-bottom: 0; }
    .choose-row > div { flex: 1; min-width: 0; }
    .choose-row b { display: block; color: var(--color-text-strong); }
    .choose-row small { color: var(--color-text-muted); font-size: 13px; }
    .choose-row .progress { margin-top: 8px; max-width: 260px; }
</style>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/mobile.css?v=<?= ASSET_VER ?>" media="(max-width: 768px)">
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/../../includes/header.php'; ?>
        <div class="narrow">
            <div class="panel">
                <div class="form-head">
                    <div class="kicker"><?= count($live_events) ?> events are live</div>
                    <h3>Which event are you checking in?</h3>
                    <p>Pick the event at your desk. You can switch any time from this button.</p>
                </div>
                <?php foreach ($live_events as $e): $st = getEventStats($e['id']); ?>
                    <div class="choose-row">
                        <div>
                            <b><?= htmlspecialchars($e['event_name']) ?></b>
                            <small><?= date('M j, Y', strtotime($e['event_date'])) ?><?= $e['location'] ? ' &middot; ' . htmlspecialchars($e['location']) : '' ?> &middot; <?= $st['checked_in'] ?> of <?= $st['total'] ?> checked in</small>
                            <div class="progress"><i style="width: <?= $st['percentage'] ?>%"></i></div>
                        </div>
                        <a class="btn-sm" href="<?= BASE_URL ?>/pages/checkin/index.php?event_id=<?= (int)$e['id'] ?>"><?= icon('camera', ['class' => 'icon-svg icon-sm']) ?> Open check-in</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>
