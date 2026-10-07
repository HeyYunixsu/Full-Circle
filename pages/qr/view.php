<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../includes/qrcode.php';

$code = preg_replace('/[^A-Za-z0-9\-]/', '', $_GET['code'] ?? '');

$attendee = null;
$event = null;

if ($code !== '') {
    $stmt = $conn->prepare("SELECT a.*, e.event_name, e.event_date, e.event_time, e.location, e.status AS event_status
                            FROM attendees a JOIN events e ON a.event_id = e.id
                            WHERE a.attendee_code = ? LIMIT 1");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $attendee = $stmt->get_result()->fetch_assoc();
    if ($attendee) $event = $attendee;
}

$qr_url = $attendee ? getQRCodeImageUrl($attendee['qr_code'], $attendee['qr_image_path']) : null;
$checked_in = $attendee && $attendee['status'] === 'checked_in';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $attendee ? htmlspecialchars($attendee['event_name']) : 'QR Not Found' ?> &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<style>
    /* Admission ticket, same family as the login page ticket */
    .ticket { width: min(440px, 100%); background: #fbf5ec; color: var(--wine-darkest); border-radius: 16px; box-shadow: var(--shadow-lg); overflow: hidden; }
    .ticket-main { padding: 26px 26px 22px; position: relative; border-bottom: 2px dashed rgba(92, 34, 73, .28); }
    .ticket-main::before, .ticket-main::after { content: ''; position: absolute; bottom: -12px; width: 22px; height: 22px; border-radius: 50%; background: var(--wine-darkest); }
    .ticket-main::before { left: -11px; }
    .ticket-main::after { right: -11px; }
    .ticket-kicker { display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: var(--magenta); font-weight: 700; }
    .ticket-kicker .ring { width: 26px; height: 26px; border-radius: 50%; border: 2px solid var(--pink-bright); display: grid; place-items: center; font-size: 9px; font-weight: 800; color: var(--wine-darkest); letter-spacing: .5px; }
    .ticket-title { font-size: 24px; font-weight: 800; letter-spacing: -.4px; margin: 8px 0 14px; line-height: 1.15; }
    .ticket-meta { display: flex; flex-wrap: wrap; gap: 12px 24px; }
    .ticket-meta small { display: block; font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; color: var(--color-text-muted); }
    .ticket-meta b { font-size: 14px; }
    .ticket-stub { padding: 24px 26px 22px; text-align: center; }
    .qr-wrap { display: inline-block; padding: 10px; background: var(--white); border: 1px solid rgba(92, 34, 73, .18); border-radius: 12px; }
    .qr-wrap img { display: block; width: 220px; height: 220px; max-width: 100%; }
    .holder { font-size: 18px; font-weight: 700; margin-top: 14px; }
    .company { font-size: 14px; color: var(--color-text-muted); }
    .code { font-family: monospace; font-size: 15px; letter-spacing: 1.5px; color: var(--magenta); font-weight: 700; margin-top: 8px; }
    .status { margin-top: 14px; }
    .hint { font-size: 12px; color: var(--color-text-muted); margin-top: 16px; line-height: 1.5; }
    .ticket-foot { background: var(--wine-darkest); color: rgba(255, 255, 255, .6); font-size: 11px; text-align: center; padding: 10px; letter-spacing: .3px; }
    .notfound { text-align: center; }
    .notfound .icon-svg { width: 40px; height: 40px; color: var(--danger); margin-bottom: 8px; }
    .notfound p { color: var(--color-text-muted); font-size: 14px; margin-top: 8px; }
</style>
</head>
<body class="auth-body">
    <?php if (!$attendee): ?>
        <div class="auth-card notfound">
            <div class="auth-mark"><span class="ring">FC</span> <?= SITE_NAME ?></div>
            <?= icon('x-circle', ['class' => 'icon-svg']) ?>
            <div class="auth-logo"><h1>QR Not Found</h1></div>
            <p>Invalid or expired code. Please check the link sent to you, or contact the event organizer.</p>
        </div>
    <?php else: ?>
        <article class="ticket" aria-label="Your admission QR">
            <div class="ticket-main">
                <div class="ticket-kicker"><span>Admit one</span><span class="ring">FC</span></div>
                <h1 class="ticket-title"><?= htmlspecialchars($attendee['event_name']) ?></h1>
                <div class="ticket-meta">
                    <span><small>Date</small><b><?= date('M j, Y', strtotime($attendee['event_date'])) ?></b></span>
                    <span><small>Time</small><b><?= date('g:i A', strtotime($attendee['event_time'])) ?></b></span>
                    <span><small>Venue</small><b><?= htmlspecialchars($attendee['location']) ?></b></span>
                </div>
            </div>
            <div class="ticket-stub">
                <?php if ($qr_url): ?>
                    <div class="qr-wrap"><img src="<?= htmlspecialchars($qr_url) ?>" alt="Your QR code"></div>
                <?php endif; ?>

                <div class="holder"><?= htmlspecialchars($attendee['full_name']) ?></div>
                <?php if (!empty($attendee['company'])): ?>
                    <div class="company"><?= htmlspecialchars($attendee['company']) ?></div>
                <?php endif; ?>
                <div class="code"><?= htmlspecialchars($attendee['attendee_code']) ?></div>

                <div class="status">
                    <?php if ($checked_in): ?>
                        <span class="pill-ok"><?= icon('check', ['class' => 'icon-svg icon-sm']) ?> Checked in</span>
                    <?php else: ?>
                        <span class="pill-warn">Show this QR at the entrance</span>
                    <?php endif; ?>
                </div>

                <p class="hint">Present this screen to the staff at the entrance. They will scan the QR code to check you in.</p>
            </div>
            <div class="ticket-foot"><?= SITE_NAME ?>, Inc.</div>
        </article>
    <?php endif; ?>
</body>
</html>
