<?php

require_once __DIR__ . '/../../core/bootstrap.php';
requireLogin();

$event_id = (int)($_GET['event_id'] ?? 0);
if (!$event_id) {
    redirect(BASE_URL . '/pages/events/index.php', 'Select an event first.', 'error');
}

$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) {
    redirect(BASE_URL . '/pages/events/index.php', 'Event not found.', 'error');
}

$page_title = 'Scan QR Code';

$icon_check = icon('check-circle', ['class' => 'icon-svg icon-sm']);
$icon_xcircle = icon('x-circle', ['class' => 'icon-svg icon-sm']);
$icon_ticket = icon('ticket', ['class' => 'icon-svg icon-sm']);
$icon_camera = icon('camera', ['class' => 'icon-svg icon-sm']);
$icon_alert = icon('alert', ['class' => 'icon-svg icon-sm']);
$icon_printer = icon('printer', ['class' => 'icon-svg icon-sm']);
$icon_plus = icon('plus', ['class' => 'icon-svg icon-sm']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page_title ?> &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<style>
    .scanner-card { padding: 0; overflow: hidden; }
    /* Fits one screen: the camera box (4:3) narrows on short screens so the manual entry stays visible.
       236px = top bar + page padding + manual entry row. */
    .scan-wrap { max-width: min(600px, calc((100vh - 236px) * 4 / 3)); margin: 0 auto; }
    .scan-event { position: absolute; top: 12px; left: 12px; z-index: 4; max-width: calc(100% - 24px); display: inline-flex; align-items: center; gap: 6px; padding: 5px 10px; border-radius: 999px; background: rgba(46, 15, 38, .78); color: var(--white); font-size: 12px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    #qr-reader { width: 100%; border: none; background: var(--wine-darkest); }
    #qr-reader video { width: 100%; display: block; }
    #qr-reader__scan_region { min-height: 280px; }
    #qr-reader__dashboard { padding: 14px; background: var(--color-surface); }
    /* The result sits on top of the camera, where staff are already looking: no scrolling after a scan */
    .scan-stage { position: relative; min-height: 300px; background: var(--wine-darkest); }
    .result-area { position: absolute; left: 14px; right: 14px; bottom: 14px; z-index: 5; }
    .result-area:empty { display: none; }
    .result-card { background: var(--color-surface); border-radius: var(--radius-md); border-left: 5px solid; padding: 14px 16px; box-shadow: var(--shadow-lg); animation: result-in .18s ease-out; }
    .result-ok   { border-left-color: var(--success); }
    .result-warn { border-left-color: var(--warning); }
    .result-bad  { border-left-color: var(--danger); }
    .result-head { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 700; }
    .result-ok .result-head   { color: var(--success-text); }
    .result-warn .result-head { color: var(--warning-text); }
    .result-bad .result-head  { color: var(--danger-text); }
    .result-bad p { font-size: 13px; color: var(--color-text-muted); margin-top: 4px; }
    .attendee-info { display: flex; align-items: center; gap: 12px; margin-top: 12px; min-width: 0; }
    .attendee-info > div:last-child { min-width: 0; }
    .attendee-name { font-size: 17px; font-weight: 700; color: var(--color-text-strong); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .attendee-meta { color: var(--color-text-muted); font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .attendee-meta b { color: var(--color-text-strong); font-weight: 600; letter-spacing: .3px; }
    .action-buttons { display: flex; gap: 8px; margin-top: 12px; }
    .action-buttons .btn-sm { flex: 1; justify-content: center; }
    @keyframes result-in { from { opacity: 0; transform: translateY(8px); } }
    @media (prefers-reduced-motion: reduce) { .result-card { animation: none; } }
    .manual { padding: 12px 18px 16px; border-top: 1px solid var(--color-border); }
    .manual label { display: block; font-size: 13px; font-weight: 600; color: var(--color-text-strong); margin-bottom: 6px; }
    .manual-row { display: flex; gap: 8px; }
    .manual-row .input { flex: 1; }
    .camera-fallback { padding: 40px 20px; text-align: center; color: rgba(255, 255, 255, .85); display: flex; flex-direction: column; align-items: center; gap: 6px; background: var(--wine-darkest); }
    .camera-fallback .icon-svg { width: 36px; height: 36px; opacity: .7; margin-bottom: 6px; }
    .camera-fallback small { opacity: .7; }
    @media (max-width: 560px) { .manual-row { flex-direction: column; } }
</style>
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php $back_url = BASE_URL . '/pages/checkin/index.php?event_id=' . (int)$event_id; $back_label = 'Back to Check-In'; include __DIR__ . '/../../includes/header.php'; ?>


        <div class="scan-wrap">
            <div class="panel scanner-card">
                <div class="scan-stage">
                    <span class="scan-event"><?= icon('calendar', ['class' => 'icon-svg icon-sm']) ?> <?= htmlspecialchars($event['event_name']) ?></span>
                    <div id="qr-reader"></div>
                    <div class="result-area" id="result" aria-live="polite"></div>
                </div>

                <div class="manual">
                    <label for="manual-code">Or enter the code manually</label>
                    <div class="manual-row">
                        <input type="text" id="manual-code" class="input" placeholder="QR code or 9-digit attendee code" autocomplete="off">
                        <button class="btn-sm lg" onclick="manualLookup()"><?= icon('check', ['class' => 'icon-svg icon-sm']) ?> Look Up &amp; Check In</button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
const EVENT_ID = <?= $event_id ?>;
const API_URL = '<?= BASE_URL ?>/api/checkin/scan.php';
let isProcessing = false;
let html5QrCode;

const ICON_SUCCESS = `<?= addslashes($icon_check) ?>`;
const ICON_ERROR = `<?= addslashes($icon_xcircle) ?>`;
const ICON_TICKET = `<?= addslashes($icon_ticket) ?>`;
const ICON_ALERT = `<?= addslashes($icon_alert) ?>`;
const ICON_PRINTER = `<?= addslashes($icon_printer) ?>`;
const ICON_PLUS = `<?= addslashes($icon_plus) ?>`;
// Archived events take no walk-ins (walkin.php refuses them), so no shortcut there
const WALKIN_URL = <?= $event['status'] === 'archived' ? 'null' : "'" . BASE_URL . '/pages/checkin/walkin.php?event_id=' . $event_id . "'" ?>;
const BADGE_URL = '<?= BASE_URL ?>/pages/checkin/badge.php?attendee_id=';
let lastCode = '', lastAt = 0, clearTimer = null;

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function showResult(html) {
    document.getElementById('result').innerHTML = html;
}

async function processScan(code) {
    code = String(code || '').trim();
    if (isProcessing || !code) return;
    // The camera keeps reading a QR while it is held up; ignore the same code for a few seconds
    if (code === lastCode && Date.now() - lastAt < 4000) return;
    lastCode = code;
    lastAt = Date.now();
    isProcessing = true;
    clearTimeout(clearTimer);

    try {
        const formData = new FormData();
        formData.append('event_id', EVENT_ID);
        formData.append('code', code.trim());

        const res = await fetch(API_URL, {
            method: 'POST',
            body: formData,
            cache: 'no-cache'
        });
        const data = await res.json();

        if (data.success) {
            const a = data.attendee;
            const already = !!data.already_checked_in;
            const initials = a.full_name.split(' ').map(n => n[0] || '').join('').substring(0, 2).toUpperCase();

            showResult(`
                <div class="result-card ${already ? 'result-warn' : 'result-ok'}">
                    <div class="result-head">${already ? ICON_ALERT : ICON_SUCCESS} ${escapeHtml(data.message)}</div>
                    <div class="attendee-info">
                        <div class="avatar lg">${escapeHtml(initials)}</div>
                        <div>
                            <div class="attendee-name">${escapeHtml(a.full_name)}</div>
                            <div class="attendee-meta">${escapeHtml(a.company)} &middot; <b>${escapeHtml(a.attendee_code)}</b></div>
                        </div>
                    </div>
                    <div class="action-buttons">
                        <a class="btn-sm light" href="${BADGE_URL}${encodeURIComponent(a.id)}">${ICON_PRINTER} Print Badge</a>
                        <button class="btn-sm" onclick="resetScanner()">${ICON_TICKET} Scan Next</button>
                    </div>
                </div>
            `);
            // A fresh check-in clears itself so the line keeps moving; a warning stays until dismissed
            if (!already) clearTimer = setTimeout(resetScanner, 6000);

            new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2Ftius0x9LmW3Uz2tr2BwqJ9DqJev/2q3+AABxUUV5dX2FhIiL+gIA/ak5DRlJOUX5qZGFqYm1qXlJjfoKA/gFhYV5hYVhiU1JSXlJNYVFLQ0RFT0ZLTU5PQU5KQ05MTkZOT09PT1BQRU5KQ05MTE5OT09PUU5KQ05MTkZOT09PVX9AQEJDRU5KQ05MTkZOT09QVVBQkxDQ0RFTU5KQ05MTkZOT09QUFBRkxDQ0RFTU5KQ05MTkZOT09').play().catch(() => {});
        } else {
            showResult(`
                <div class="result-card result-bad">
                    <div class="result-head">${ICON_ERROR} Not found</div>
                    <p>${escapeHtml(data.message)}</p>
                    <div class="action-buttons">
                        <button class="btn-sm light" onclick="resetScanner()">Try Again</button>
                        ${WALKIN_URL ? `<a class="btn-sm" href="${WALKIN_URL}">${ICON_PLUS} Register Walk-in</a>` : ''}
                    </div>
                </div>
            `);
        }
    } catch (e) {
        showResult(`<div class="result-card result-bad"><div class="result-head">${ICON_ERROR} Connection problem</div><p>Check the connection and scan again.</p><div class="action-buttons"><button class="btn-sm light" onclick="resetScanner()">Close</button></div></div>`);
    } finally {
        setTimeout(() => { isProcessing = false; }, 1500);
    }
}

function resetScanner() {
    clearTimeout(clearTimer);
    document.getElementById('result').innerHTML = '';
    document.getElementById('manual-code').value = '';
    isProcessing = false;
}

function manualLookup() {
    const code = document.getElementById('manual-code').value.trim();
    if (code) {
        processScan(code);
    }
}

document.getElementById('manual-code').addEventListener('keypress', e => {
    if (e.key === 'Enter') manualLookup();
});

window.addEventListener('DOMContentLoaded', () => {
    html5QrCode = new Html5Qrcode("qr-reader");
    const config = { fps: 10, qrbox: { width: 250, height: 250 } };

    html5QrCode.start(
        { facingMode: "environment" },
        config,
        (decodedText) => {

            processScan(decodedText);
        },
        () => {}
    ).catch(err => {
        console.log('Camera error:', err);
        document.getElementById('qr-reader').innerHTML = `
            <div class="camera-fallback">
                <svg class="icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <p>Camera not available or permission denied</p>
                <small>Use the manual code entry below</small>
            </div>`;
    });
});
</script>
</body>
</html>
