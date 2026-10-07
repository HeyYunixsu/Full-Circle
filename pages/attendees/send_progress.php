<?php

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../includes/icons.php';
requireLogin();

$event_id = (int)($_GET['event_id'] ?? 0);
$total    = (int)($_GET['total'] ?? 0);

if (!$event_id) {
    redirect(BASE_URL . '/pages/events/index.php', 'Invalid event.', 'error');
}

$stmt = $conn->prepare("SELECT event_name FROM events WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) {
    redirect(BASE_URL . '/pages/events/index.php', 'Event not found.', 'error');
}

$page_title = 'Sending Emails';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> &mdash; <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
    <style>
        .sp-head { display: flex; align-items: center; gap: 14px; margin-bottom: 18px; }
        .sp-icon { width: 46px; height: 46px; border-radius: var(--radius-md); background: var(--color-primary-soft); color: var(--wine-mid); display: grid; place-items: center; flex-shrink: 0; }
        .sp-head h3 { margin: 0; }
        .sp-head p { font-size: 13px; color: var(--color-text-muted); }
        .sp-percent { display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; color: var(--color-text-muted); margin: 8px 0 20px; font-variant-numeric: tabular-nums; }
        .sp-stats { grid-template-columns: repeat(3, 1fr); }
        .sp-stats .stat-card { text-align: center; }
        .stat-card.pulse { animation: sp-pulse .45s ease; }
        @keyframes sp-pulse { 0% { transform: scale(1); } 35% { transform: scale(1.04); } 100% { transform: scale(1); } }
        .status-msg { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 14px; background: var(--color-surface-alt); border-radius: var(--radius-md); font-size: 14px; font-weight: 500; color: var(--color-text-muted); }
        .done-banner, .error-banner { display: none; }
        .done-banner.show, .error-banner.show { display: flex; }
        .action-row { display: flex; justify-content: center; margin-top: 20px; }
        #btnBack { display: none; }
        #btnBack.show { display: inline-flex; }
    </style>
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/../../includes/header.php'; ?>

        <div class="narrow">
            <div class="panel">
                <div class="sp-head">
                    <div class="sp-icon"><?= icon('mail', ['class' => 'icon-svg icon-md']) ?></div>
                    <div>
                        <h3>Sending QR Code Emails</h3>
                        <p><?= htmlspecialchars($event['event_name']) ?></p>
                    </div>
                </div>

                <div class="alert alert-warning">
                    <?= icon('alert', ['class' => 'icon-svg icon-sm']) ?>
                    <span>Keep this page open until it finishes. Closing it pauses sending; you can resume later.</span>
                </div>

                <div class="progress lg" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="progressBar"><i id="progressFill" style="width:0%"></i></div>
                <div class="sp-percent"><span id="progressStep">Starting</span><span id="progressPercent">0%</span></div>

                <div class="stats-grid sp-stats">
                    <div class="stat-card">
                        <div class="stat-label">Total</div>
                        <div class="stat-value" id="statTotal"><?= $total ?></div>
                    </div>
                    <div class="stat-card" id="sentCard">
                        <div class="stat-label">Sent</div>
                        <div class="stat-value" id="statSent">0</div>
                    </div>
                    <div class="stat-card" id="failedCard">
                        <div class="stat-label">Failed</div>
                        <div class="stat-value" id="statFailed">0</div>
                    </div>
                </div>

                <div class="status-msg" id="statusMsg" aria-live="polite">
                    <span class="spinner"></span>
                    <span>Starting...</span>
                </div>

                <div class="alert alert-success done-banner" id="doneBanner">
                    <?= icon('check-circle', ['class' => 'icon-svg icon-sm']) ?>
                    <span>All done. Emails have been sent.</span>
                </div>

                <div class="alert alert-error error-banner" id="errorBanner"></div>

                <div class="action-row">
                    <a href="<?= BASE_URL ?>/pages/attendees/index.php?event_id=<?= $event_id ?>" class="btn-sm light lg" id="btnBack">
                        <?= icon('arrow-left', ['class' => 'icon-svg icon-sm']) ?>
                        <span>Back to Attendees</span>
                    </a>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
(function() {
    const eventId   = <?= $event_id ?>;
    const total     = <?= $total ?>;
    const batchSize = 25;

    let totalSent = 0;
    let totalFailed = 0;
    let consecutiveErrors = 0;

    const barEl     = document.getElementById('progressBar');
    const fillEl    = document.getElementById('progressFill');
    const pctEl     = document.getElementById('progressPercent');
    const stepEl    = document.getElementById('progressStep');
    const sentEl    = document.getElementById('statSent');
    const failedEl  = document.getElementById('statFailed');
    const sentCard  = document.getElementById('sentCard');
    const failCard  = document.getElementById('failedCard');
    const statusEl  = document.getElementById('statusMsg');
    const doneEl    = document.getElementById('doneBanner');
    const errEl     = document.getElementById('errorBanner');
    const btnBackEl = document.getElementById('btnBack');

    function animateValue(el, to, duration, suffix) {
        suffix = suffix || '';
        const from = parseInt(el.textContent, 10) || 0;
        if (from === to) { el.textContent = to + suffix; return; }
        const start = performance.now();
        function tick(now) {
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 3);
            el.textContent = Math.round(from + (to - from) * eased) + suffix;
            if (t < 1) requestAnimationFrame(tick);
            else el.textContent = to + suffix;
        }
        requestAnimationFrame(tick);
    }

    function pulseCard(card) {
        card.classList.remove('pulse');
        void card.offsetWidth;
        card.classList.add('pulse');
    }

    function updateUI(remaining) {
        const done = total - remaining;
        const pct  = total > 0 ? Math.min(100, (done / total) * 100) : 100;
        fillEl.style.width = pct.toFixed(1) + '%';
        barEl.setAttribute('aria-valuenow', Math.round(pct));
        animateValue(pctEl, Math.round(pct), 400, '%');
        if (parseInt(sentEl.textContent, 10) !== totalSent) {
            animateValue(sentEl, totalSent, 350);
            pulseCard(sentCard);
        }
        if (parseInt(failedEl.textContent, 10) !== totalFailed) {
            animateValue(failedEl, totalFailed, 350);
            pulseCard(failCard);
        }
        if (totalSent > 0)   sentCard.classList.add('ok');
        if (totalFailed > 0) failCard.classList.add('warn');
        stepEl.textContent = done + ' of ' + total;
        statusEl.innerHTML = '<span class="spinner"></span><span>Processing... ' + done + ' of ' + total + '</span>';
    }

    function finish() {
        fillEl.style.width = '100%';
        barEl.setAttribute('aria-valuenow', 100);
        animateValue(pctEl, 100, 300, '%');
        animateValue(sentEl, totalSent, 300);
        animateValue(failedEl, totalFailed, 300);
        stepEl.textContent = 'Done';
        statusEl.style.display = 'none';
        doneEl.classList.add('show');
        btnBackEl.classList.add('show');
    }

    function showError(msg) {
        statusEl.style.display = 'none';
        errEl.innerHTML = '<strong>Error:</strong>&nbsp;' + msg + '&nbsp;&mdash;&nbsp;<a href="javascript:location.reload()" style="text-decoration:underline;font-weight:600;">Retry</a>';
        errEl.classList.add('show');
        btnBackEl.classList.add('show');
    }

    function processBatch() {
        const url = '<?= BASE_URL ?>/api/notifications/process_queue.php?event_id=' + eventId + '&batch=' + batchSize;

        fetch(url, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    consecutiveErrors++;
                    if (consecutiveErrors >= 3) {
                        showError(data.error);
                        return;
                    }
                    setTimeout(processBatch, 2000);
                    return;
                }

                consecutiveErrors = 0;
                totalSent   += (data.sent   || 0);
                totalFailed += (data.failed || 0);

                updateUI(data.remaining);

                if (data.done) {
                    finish();
                } else {
                    setTimeout(processBatch, 200);
                }
            })
            .catch(err => {
                consecutiveErrors++;
                if (consecutiveErrors >= 3) {
                    showError('Network error. ' + err.message);
                    return;
                }
                setTimeout(processBatch, 2000);
            });
    }

    processBatch();
})();
</script>

</body>
</html>
