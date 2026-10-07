<?php
// Public feedback form, opened from the emailed link. No login:
// the attendee's QR value (?c=) identifies them.
require_once __DIR__ . '/../../core/bootstrap.php';

$code = trim($_GET['c'] ?? '');
$stmt = $conn->prepare("SELECT a.id, a.full_name, a.event_id, e.event_name, e.event_date
                        FROM attendees a JOIN events e ON e.id = a.event_id WHERE a.qr_code = ? LIMIT 1");
$stmt->bind_param("s", $code);
$stmt->execute();
$att = $code !== '' ? $stmt->get_result()->fetch_assoc() : null;

$sessions = [];
$existing = ['event' => null];
$saved = false;
$error = '';

if ($att) {
    $aid = (int)$att['id'];
    $eid = (int)$att['event_id'];

    // Sessions the attendee was scanned into; if none were scanned, offer all of the event's sessions.
    $sessions = $conn->query("SELECT s.id, s.session_name, s.speaker_name FROM sessions s
                              JOIN session_attendance sa ON sa.session_id = s.id AND sa.attendee_id = $aid
                              WHERE s.event_id = $eid ORDER BY s.session_date, s.start_time")->fetch_all(MYSQLI_ASSOC);
    if (!$sessions) {
        $sessions = $conn->query("SELECT id, session_name, speaker_name FROM sessions WHERE event_id = $eid
                                  ORDER BY session_date, start_time")->fetch_all(MYSQLI_ASSOC);
    }
    $session_ids = array_column($sessions, 'id');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rating  = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if ($rating < 1 || $rating > 5) {
            $error = 'Please give the event a rating from 1 to 5 stars.';
        } else {
            $conn->begin_transaction();
            $find = $conn->prepare("SELECT id FROM feedback WHERE event_id = ? AND attendee_id = ? AND session_id IS NULL");
            $find->bind_param("ii", $eid, $aid);
            $find->execute();
            $row = $find->get_result()->fetch_assoc();
            if ($row) {
                $q = $conn->prepare("UPDATE feedback SET rating = ?, comment = ?, submitted_at = NOW() WHERE id = ?");
                $q->bind_param("isi", $rating, $comment, $row['id']);
            } else {
                $q = $conn->prepare("INSERT INTO feedback (event_id, attendee_id, rating, comment) VALUES (?, ?, ?, ?)");
                $q->bind_param("iiis", $eid, $aid, $rating, $comment);
            }
            $q->execute();

            $q = $conn->prepare("INSERT INTO feedback (event_id, session_id, attendee_id, rating, comment) VALUES (?, ?, ?, ?, ?)
                                 ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), submitted_at = NOW()");
            foreach ((array)($_POST['session_rating'] ?? []) as $sid => $sr) {
                $sid = (int)$sid;
                $sr  = (int)$sr;
                if (!in_array($sid, $session_ids) || $sr < 1 || $sr > 5) continue;
                $sc = trim($_POST['session_comment'][$sid] ?? '');
                $q->bind_param("iiiis", $eid, $sid, $aid, $sr, $sc);
                $q->execute();
            }
            $conn->commit();
            $saved = true;
        }
    }

    foreach ($conn->query("SELECT session_id, rating, comment FROM feedback WHERE attendee_id = $aid AND event_id = $eid") as $f) {
        $existing[$f['session_id'] ? (int)$f['session_id'] : 'event'] = $f;
    }
}

function stars($name, $current, $required = false) {
    $html = '<div class="stars">';
    for ($i = 5; $i >= 1; $i--) {   // reversed + row-reverse so CSS ~ can light up lower stars
        $id = preg_replace('/\W/', '_', $name) . "_$i";
        $html .= "<input type=\"radio\" id=\"$id\" name=\"$name\" value=\"$i\"" . ((int)$current === $i ? ' checked' : '')
               . ($required ? ' required' : '') . "><label for=\"$id\" title=\"$i star" . ($i > 1 ? 's' : '') . "\">&#9733;</label>";
    }
    return $html . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Event Feedback &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<style>
    .auth-card { max-width: 560px; }
    .fb-event { color: var(--color-text-muted); font-size: 14px; margin-bottom: 24px; text-align: center; }
    .fb-block { margin-bottom: 22px; }
    .fb-block h3 { font-size: 15px; color: var(--color-text-strong); margin-bottom: 4px; }
    .fb-block small { color: var(--color-text-muted); display: block; margin-bottom: 6px; }
    .stars { display: inline-flex; flex-direction: row-reverse; gap: 4px; }
    .stars input { position: absolute; opacity: 0; width: 1px; height: 1px; }
    .stars label { font-size: 34px; color: var(--color-border); cursor: pointer; line-height: 1; transition: color var(--dur-fast); }
    .stars input:checked ~ label, .stars label:hover, .stars label:hover ~ label { color: var(--warning); }
    .stars input:focus-visible + label { outline: 2px solid var(--magenta); outline-offset: 2px; border-radius: 4px; }
    .fb-block textarea { width: 100%; margin-top: 8px; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); font: inherit; font-size: 14px; resize: vertical; background: var(--color-surface); }
    .fb-block textarea:focus { border-color: var(--magenta); box-shadow: var(--focus-ring); }
    .fb-sessions { border-top: 1px solid var(--color-border); padding-top: 18px; }
    .fb-done { text-align: center; padding: 10px 0 0; }
</style>
</head>
<body class="auth-body">
<div class="auth-card">
    <div class="auth-mark"><span class="ring">FC</span> <?= SITE_NAME ?></div>
    <div class="auth-logo"><h1>Feedback</h1></div>

    <?php if (!$att): ?>
        <div class="alert alert-error" role="alert"><?= icon('alert', ['class' => 'icon-svg icon-sm']) ?> This feedback link is invalid. Please use the link from your email.</div>
    <?php elseif ($saved): ?>
        <div class="fb-done">
            <div class="alert alert-success" role="status"><?= icon('check-circle', ['class' => 'icon-svg icon-sm']) ?> Thank you, <?= htmlspecialchars(explode(' ', $att['full_name'])[0]) ?>! Your feedback has been saved.</div>
            <p class="fb-event">You can open this link again any time to change your answers.</p>
        </div>
    <?php else: ?>
        <p class="fb-event"><?= htmlspecialchars($att['event_name']) ?> &middot; <?= formatDate($att['event_date']) ?></p>
        <?php if ($error): ?><div class="alert alert-error" role="alert"><?= icon('alert', ['class' => 'icon-svg icon-sm']) ?> <?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <div class="fb-block">
                <h3>How was the event overall? *</h3>
                <?= stars('rating', $existing['event']['rating'] ?? 0, true) ?>
                <textarea name="comment" rows="3" placeholder="What did you like? What can we improve? (optional)" aria-label="Event comment"><?= htmlspecialchars($existing['event']['comment'] ?? '') ?></textarea>
            </div>

            <?php if ($sessions): ?>
            <div class="fb-sessions">
                <p class="fb-event" style="text-align:left;margin-bottom:14px;">Rate the sessions you joined (optional)</p>
                <?php foreach ($sessions as $s): $sid = (int)$s['id']; ?>
                    <div class="fb-block">
                        <h3><?= htmlspecialchars($s['session_name']) ?></h3>
                        <?php if ($s['speaker_name']): ?><small><?= htmlspecialchars($s['speaker_name']) ?></small><?php endif; ?>
                        <?= stars("session_rating[$sid]", $existing[$sid]['rating'] ?? 0) ?>
                        <textarea name="session_comment[<?= $sid ?>]" rows="2" placeholder="Comment (optional)" aria-label="Comment for <?= htmlspecialchars($s['session_name']) ?>"><?= htmlspecialchars($existing[$sid]['comment'] ?? '') ?></textarea>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary full">Submit Feedback</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
