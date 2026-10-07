<?php
require_once __DIR__ . '/../../core/bootstrap.php';
requireLogin();
$event_id = (int)($_GET['event_id'] ?? 0);
if (!$event_id) {
    $latest = $conn->query("SELECT id FROM events WHERE status IN ('upcoming','ongoing') ORDER BY event_date ASC LIMIT 1");
    if ($latest->num_rows > 0) $event_id = $latest->fetch_assoc()['id'];
}
if (!$event_id) {
    redirect(BASE_URL . '/pages/events/index.php', 'No active event found.', 'error');
}
$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stats = getEventStats($event_id);
$company_stats = [];
$cs_result = $conn->query("SELECT company, COUNT(*) as total, SUM(CASE WHEN status='checked_in' THEN 1 ELSE 0 END) as checked FROM attendees WHERE event_id=$event_id GROUP BY company ORDER BY company");
while ($r = $cs_result->fetch_assoc()) $company_stats[] = $r;
$recent = $conn->query("SELECT * FROM attendees WHERE event_id=$event_id AND status='checked_in' ORDER BY check_in_time DESC LIMIT 10");
$page_title = 'Check-In';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $page_title ?> &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<style>
    .checkin-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .action-card { display: block; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 22px 20px; text-align: center; transition: border-color var(--dur-fast); }
    .action-card:hover { border-color: var(--magenta); }
    .action-card .icon-circle { width: 52px; height: 52px; border-radius: 50%; background: var(--color-primary-soft); color: var(--wine-mid); display: grid; place-items: center; margin: 0 auto 12px; }
    .action-card .icon-circle .icon-svg { width: 24px; height: 24px; }
    .action-label { font-size: 15px; font-weight: 700; color: var(--color-text-strong); }
    .action-desc { font-size: 13px; color: var(--color-text-muted); margin-top: 2px; }
    .company-row .progress { width: 120px; margin-top: 6px; }
    .company-row .count { text-align: right; }
    .company-row .count b { font-variant-numeric: tabular-nums; }
    .recent-item > div:not(.avatar) { flex: 1; min-width: 0; }
</style>
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php $back_url = BASE_URL . '/pages/events/view.php?id=' . (int)$event_id; $back_label = 'Back to event'; include __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-intro">
            <div>
                <h2><?= htmlspecialchars($event['event_name']) ?></h2>
                <p><?= icon('calendar', ['class' => 'icon-svg']) ?> <?= formatDate($event['event_date']) ?> at <?= htmlspecialchars($event['location']) ?></p>
            </div>
            <span class="pill-ok" role="status">Live &middot; updates every 2s</span>
        </div>

        <div class="checkin-actions">
            <a href="<?= BASE_URL ?>/pages/checkin/scan.php?event_id=<?= $event_id ?>" class="action-card">
                <div class="icon-circle"><?= icon('camera', ['class' => 'icon-svg']) ?></div>
                <div class="action-label">Scan QR Code</div>
                <div class="action-desc">Use the camera to scan an attendee QR</div>
            </a>
            <a href="<?= BASE_URL ?>/pages/checkin/walkin.php?event_id=<?= $event_id ?>" class="action-card">
                <div class="icon-circle"><?= icon('walk', ['class' => 'icon-svg']) ?></div>
                <div class="action-label">Walk-in Entry</div>
                <div class="action-desc">Register an unlisted attendee</div>
            </a>
            <a href="<?= BASE_URL ?>/pages/attendees/index.php?event_id=<?= $event_id ?>" class="action-card">
                <div class="icon-circle"><?= icon('list', ['class' => 'icon-svg']) ?></div>
                <div class="action-label">Attendee List</div>
                <div class="action-desc">Search and manage all attendees</div>
            </a>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-label">Total Registered</div><div class="stat-value" id="stat-total"><?= $stats['total'] ?></div></div>
            <div class="stat-card ok"><div class="stat-label">Checked In</div><div class="stat-value" id="stat-checkedin"><?= $stats['checked_in'] ?></div></div>
            <div class="stat-card warn"><div class="stat-label">Pending</div><div class="stat-value" id="stat-pending"><?= $stats['not_yet'] ?></div></div>
            <div class="stat-card accent"><div class="stat-label">Progress</div><div class="stat-value" id="stat-percentage"><?= $stats['percentage'] ?>%</div></div>
        </div>

        <div class="panel-grid">
            <div class="panel">
                <h3><span class="panel-title"><?= icon('building', ['class' => 'icon-svg']) ?> By Company</span></h3>
                <div id="company-list">
                <?php if (empty($company_stats)): ?>
                    <p class="muted">No attendees yet.</p>
                <?php else: foreach ($company_stats as $cs): $pct = $cs['total'] > 0 ? round(($cs['checked']/$cs['total'])*100) : 0; ?>
                    <div class="list-row company-row">
                        <div>
                            <b><?= htmlspecialchars($cs['company']) ?></b>
                            <div class="progress"><i style="width: <?= $pct ?>%"></i></div>
                        </div>
                        <div class="count"><b><?= $cs['checked'] ?>/<?= $cs['total'] ?></b><small><?= $pct ?>% in</small></div>
                    </div>
                <?php endforeach; endif; ?>
                </div>
            </div>
            <div class="panel">
                <h3><span class="panel-title"><?= icon('zap', ['class' => 'icon-svg']) ?> Recent Check-ins</span></h3>
                <div id="recent-list">
                <?php if ($recent->num_rows === 0): ?>
                    <p class="muted">No check-ins yet.</p>
                <?php else: while ($r = $recent->fetch_assoc()): $initials = strtoupper(substr($r['full_name'], 0, 2)); ?>
                    <div class="list-row recent-item" data-id="<?= $r['id'] ?>">
                        <div class="avatar"><?= $initials ?></div>
                        <div>
                            <b><?= htmlspecialchars($r['full_name']) ?></b>
                            <small><?= htmlspecialchars($r['company']) ?> &middot; <?= date('h:i A', strtotime($r['check_in_time'])) ?></small>
                        </div>
                    </div>
                <?php endwhile; endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
const POLL_EVENT_ID = <?= $event_id ?>;
let pollSince = '<?= date('Y-m-d H:i:s', time() - 5) ?>';
let pollInFlight = false;
function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}
async function pollCheckins() {
    if (pollInFlight) return;
    pollInFlight = true;
    try {
        const res = await fetch(`<?= BASE_URL ?>/api/attendees/poll.php?event_id=${POLL_EVENT_ID}&since=${encodeURIComponent(pollSince)}`);
        const data = await res.json();
        document.getElementById('stat-total').textContent = data.stats.total;
        document.getElementById('stat-checkedin').textContent = data.stats.checked_in;
        document.getElementById('stat-pending').textContent = data.stats.not_yet;
        document.getElementById('stat-percentage').textContent = data.stats.percentage + '%';
        const companyList = document.getElementById('company-list');
        if (data.company_stats.length) {
            companyList.innerHTML = data.company_stats.map(cs => {
                const pct = cs.total > 0 ? Math.round((cs.checked / cs.total) * 100) : 0;
                return `<div class="list-row company-row">
                    <div>
                        <b>${escapeHtml(cs.company)}</b>
                        <div class="progress"><i style="width: ${pct}%"></i></div>
                    </div>
                    <div class="count"><b>${cs.checked}/${cs.total}</b><small>${pct}% in</small></div>
                </div>`;
            }).join('');
        }
        const recentList = document.getElementById('recent-list');
        const newCheckins = data.attendees.filter(a => a.status === 'checked_in' && a.check_in_time);
        newCheckins.forEach(a => {
            if (recentList.querySelector(`[data-id="${a.id}"]`)) return;
            const noData = recentList.querySelector('p');
            if (noData) noData.remove();
            const initials = (a.full_name || '??').substring(0, 2).toUpperCase();
            const time = new Date(a.check_in_time.replace(' ', 'T')).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
            const div = document.createElement('div');
            div.className = 'list-row recent-item';
            div.dataset.id = a.id;
            div.innerHTML = `<div class="avatar">${escapeHtml(initials)}</div>
                <div>
                    <b>${escapeHtml(a.full_name)}</b>
                    <small>${escapeHtml(a.company)} &middot; ${time}</small>
                </div>`;
            recentList.prepend(div);
        });
        [...recentList.querySelectorAll('.recent-item')].slice(10).forEach(el => el.remove());
        pollSince = data.server_time;
    } catch (err) {
        console.error('Poll failed:', err);
    } finally {
        pollInFlight = false;
    }
}
setInterval(pollCheckins, 2000);
</script>
</body>
</html>
