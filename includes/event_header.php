<?php

require_once __DIR__ . '/icons.php';

$event_id   = $event_id ?? '';
$event_name = $event_name ?? 'Event';
$active_tab = $active_tab ?? 'overview';

$tabs = [
    'overview' => ['label' => 'Overview',        'url' => "/pages/events/overview.php?id=$event_id"],
    'badge'    => ['label' => 'Badge Designer',   'url' => "/pages/events/badge_designer.php?id=$event_id"],
    'guests'   => ['label' => 'Guests',           'url' => "/pages/attendees/index.php?id=$event_id"],
    'sessions' => ['label' => 'Sessions',         'url' => "/pages/sessions/index.php?id=$event_id"],
    'walkin'   => ['label' => 'Walk-in/Scan QR',  'url' => "/pages/checkin/index.php?id=$event_id"],
];
?>

<div class="event-workspace">
    <div class="event-topbar">
        <div class="event-topbar-left">
            <a href="<?= BASE_URL ?>/pages/events/index.php" class="event-back-btn" title="Back to events">
                <?= icon('arrow-left', ['class' => 'icon-svg icon-sm']) ?>
            </a>
            <span class="event-topbar-title"><?= htmlspecialchars($event_name) ?></span>
        </div>
        <div class="event-topbar-right">
            <div class="user-avatar"><?= strtoupper(substr($_SESSION['first_name'] ?? 'U', 0, 1)) ?></div>
        </div>
    </div>

    <div class="event-tabs">
        <?php foreach ($tabs as $key => $tab): ?>
            <a href="<?= BASE_URL . $tab['url'] ?>" class="event-tab <?= $active_tab === $key ? 'active' : '' ?>">
                <?= $tab['label'] ?>
            </a>
        <?php endforeach; ?>
        <div class="event-tabs-spacer"></div>
        <button class="event-share-btn" onclick="document.dispatchEvent(new CustomEvent('event:share'))">
            <?= icon('share-2', ['class' => 'icon-svg icon-sm']) ?>
            Share
        </button>
    </div>
</div>
