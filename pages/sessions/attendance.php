<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../modules/SessionManager.php';
requireLogin();

$session_id = (int)($_GET['session_id'] ?? 0);
$mgr = new SessionManager($conn);
$session = $mgr->find($session_id);

if (!$session) redirect(BASE_URL . '/pages/sessions/index.php', 'Session not found.', 'error');

$attendees = $mgr->attendees($session_id);
$page_title = 'Session Attendance';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($session['session_name']) ?> &mdash; Attendance</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php $back_url = BASE_URL . '/pages/sessions/index.php?event_id=' . (int)$session['event_id']; $back_label = 'Back to Sessions'; include __DIR__ . '/../../includes/header.php'; ?>

        <div class="hero">
            <h2><?= htmlspecialchars($session['session_name']) ?></h2>
            <p><?= htmlspecialchars($session['event_name']) ?><?= $session['speaker_name'] ? ' &middot; ' . htmlspecialchars($session['speaker_name']) : '' ?></p>
            <span class="pill"><?= icon('users', ['class' => 'icon-svg icon-sm']) ?> <?= count($attendees) ?> attended</span>
        </div>

        <?php if (empty($attendees)): ?>
            <div class="empty-state">
                <?= icon('qr', ['class' => 'icon-svg']) ?>
                <strong>No attendance yet</strong>
                <p>No one has been scanned into this session yet. Scan attendee QR codes at the door to record attendance.</p>
                <a class="btn-sm" href="<?= BASE_URL ?>/pages/checkin/scan.php?event_id=<?= $session['event_id'] ?>&session_id=<?= $session_id ?>"><?= icon('camera', ['class' => 'icon-svg icon-sm']) ?> Scan for this session</a>
            </div>
        <?php else: ?>
            <div class="panel">
                <h3>Attendance list</h3>
                <table class="tbl">
                    <thead><tr><th>Name</th><th>Company</th><th>Code</th><th>Scanned At</th></tr></thead>
                    <tbody>
                        <?php foreach ($attendees as $a): ?>
                            <tr>
                                <td><?= htmlspecialchars($a['full_name']) ?></td>
                                <td><?= htmlspecialchars($a['company']) ?></td>
                                <td><code><?= htmlspecialchars($a['attendee_code']) ?></code></td>
                                <td class="muted"><?= date('M j, g:i A', strtotime($a['scanned_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
