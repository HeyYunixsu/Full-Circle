<?php

require_once __DIR__ . '/../../core/bootstrap.php';
requireLogin();
$event_id = (int)($_GET['id'] ?? 0);
if (!$event_id) {
    redirect(BASE_URL . '/pages/events/index.php', 'Invalid event.', 'error');
}
$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
if (!$event) {
    redirect(BASE_URL . '/pages/events/index.php', 'Event not found.', 'error');
}

$company_caps = companyCapacity($event_id);

$sessions = $conn->query("SELECT * FROM sessions WHERE event_id = $event_id ORDER BY session_date, start_time");

$stats = getEventStats($event_id);
$ev_tpl = badgeTemplateFor($event['badge_template_id'] ?? 0);
$is_admin = in_array($_SESSION['role'] ?? '', ['admin', 'super_admin']);

// Completed events: how many checked-in attendees still need the feedback link (same rule the sender uses)
$fb_pending = null;
if ($event['status'] === 'completed' && in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    require_once __DIR__ . '/../../includes/automation.php';
    $fb_pending = (int)fetchRows("SELECT COUNT(*) c FROM attendees a WHERE a.event_id = ? AND a.status = 'checked_in'
                                  AND a.email <> '' AND a.qr_code IS NOT NULL AND " . notYetSent('email_queue', 'email_type', 'feedback'),
                                 "i", $event_id)[0]['c'];
}

$company_stats = [];
$cs_result = $conn->query("SELECT company, COUNT(*) as total, SUM(CASE WHEN status='checked_in' THEN 1 ELSE 0 END) as checked FROM attendees WHERE event_id=$event_id GROUP BY company ORDER BY company");
while ($r = $cs_result->fetch_assoc()) $company_stats[] = $r;

$recent = $conn->query("SELECT * FROM attendees WHERE event_id=$event_id AND status='checked_in' ORDER BY check_in_time DESC LIMIT 10");
$flash = getFlashMessage();
$page_title = $event['event_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<style>
    * { box-sizing: border-box; }
    .event-hero { 
        background: radial-gradient(rgba(255,255,255,.07) 1px, transparent 1.5px) 0 0 / 22px 22px, linear-gradient(135deg, #2e0f26, #7a2f5f); 
        border-radius: 16px; 
        padding: 32px; 
        color: #ffffff; 
        margin-bottom: 24px; 
        position: relative;
        min-height: 180px;
    }
    .event-hero h2 { 
        font-size: 28px; 
        margin-bottom: 12px; 
        color: #ffffff;
        font-weight: 700;
    }
    .event-hero-meta { 
        display: flex; 
        gap: 24px; 
        flex-wrap: wrap; 
        font-size: 15px; 
        opacity: 0.95; 
        margin-top: 12px;
    }
    .event-hero-meta span { 
        display: inline-flex; 
        align-items: center; 
        gap: 6px; 
        white-space: nowrap;
    }
    .event-hero-meta .icon-svg { width: 16px; height: 16px; flex-shrink: 0; }
    .event-status-badge { 
        display: inline-block; 
        padding: 6px 16px; 
        background: rgba(255,255,255,0.25); 
        border-radius: 20px; 
        font-size: 13px; 
        font-weight: 600; 
        text-transform: uppercase; 
        margin-bottom: 12px;
        color: #ffffff;
    }
    .event-hero-actions { 
        position: absolute; 
        top: 24px; 
        right: 24px; 
        display: flex; 
        gap: 10px; 
        flex-wrap: wrap; 
        justify-content: flex-end; 
        max-width: 50%;
    }
    .status-btn { 
        display: inline-flex; 
        align-items: center; 
        gap: 6px; 
        padding: 10px 14px; 
        background: rgba(255,255,255,0.2); 
        color: #ffffff; 
        border: 1px solid rgba(255,255,255,0.35); 
        border-radius: 8px; 
        font-size: 13px; 
        font-weight: 600; 
        text-decoration: none; 
        cursor: pointer; 
        transition: all 0.2s; 
        white-space: nowrap;
    }
    .status-btn:hover { background: rgba(255,255,255,0.35); transform: translateY(-1px); }
    .status-btn.complete-btn:hover { background: #1b7a4b; }
    .status-btn.ongoing-btn:hover { background: #c23b8e; border-color: #c23b8e; }
    .status-btn.archive-btn:hover { background: #7a2f5f; border-color: #7a2f5f; }
    .status-btn.feedback-btn { background: #ffffff; color: #5c2249; border-color: #ffffff; font-family: var(--font-display); }
    .status-btn.feedback-btn:hover { background: #f7d9ec; border-color: #f7d9ec; color: #5c2249; }
    .status-btn.locked-btn { background: rgba(255,255,255,0.15); border-color: rgba(255,255,255,0.3); cursor: default; opacity: 0.9; }
    .status-btn.locked-btn:hover { background: rgba(255,255,255,0.15); }
    .status-btn .icon-svg { width: 14px; height: 14px; flex-shrink: 0; }

    .back-link { 
        color: #5c2249; 
        text-decoration: none; 
        display: inline-flex; 
        align-items: center; 
        gap: 6px; 
        margin-bottom: 16px; 
        font-weight: 500;
    }
    .back-link:hover { text-decoration: underline; }
    .back-link .icon-svg { width: 16px; height: 16px; }

    .quick-actions { 
        display: grid; 
        grid-template-columns: repeat(5, 1fr); 
        gap: 16px; 
        margin-bottom: 28px; 
    }
    .action-btn { 
        background: #ffffff; 
        padding: 24px 16px; 
        border-radius: 12px; 
        text-decoration: none; 
        color: #5c2249; 
        box-shadow: 0 2px 8px rgba(46, 15, 38, 0.08); 
        transition: all 0.25s; 
        text-align: center; 
        border: 1px solid #e9e3ea;
    }
    .action-btn:hover { 
        transform: translateY(-4px); 
        box-shadow: 0 8px 24px rgba(46, 15, 38, 0.15); 
        border-color: #ecc9df;
    }
    .action-icon { 
        display: flex; 
        justify-content: center; 
        margin-bottom: 10px; 
        color: #c23b8e; 
    }
    .action-icon .icon-svg { width: 28px; height: 28px; stroke-width: 1.8; }
    .action-label { 
        font-weight: 600; 
        font-size: 14px; 
        color: #2e0f26;
        line-height: 1.3;
    }

    .stats-grid { 
        display: grid; 
        grid-template-columns: repeat(4, 1fr); 
        gap: 16px; 
        margin-bottom: 28px; 
    }
    .stat-card { 
        background: #ffffff; 
        padding: 20px; 
        border-radius: 12px; 
        border: 1px solid #e9e3ea;
    }
    .stat-bar { height: 4px; border-radius: 4px; background: #e9e3ea; margin-top: 10px; overflow: hidden; }
    .stat-bar i { display: block; height: 100%; background: #c23b8e; border-radius: 4px; }
    .stat-label { 
        font-size: 13px; 
        color: #4a3d52; 
        text-transform: uppercase; 
        margin-bottom: 8px; 
        font-weight: 500;
        letter-spacing: 0.3px;
    }
    .stat-value { 
        font-size: 32px; 
        font-weight: 700; 
        color: #2e0f26; 
        line-height: 1;
    }

    .info-grid { 
        display: grid; 
        grid-template-columns: 2fr 1fr; 
        gap: 24px; 
    }
    .info-card { 
        background: #ffffff; 
        padding: 24px; 
        border-radius: 14px; 
        box-shadow: 0 2px 8px rgba(46, 15, 38, 0.08); 
    }
    .info-title { 
        font-size: 16px; 
        font-weight: 700; 
        color: #2e0f26; 
        margin-bottom: 16px; 
        display: flex; 
        align-items: center; 
        gap: 8px; 
    }
    .info-title .icon-svg { width: 18px; height: 18px; color: #c23b8e; }
    .info-card p { color: #444444; line-height: 1.7; margin: 0; }
    .ev-badge { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .ev-badge-thumb { flex: none; }
    .ev-badge strong { display: block; color: var(--color-text-strong); }
    .ev-badge p { font-size: 13px; color: var(--color-text-muted); }
    .ev-badge-change { margin-top: 12px; }
    .ev-badge-change summary { cursor: pointer; display: inline-block; }
    .ev-badge-change form { margin-top: 12px; }

    .company-list, .session-list { 
        display: flex; 
        flex-wrap: wrap; 
        gap: 10px; 
    }
    .company-chip { 
        padding: 8px 16px; 
        background: #f7d9ec; 
        color: #5c2249; 
        border-radius: 20px; 
        font-size: 14px; 
        font-weight: 600; 
    }
    .session-item { 
        padding: 14px; 
        background: #faf7f9; 
        border-radius: 10px; 
        border-left: 3px solid #c23b8e; 
        margin-bottom: 10px; 
    }
    .session-name { font-weight: 600; color: #2e0f26; font-size: 15px; }
    .session-meta { 
        font-size: 13px; 
        color: #777777; 
        margin-top: 6px; 
        display: flex; 
        align-items: center; 
        gap: 10px; 
        flex-wrap: wrap; 
    }
    .session-meta span { display: inline-flex; align-items: center; gap: 4px; }
    .session-meta .icon-svg { width: 13px; height: 13px; }

    .progress-bar {
        background: #f4eaf1; 
        height: 12px; 
        border-radius: 6px; 
        overflow: hidden;
        margin: 12px 0;
    }
    .progress-fill {
        height: 100%; 
        background: linear-gradient(90deg, #c23b8e, #E91E63);
        border-radius: 6px;
        transition: width 0.4s ease;
    }

    @media (max-width: 1024px) {
        .quick-actions { grid-template-columns: repeat(3, 1fr); }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .info-grid { grid-template-columns: 1fr; }
        .event-hero-actions { position: static; margin-bottom: 20px; max-width: 100%; }
    }
</style>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/mobile.css?v=<?= ASSET_VER ?>" media="(max-width: 768px)">
</head>
<body class="dashboard-body page-event-view">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php $back_url = BASE_URL . '/pages/events/index.php'; $back_label = 'Back to Events'; include __DIR__ . '/../../includes/header.php'; ?>



        <!-- Event Hero Section -->
        <div class="event-hero">
            <div class="event-hero-actions">
                <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])): ?>
                <?php if ($event['status'] === 'upcoming'): ?>
                    <a href="<?= BASE_URL ?>/api/events/update_status.php?id=<?= $event_id ?>&status=ongoing"
                       class="status-btn ongoing-btn"
                       data-confirm="Staff can start checking in attendees once it is live." data-confirm-title="Mark this event as Ongoing?" data-confirm-ok="Mark as Ongoing">
                        <?= icon('zap', ['class' => 'icon-svg']) ?>
                        Mark as Ongoing
                    </a>
                <?php endif; ?>
                <?php if ($event['status'] === 'upcoming' || $event['status'] === 'ongoing'): ?>
                    <a href="<?= BASE_URL ?>/api/events/update_status.php?id=<?= $event_id ?>&status=completed"
                       class="status-btn complete-btn"
                       data-confirm="The event moves to Completed. You can re-open it until it is archived." data-confirm-title="Mark this event as Completed?" data-confirm-ok="Mark as Completed">
                        <?= icon('check-circle', ['class' => 'icon-svg']) ?>
                        Mark as Completed
                    </a>
                <?php endif; ?>
                <?php if ($event['status'] === 'completed'): ?>
                    <?php if ($fb_pending > 0): ?>
                    <form method="POST" action="<?= BASE_URL ?>/pages/feedback/index.php" style="display: contents;"
                          data-confirm="<?= $fb_pending ?> checked-in attendee(s) who have not received it yet will get a personal feedback link by email." data-confirm-title="Send feedback links?" data-confirm-ok="Send links">
                        <?= csrfField() ?>
                        <input type="hidden" name="event_id" value="<?= $event_id ?>">
                        <input type="hidden" name="return" value="event">
                        <button type="submit" class="status-btn feedback-btn" name="send_links" value="1">
                            <?= icon('send', ['class' => 'icon-svg']) ?>
                            Send feedback links (<?= $fb_pending ?>)
                        </button>
                    </form>
                    <?php elseif ($fb_pending === 0 && $stats['checked_in'] > 0): ?>
                    <span class="status-btn locked-btn" title="Every checked-in attendee with an email has the feedback link">
                        <?= icon('check', ['class' => 'icon-svg']) ?>
                        Feedback links sent
                    </span>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/api/events/archive.php?event_id=<?= $event_id ?>"
                       class="status-btn archive-btn"
                       data-confirm="The attendee list is exported to CSV. Archiving is permanent: the event and its attendee records become read-only and cannot be re-opened." data-confirm-title="Export and archive this event?" data-confirm-ok="Export &amp; Archive" data-confirm-danger>
                        <?= icon('briefcase', ['class' => 'icon-svg']) ?>
                        Export & Archive
                    </a>
                    <a href="<?= BASE_URL ?>/api/events/update_status.php?id=<?= $event_id ?>&status=upcoming"
                       class="status-btn"
                       data-confirm="The event goes back to Upcoming so it can be edited and run again." data-confirm-title="Re-open this event?" data-confirm-ok="Re-open">
                        <?= icon('arrow-left', ['class' => 'icon-svg']) ?>
                        Re-open
                    </a>
                <?php endif; ?>
                <?php if ($event['status'] === 'archived'): ?>
                    <a href="<?= BASE_URL ?>/api/attendees/export.php?event_id=<?= $event_id ?>"
                       class="status-btn">
                        <?= icon('download', ['class' => 'icon-svg']) ?>
                        Re-download CSV
                    </a>
                    <span class="status-btn locked-btn" title="Archived events are permanent and read-only">
                        <?= icon('shield', ['class' => 'icon-svg']) ?>
                        Locked &mdash; Permanent
                    </span>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <span class="event-status-badge"><?= ucfirst($event['status']) ?></span>
            <h2><?= htmlspecialchars($event['event_name']) ?></h2>
            <div class="event-hero-meta">
                <span><?= icon('calendar', ['class' => 'icon-svg']) ?> <?= formatDate($event['event_date']) ?> at <?= formatTime($event['event_time']) ?></span>
                <span><?= icon('map-pin', ['class' => 'icon-svg']) ?> <?= htmlspecialchars($event['location']) ?></span>
                <?php if ($event['event_manager']): ?>
                    <span><?= icon('user', ['class' => 'icon-svg']) ?> <?= htmlspecialchars($event['event_manager']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Action Buttons -->
        <div class="quick-actions">
            <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])): ?>
            <a href="<?= BASE_URL ?>/pages/attendees/upload.php?event_id=<?= $event_id ?>" class="action-btn">
                <div class="action-icon"><?= icon('upload', ['class' => 'icon-svg']) ?></div>
                <div class="action-label">Upload Attendees</div>
            </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/pages/attendees/index.php?event_id=<?= $event_id ?>" class="action-btn">
                <div class="action-icon"><?= icon('users', ['class' => 'icon-svg']) ?></div>
                <div class="action-label">View Attendees</div>
            </a>
            <a href="<?= BASE_URL ?>/pages/checkin/scan.php?event_id=<?= $event_id ?>" class="action-btn">
                <div class="action-icon"><?= icon('camera', ['class' => 'icon-svg']) ?></div>
                <div class="action-label">Scan QR</div>
            </a>
            <a href="<?= BASE_URL ?>/pages/checkin/walkin.php?event_id=<?= $event_id ?>" class="action-btn">
                <div class="action-icon"><?= icon('walk', ['class' => 'icon-svg']) ?></div>
                <div class="action-label">Walk-in</div>
            </a>
            <a href="<?= BASE_URL ?>/pages/sessions/index.php?event_id=<?= $event_id ?>" class="action-btn">
                <div class="action-icon"><?= icon('mic', ['class' => 'icon-svg']) ?></div>
                <div class="action-label">Sessions</div>
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Attendees</div>
                <div class="stat-value"><?= $stats['total'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Checked In</div>
                <div class="stat-value" style="color: var(--magenta);"><?= $stats['checked_in'] ?></div>
                <div class="stat-bar"><i style="width: <?= min(100, max(0, (float)$stats['percentage'])) ?>%"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Not Yet</div>
                <div class="stat-value"><?= $stats['not_yet'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Walk-ins</div>
                <div class="stat-value"><?= $stats['walk_ins'] ?></div>
            </div>
        </div>

        <!-- Info Section -->
        <div class="info-grid">
            <div class="info-card">
                <div class="info-title">
                    <?= icon('file-text', ['class' => 'icon-svg']) ?>
                    Description
                </div>
                <p><?= nl2br(htmlspecialchars($event['description'] ?: 'No description.')) ?></p>

                <div class="info-title" style="margin-top: 24px;">
                    <?= icon('mic', ['class' => 'icon-svg']) ?>
                    Sessions
                    <a class="cap-link" style="margin: 0 0 0 auto;" href="<?= BASE_URL ?>/pages/sessions/index.php?event_id=<?= $event_id ?>">Manage sessions</a>
                </div>
                <?php if ($sessions->num_rows === 0): ?>
                    <p class="muted" style="font-size: 14px;">No seminars or breakout sessions yet.</p>
                <?php else: ?>
                    <div class="session-list">
                        <?php while ($session = $sessions->fetch_assoc()): ?>
                            <div class="session-item">
                                <div class="session-name"><?= htmlspecialchars($session['session_name']) ?></div>
                                <div class="session-meta">
                                    <span><?= icon('user', ['class' => 'icon-svg']) ?> <?= htmlspecialchars($session['speaker_name']) ?></span>
                                    <span><?= icon('calendar', ['class' => 'icon-svg']) ?> <?= formatDate($session['session_date']) ?></span>
                                    <span><?= icon('clock', ['class' => 'icon-svg']) ?> <?= formatTime($session['start_time']) ?></span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>

                <div class="info-title" style="margin-top: 24px;">
                    <?= icon('ticket', ['class' => 'icon-svg']) ?>
                    Badge design
                </div>
                <div class="ev-badge">
                    <?php if ($ev_tpl): ?><div class="ev-badge-thumb" id="evBadgeThumb"></div><?php endif; ?>
                    <div>
                        <strong><?= $ev_tpl ? htmlspecialchars($ev_tpl['template_name']) : 'Standard badge' ?></strong>
                        <p><?= $ev_tpl && (int)$ev_tpl['id'] !== (int)$event['badge_template_id'] ? 'No design chosen for this event, so the favorite design is used.' : 'Every badge for this event prints with this design.' ?></p>
                    </div>
                </div>
                <?php $badge_render_loaded = true; ?>
                <script src="<?= BASE_URL ?>/assets/js/badge-render.js?v=<?= ASSET_VER ?>"></script>
                <?php if ($ev_tpl): ?>
                <script>
                    document.getElementById('evBadgeThumb').innerHTML = renderBadge(<?= json_encode(json_decode($ev_tpl['layout_json'], true), JSON_HEX_TAG) ?>, Object.assign({}, BADGE_SAMPLE, { event: <?= json_encode($event['event_name']) ?> }), 0.5);
                </script>
                <?php endif; ?>
                <?php if ($is_admin): ?>
                    <details class="ev-badge-change">
                        <summary class="cap-link">Change design</summary>
                        <form method="POST" action="<?= BASE_URL ?>/api/events/set_badge.php">
                            <input type="hidden" name="event_id" value="<?= $event_id ?>">
                            <?php $bp_picked = (int)($ev_tpl['id'] ?? 0); include __DIR__ . '/../../includes/badge_picker.php'; ?>
                            <div class="form-actions"><button type="submit" class="btn btn-primary">Save badge design</button></div>
                        </form>
                    </details>
                <?php endif; ?>
            </div>

            <div class="info-card">
                <div class="info-title">
                    <?= icon('bar-chart', ['class' => 'icon-svg']) ?>
                    Progress
                </div>
                <div class="cap-row cap-event">
                    <div class="cap-top"><b>Checked in</b><span class="cap-count"><strong><?= (int)$stats['checked_in'] ?></strong> / <?= (int)$stats['total'] ?> &middot; <?= $stats['percentage'] ?>%</span></div>
                    <div class="progress"><i style="width: <?= min(100, max(0, (float)$stats['percentage'])) ?>%"></i></div>
                </div>

                <div class="info-title" style="margin-top: 28px;">
                    <?= icon('building', ['class' => 'icon-svg']) ?>
                    Companies
                </div>
                <?php $reg_total = (int)$stats['total']; $ev_cap = (int)($event['max_attendees'] ?? 0); ?>
                <?php if ($ev_cap): ?>
                    <div class="cap-row cap-event">
                        <div class="cap-top"><b>Event capacity</b><span class="cap-count"><?= capacityBadge($reg_total, $ev_cap) ?><strong><?= $reg_total ?></strong> / <?= $ev_cap ?></span></div>
                        <div class="progress<?= $reg_total >= $ev_cap ? ' is-full' : '' ?>"><i style="width: <?= min(100, round($reg_total / $ev_cap * 100)) ?>%"></i></div>
                    </div>
                <?php endif; ?>
                <?php if ($company_caps): foreach ($company_caps as $c): $n = (int)$c['total']; $cap = (int)$c['cap']; ?>
                    <div class="cap-row">
                        <div class="cap-top">
                            <b title="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></b>
                            <span class="cap-count"><?= capacityBadge($n, $cap) ?><strong><?= $n ?></strong><?= $cap ? ' / ' . $cap : ' registered' ?></span>
                        </div>
                        <?php if ($cap): ?><div class="progress<?= $n >= $cap ? ' is-full' : '' ?>"><i style="width: <?= min(100, round($n / $cap * 100)) ?>%"></i></div><?php endif; ?>
                    </div>
                <?php endforeach; else: ?>
                    <p class="muted" style="font-size: 14px;">No companies added.</p>
                <?php endif; ?>
                <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])): ?>
                    <a class="cap-link" href="<?= BASE_URL ?>/pages/companies/index.php?event_id=<?= $event_id ?>">Set company limits</a>
                <?php endif; ?>

            </div>
        </div>
    </main>
</div>
</body>
</html>