<?php

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../includes/qrcode.php';
requireLogin();

$attendee_id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT a.*, e.event_name FROM attendees a JOIN events e ON a.event_id = e.id WHERE a.id = ?");
$stmt->bind_param("i", $attendee_id);
$stmt->execute();
$attendee = $stmt->get_result()->fetch_assoc();

if (!$attendee) {
    redirect(BASE_URL . '/pages/attendees/index.php', 'Attendee not found.', 'error');
}

$qr_url = getQRCodeImageUrl($attendee['qr_code'], $attendee['qr_image_path']);
$page_title = 'QR Code';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $page_title ?> &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<style>
    .qr-container { max-width: 500px; margin: 0 auto; background: white; padding: 40px; border-radius: 20px; box-shadow: var(--shadow-md); text-align: center; }
    .qr-image { width: 280px; height: 280px; margin: 20px auto; border: 4px solid var(--purple-light); border-radius: 12px; padding: 12px; background: white; }
    .qr-name { font-size: 24px; font-weight: 700; color: var(--purple-mid); margin-top: 10px; }
    .qr-company { color: var(--gray-mid); margin-top: 4px; }
    .qr-code-text { font-family: monospace; font-size: 12px; color: var(--gray-mid); margin-top: 16px; padding: 8px 12px; background: var(--off-white); border-radius: 6px; word-break: break-all; }
    .qr-actions { display: flex; gap: 10px; margin-top: 24px; }
    .qr-actions .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
    .back-link { color: var(--purple-mid); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 20px; }
    .back-link .icon-svg { width: 16px; height: 16px; }
</style>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/mobile.css?v=<?= ASSET_VER ?>" media="(max-width: 768px)">
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php $back_url = BASE_URL . '/pages/attendees/index.php?event_id=' . (int)$attendee['event_id']; $back_label = 'Back to Attendees'; include __DIR__ . '/../../includes/header.php'; ?>


        <div class="qr-container">
            <h3 style="color: var(--purple-mid);">Attendee QR Code</h3>
            <p style="color: var(--gray-mid); font-size: 13px;"><?= htmlspecialchars($attendee['event_name']) ?></p>

            <img src="<?= $qr_url ?>" alt="QR Code" class="qr-image">

            <div class="qr-name"><?= htmlspecialchars($attendee['full_name']) ?></div>
            <div class="qr-company"><?= htmlspecialchars($attendee['company']) ?></div>
            <div style="color: var(--purple-mid); font-weight: 700; margin-top: 6px;"><?= htmlspecialchars($attendee['attendee_code']) ?></div>

            <div class="qr-code-text"><?= htmlspecialchars($attendee['qr_code']) ?></div>

            <div class="qr-actions">
                <a href="<?= BASE_URL ?>/pages/checkin/badge.php?attendee_id=<?= $attendee['id'] ?>&from=qr" class="btn btn-primary" style="flex: 1;">
                    <?= icon('ticket', ['class' => 'icon-svg icon-sm']) ?>
                    View Badge
                </a>
            </div>
        </div>
    </main>
</div>
</body>
</html>
