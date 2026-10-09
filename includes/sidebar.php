<?php

require_once __DIR__ . '/icons.php';
$current_page = basename($_SERVER['SCRIPT_NAME'], '.php');
$current_folder = basename(dirname($_SERVER['SCRIPT_NAME']));
$user_role = $_SESSION['role'] ?? 'staff';
$is_admin = in_array($user_role, ['admin', 'super_admin']);
$brand_initials = 'FC';

// Sessions, attendees and check-in live inside an event, so their pages keep "Events" highlighted
$active = in_array($current_folder, ['sessions', 'attendees', 'checkin']) ? 'events' : $current_folder;

$sections = [
    'Overview' => [
        ['dashboard', 'home',     'Dashboard', 'dashboard/index.php', true],
        ['events',    'calendar', 'Events',    'events/index.php',    $is_admin],
    ],
    'Event tools' => [
        ['badges',    'ticket',    'Badge Designer', 'badges/index.php',    true],
        ['companies', 'building',  'Companies',      'companies/index.php', $is_admin],
        ['feedback',  'star',      'Feedback',       'feedback/index.php',  $is_admin],
        ['reports',   'bar-chart', 'Reports',        'reports/index.php',   $is_admin],
    ],
    'Admin' => [
        ['accounts',  'users',     'Accounts',       'accounts/index.php',  canManageAccounts()],
    ],
];

// Check-in shortcut for the next or running event (the most used action on event day)
$cta_event = getActiveEvent();
$cta_live  = (int)$conn->query("SELECT COUNT(*) c FROM events WHERE status = 'ongoing'")->fetch_assoc()['c'];   // >1: let staff pick
?>
<aside class="sidebar">
    <div class="sidebar-logo">
        <span class="sidebar-logo-circle"><?= $brand_initials ?></span>
        <span class="sidebar-logo-name"><?= SITE_NAME ?></span>
    </div>
    <nav class="sidebar-menu" aria-label="Main">
        <?php foreach ($sections as $label => $links):
            $links = array_filter($links, fn($l) => $l[4]);
            if (!$links) continue; ?>
            <div class="sidebar-section"><?= $label ?></div>
            <?php foreach ($links as [$key, $ic, $text, $path]): ?>
            <a href="<?= BASE_URL ?>/pages/<?= $path ?>" class="sidebar-link <?= $active === $key ? 'active' : '' ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
                <?= icon($ic, ['class' => 'icon-svg']) ?>
                <span><?= $text ?></span>
            </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <?php if ($cta_event): $live = $cta_event['status'] === 'ongoing'; ?>
    <a class="sidebar-cta" href="<?= BASE_URL ?>/pages/checkin/<?= $cta_live > 1 ? 'choose.php' : 'index.php?event_id=' . (int)$cta_event['id'] ?>" title="<?= $cta_live > 1 ? 'Choose which live event to check in' : 'Open check-in for ' . htmlspecialchars($cta_event['event_name']) ?>">
        <?= icon('camera', ['class' => 'icon-svg']) ?>
        <div>
            <b>Open check-in</b>
            <small><span class="cta-state<?= $live ? ' live' : '' ?>"><?= $live ? 'Live' : 'Next' ?></span> <?= $cta_live > 1 ? $cta_live . ' events' : htmlspecialchars($cta_event['event_name']) ?></small>
        </div>
    </a>
    <?php endif; ?>
</aside>
