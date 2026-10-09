<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../modules/SessionManager.php';
requireLogin();

$event_id = (int)($_GET['event_id'] ?? 0);
$events_list = $conn->query("SELECT id, event_name FROM events ORDER BY event_date DESC");
if (!$event_id) {
    $latest = $conn->query("SELECT id FROM events ORDER BY event_date DESC LIMIT 1");
    if ($latest->num_rows > 0) $event_id = $latest->fetch_assoc()['id'];
}

$current_event = null;
$sessions = [];
if ($event_id) {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $current_event = $stmt->get_result()->fetch_assoc();
    $mgr = new SessionManager($conn);
    $sessions = $mgr->forEvent($event_id);
}

$locked   = $current_event && $current_event['status'] === 'archived';
$can_edit = in_array(currentRole(), ['super_admin', 'admin']) && !$locked;
$flash = getFlashMessage();
$page_title = 'Sessions';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $page_title ?> &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<style>
    .sess-card { display: flex; flex-direction: column; }
    .sess-name { font-size: 16px; font-weight: 700; color: var(--color-text-strong); margin-bottom: 2px; }
    .sess-speaker { font-size: 13px; color: var(--color-text-muted); margin-bottom: 12px; }
    .sess-meta { display: flex; flex-direction: column; gap: 4px; font-size: 13px; color: var(--color-text-muted); margin-bottom: 14px; }
    .sess-meta span { display: flex; align-items: center; gap: 6px; }
    .sess-meta .icon-svg { width: 14px; height: 14px; color: var(--color-text-faint); }
    .sess-stats { display: flex; margin-top: auto; padding-top: 14px; border-top: 1px solid var(--color-border); }
    .sess-stat { flex: 1; text-align: center; }
    .sess-stat b { display: block; font-size: 20px; color: var(--color-text-strong); font-variant-numeric: tabular-nums; }
    .sess-stat small { font-size: 11px; font-weight: 600; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: .5px; }
    .sess-actions { display: flex; gap: 8px; margin-top: 14px; }
    .sess-actions .btn-sm { flex: 1; padding: 0 10px; }
    .modal .form-group { margin-bottom: 12px; }
</style>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/mobile.css?v=<?= ASSET_VER ?>" media="(max-width: 768px)">
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php $back_url = ($current_event ? BASE_URL . '/pages/events/view.php?id=' . (int)$event_id : null); $back_label = 'Back to event'; include __DIR__ . '/../../includes/header.php'; ?>


        <?php if (!$current_event): ?>
            <div class="empty-state">
                <?= icon('calendar', ['class' => 'icon-svg']) ?>
                <strong>No events yet</strong>
                <p>Create an event first, then add its seminars and sessions here.</p>
            </div>
        <?php else: ?>
        <div class="toolbar">
            <form method="GET">
                <select name="event_id" onchange="this.form.submit()" aria-label="Event">
                    <?php $events_list->data_seek(0); while ($e = $events_list->fetch_assoc()): ?>
                        <option value="<?= $e['id'] ?>" <?= $e['id'] == $event_id ? 'selected' : '' ?>><?= htmlspecialchars($e['event_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </form>
            <span class="muted"><?= count($sessions) ?> session<?= count($sessions) === 1 ? '' : 's' ?></span>
            <span class="spacer"></span>
            <?php if ($can_edit): ?>
                <button class="btn-sm" onclick="openSession()"><?= icon('plus', ['class' => 'icon-svg icon-sm']) ?> Add Session</button>
            <?php endif; ?>
        </div>

        <?php if ($locked): ?>
            <div class="alert alert-warning"><?= icon('info', ['class' => 'icon-svg icon-sm']) ?> This event is archived. Sessions are read-only.</div>
        <?php endif; ?>

        <?php if (empty($sessions)): ?>
            <div class="empty-state">
                <?= icon('list', ['class' => 'icon-svg']) ?>
                <strong>No sessions yet</strong>
                <p><?= $can_edit ? 'Use Add Session to create the first seminar or breakout session for this event.' : 'Sessions will appear here once an admin adds them.' ?></p>
            </div>
        <?php else: ?>
            <div class="card-grid">
                <?php foreach ($sessions as $s): ?>
                    <div class="panel sess-card">
                        <div class="sess-name"><?= htmlspecialchars($s['session_name']) ?></div>
                        <div class="sess-speaker"><?= $s['speaker_name'] ? htmlspecialchars($s['speaker_name']) . ($s['speaker_role'] ? ' &middot; ' . htmlspecialchars($s['speaker_role']) : '') : 'No speaker set' ?></div>
                        <div class="sess-meta">
                            <?php if ($s['session_date']): ?><span><?= icon('calendar', ['class' => 'icon-svg']) ?> <?= date('M j, Y', strtotime($s['session_date'])) ?></span><?php endif; ?>
                            <?php if ($s['start_time']): ?><span><?= icon('clock', ['class' => 'icon-svg']) ?> <?= date('g:i A', strtotime($s['start_time'])) ?><?= $s['end_time'] ? ' - ' . date('g:i A', strtotime($s['end_time'])) : '' ?></span><?php endif; ?>
                            <?php if ($s['location']): ?><span><?= icon('map-pin', ['class' => 'icon-svg']) ?> <?= htmlspecialchars($s['location']) ?></span><?php endif; ?>
                        </div>
                        <div class="sess-stats">
                            <div class="sess-stat"><b><?= (int)$s['attended'] ?></b><small>Attended</small></div>
                            <div class="sess-stat"><b><?= (int)$s['feedback_count'] ?></b><small>Feedback</small></div>
                            <div class="sess-stat"><b><?= $s['avg_rating'] ?: '—' ?></b><small>Avg Rating</small></div>
                        </div>
                        <div class="sess-actions">
                            <?php if (!$locked): ?>
                                <a class="btn-sm" href="<?= BASE_URL ?>/pages/checkin/scan.php?event_id=<?= $event_id ?>&session_id=<?= $s['id'] ?>"><?= icon('qr', ['class' => 'icon-svg icon-sm']) ?> Scan</a>
                            <?php endif; ?>
                            <a class="btn-sm light" href="<?= BASE_URL ?>/pages/sessions/attendance.php?session_id=<?= $s['id'] ?>">View</a>
                            <?php if ($can_edit): ?>
                                <button class="btn-sm light" onclick='openSession(<?= json_encode([
                                    "id" => (int)$s["id"], "session_name" => $s["session_name"],
                                    "speaker_name" => $s["speaker_name"], "speaker_role" => $s["speaker_role"],
                                    "session_date" => $s["session_date"], "start_time" => $s["start_time"],
                                    "end_time" => $s["end_time"], "location" => $s["location"],
                                    "description" => $s["description"],
                                ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                                <a class="btn-sm danger" href="<?= BASE_URL ?>/api/sessions/delete.php?id=<?= $s['id'] ?>" data-confirm="Its attendance records and feedback are deleted too. This cannot be undone." data-confirm-title="Delete this session?" data-confirm-ok="Delete" data-confirm-danger>Delete</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<?php if ($can_edit): ?>
<div class="modal-overlay" id="sess-overlay" onclick="if(event.target===this) closeSession()" role="dialog" aria-modal="true" aria-labelledby="sess-modal-title">
    <div class="modal">
        <h3 id="sess-modal-title">Add Session</h3>
        <form method="POST" action="<?= BASE_URL ?>/api/sessions/save.php">
            <input type="hidden" name="event_id" value="<?= $event_id ?>">
            <input type="hidden" name="id" id="f-id" value="">
            <div class="form-group">
                <label for="f-name">Session name</label>
                <input type="text" name="session_name" id="f-name" class="form-input" required placeholder="e.g. Seminar 1: Cloud Basics">
            </div>
            <div class="form-grid">
                <div class="form-group"><label for="f-speaker">Speaker <span class="optional">(optional)</span></label><input type="text" name="speaker_name" id="f-speaker" class="form-input" placeholder="e.g. Juan Cruz"></div>
                <div class="form-group"><label for="f-role">Speaker's role <span class="optional">(optional)</span></label><input type="text" name="speaker_role" id="f-role" class="form-input" placeholder="e.g. AWS Architect"></div>
            </div>
            <div class="form-grid">
                <div class="form-group"><label for="f-date">Date <span class="optional">(optional)</span></label><input type="date" name="session_date" id="f-date" class="form-input" min="2000-01-01" max="2100-12-31"></div>
                <div class="form-group"><label for="f-loc">Room <span class="optional">(optional)</span></label><input type="text" name="location" id="f-loc" class="form-input" placeholder="e.g. Function Room A"></div>
            </div>
            <div class="form-grid">
                <div class="form-group"><label for="f-start">Starts <span class="optional">(optional)</span></label><input type="time" name="start_time" id="f-start" class="form-input"></div>
                <div class="form-group"><label for="f-end">Ends <span class="optional">(optional)</span></label><input type="time" name="end_time" id="f-end" class="form-input"></div>
            </div>
            <div class="form-group">
                <label for="f-desc">Description <span class="optional">(optional)</span></label>
                <textarea name="description" id="f-desc" class="form-input" rows="2"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeSession()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save session</button>
            </div>
        </form>
    </div>
</div>
<script>
function openSession(s) {
    document.getElementById('sess-modal-title').textContent = s ? 'Edit Session' : 'Add Session';
    document.getElementById('f-id').value      = s ? s.id : '';
    document.getElementById('f-name').value    = s ? (s.session_name || '') : '';
    document.getElementById('f-speaker').value = s ? (s.speaker_name || '') : '';
    document.getElementById('f-role').value    = s ? (s.speaker_role || '') : '';
    document.getElementById('f-date').value    = s ? (s.session_date || '') : <?= json_encode($current_event['event_date'] ?? '') ?>;
    document.getElementById('f-start').value   = s ? (s.start_time || '') : '';
    document.getElementById('f-end').value     = s ? (s.end_time || '') : '';
    document.getElementById('f-loc').value     = s ? (s.location || '') : '';
    document.getElementById('f-desc').value    = s ? (s.description || '') : '';
    document.getElementById('sess-overlay').classList.add('show');
    document.getElementById('f-name').focus();
}
function closeSession() { document.getElementById('sess-overlay').classList.remove('show'); }
['f-start', 'f-end'].forEach(id => document.getElementById(id).addEventListener('input', () => {
    const start = document.getElementById('f-start').value, end = document.getElementById('f-end');
    end.setCustomValidity(start && end.value && end.value <= start ? 'The end time must be after the start time.' : '');
}));
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSession(); });
</script>
<?php endif; ?>
</body>
</html>
