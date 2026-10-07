<?php

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../includes/qrcode.php';
requireLogin();

$attendee_id = (int)($_GET['attendee_id'] ?? 0);

$stmt = $conn->prepare("SELECT a.*, e.event_name FROM attendees a JOIN events e ON a.event_id = e.id WHERE a.id = ?");
$stmt->bind_param("i", $attendee_id);
$stmt->execute();
$attendee = $stmt->get_result()->fetch_assoc();

if (!$attendee) {
    redirect(BASE_URL . '/pages/attendees/index.php', 'Attendee not found.', 'error');
}

if (isset($_GET['print']) && $_GET['print'] == '1') {
    $upd = $conn->prepare("UPDATE attendees SET badge_printed = TRUE WHERE id = ?");
    $upd->bind_param("i", $attendee_id);
    $upd->execute();
    logActivity('Badge Printed', $attendee['full_name']);
}

// Back goes where the user came from: the attendee list, the attendee's QR page, or (default) the scanner
$from  = $_GET['from'] ?? '';
$backs = [
    'attendees' => [BASE_URL . '/pages/attendees/index.php?event_id=' . (int)$attendee['event_id'], 'Back to Attendees'],
    'qr'        => [BASE_URL . '/pages/attendees/view_qr.php?id=' . $attendee_id, 'Back to QR Code'],
];
if (!isset($backs[$from])) $from = '';
[$back_url, $back_label] = $backs[$from] ?? [BASE_URL . '/pages/checkin/scan.php?event_id=' . (int)$attendee['event_id'], 'Back to Scan'];

$qr_url = getQRCodeImageUrl($attendee['qr_code'], $attendee['qr_image_path']);
$page_title = 'Badge Preview';
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $page_title ?> &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<style>
    .badge-screen { max-width: 600px; margin: 0 auto; }
    .badge-actions { display: flex; gap: 12px; margin-top: 20px; }
    .badge-actions .btn-sm { flex: 1; }
    .badge-stage { overflow-x: auto; padding: 4px 0; }

    @media print {
        body { background: white; margin: 0; padding: 0; }
        .sidebar, .page-header, .no-print, .badge-actions, .badge-picker { display: none !important; }
        .main-content { margin: 0 !important; padding: 0 !important; }
        .panel { border: none; padding: 0; }
        .badge { box-shadow: none !important; margin: 0 auto !important; page-break-inside: avoid; }
    }

    /* The printed badge itself has a fixed physical size, so the pixel values below are intentional */
    .badge {
        width: 360px; height: 220px;
        background: var(--white); border-radius: 12px; border: 1px solid var(--color-border);
        margin: 24px auto; display: flex; overflow: hidden; position: relative;
    }
    .badge-stripe { width: 12px; background: linear-gradient(180deg, var(--pink-bright), var(--wine-mid)); }
    .badge-content { flex: 1; padding: 20px; display: flex; flex-direction: column; justify-content: space-between; }
    .badge-top { display: flex; justify-content: space-between; align-items: flex-start; }
    .badge-code { font-size: 11px; color: var(--color-text-muted); font-family: monospace; font-weight: 600; }
    .badge-event { font-size: 10px; color: var(--wine-mid); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; max-width: 150px; text-align: right; line-height: 1.3; }
    .badge-name { font-size: 24px; font-weight: 700; color: var(--color-text-strong); line-height: 1.1; margin: 12px 0 6px; letter-spacing: -.4px; }
    .badge-company { font-size: 14px; color: var(--color-text-muted); font-weight: 600; }
    .badge-qr-section { display: flex; align-items: flex-end; justify-content: space-between; margin-top: 12px; }
    .badge-qr { width: 70px; height: 70px; border: 1px solid var(--color-border); padding: 3px; border-radius: 4px; background: var(--white); }
    .badge-footer { font-size: 9px; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: .8px; }
</style>
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <div class="no-print"><?php include __DIR__ . '/../../includes/sidebar.php'; ?></div>

    <main class="main-content">
        <div class="no-print"><?php include __DIR__ . '/../../includes/header.php'; ?></div>

        <div class="badge-screen">
            <div class="no-print">
                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] ?>"><?= icon($flash['type'] === 'success' ? 'check' : 'alert', ['class' => 'icon-svg icon-sm']) ?> <?= $flash['message'] ?></div>
                <?php endif; ?>
                <div class="page-intro">
                    <div>
                        <h2>Badge Preview</h2>
                        <p>Review the badge before printing</p>
                    </div>
                    <?php if ($attendee['badge_printed']): ?><span class="pill-info"><?= icon('printer', ['class' => 'icon-svg icon-sm']) ?> Already printed</span><?php endif; ?>
                </div>
            </div>

            <div class="panel">
                <div class="badge-picker no-print form-group">
                    <label for="tplPicker">Badge design</label>
                    <select id="tplPicker" class="form-input" onchange="pickTemplate()">
                        <option value="">Default badge</option>
                    </select>
                </div>

                <div class="badge-stage">
                    <div class="badge" id="defaultBadge">
                        <div class="badge-stripe"></div>
                        <div class="badge-content">
                            <div class="badge-top">
                                <div>
                                    <div class="badge-code"><?= htmlspecialchars($attendee['attendee_code']) ?></div>
                                </div>
                                <div class="badge-event"><?= htmlspecialchars($attendee['event_name']) ?></div>
                            </div>

                            <div>
                                <div class="badge-name"><?= htmlspecialchars($attendee['full_name']) ?></div>
                                <div class="badge-company"><?= htmlspecialchars($attendee['company']) ?></div>
                            </div>

                            <div class="badge-qr-section">
                                <div class="badge-footer">
                                    <?= $attendee['registration_type'] === 'walk-in' ? 'Walk-in' : 'Pre-registered' ?>
                                </div>
                                <img src="<?= $qr_url ?>" class="badge-qr" alt="QR code for <?= htmlspecialchars($attendee['attendee_code']) ?>">
                            </div>
                        </div>
                    </div>

                    <div id="customBadge" style="display:none;margin:24px auto;"></div>
                </div>

                <div class="badge-actions no-print">
                    <button class="btn-sm lg" onclick="printBadge()">
                        <?= icon('printer', ['class' => 'icon-svg icon-sm']) ?>
                        Print Badge
                    </button>
                    <a href="<?= BASE_URL ?>/pages/checkin/scan.php?event_id=<?= $attendee['event_id'] ?>" class="btn-sm light lg">
                        <?= icon('camera', ['class' => 'icon-svg icon-sm']) ?>
                        Scan Next
                    </a>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
const ATTENDEE = {
    name:        <?= json_encode($attendee['full_name']) ?>,
    company:     <?= json_encode($attendee['company']) ?>,
    designation: <?= json_encode($attendee['designation'] ?? '') ?>,
    code:        <?= json_encode($attendee['attendee_code']) ?>,
    qr_url:      <?= json_encode($qr_url) ?>
};
let TEMPLATES = [];

async function loadTemplates() {
    try {
        const res = await fetch('<?= BASE_URL ?>/api/badges/list_templates.php');
        const d = await res.json();
        if (!d.success) return;
        TEMPLATES = d.templates;
        const sel = document.getElementById('tplPicker');
        d.templates.forEach(t => {
            const o = document.createElement('option');
            o.value = t.id;
            o.textContent = (t.is_favorite ? '★ ' : '') + t.name;
            sel.appendChild(o);
        });
    } catch (e) {}
}

function pickTemplate() {
    const id = parseInt(document.getElementById('tplPicker').value) || 0;
    const def = document.getElementById('defaultBadge');
    const box = document.getElementById('customBadge');
    if (!id) {
        def.style.display = 'flex';
        box.style.display = 'none';
        box.innerHTML = '';
        return;
    }
    const tpl = TEMPLATES.find(t => t.id === id);
    if (!tpl || !tpl.layout) return;
    def.style.display = 'none';
    box.style.display = 'block';
    box.innerHTML = renderTemplate(tpl.layout);
}

function renderTemplate(layout) {
    const w = layout.size?.w || 360;
    const h = layout.size?.h || 225;
    const bg = layout.bg?.color || '#ffffff';
    let html = `<div class="badge print-target" style="width:${w}px;height:${h}px;background:${bg};border-radius:12px;position:relative;overflow:hidden;margin:0 auto;">`;
    (layout.elements || []).forEach(el => {
        const map = { name: ATTENDEE.name, company: ATTENDEE.company, designation: ATTENDEE.designation, code: ATTENDEE.code };
        if (el.type === 'text') {
            const txt = el._field ? (map[el._field] || el.content) : el.content;
            html += `<div style="position:absolute;left:${el.x}px;top:${el.y}px;font-family:'${el.fontFamily||'sans-serif'}',sans-serif;font-size:${el.fontSize}px;font-weight:${el.fontWeight||400};color:${el.color};text-align:${el.align||'left'};white-space:nowrap;">${escapeHtml(txt)}</div>`;
        } else if (el.type === 'qr') {
            html += `<div style="position:absolute;left:${el.x}px;top:${el.y}px;width:${el.size}px;height:${el.size}px;background:#fff;"><img src="${ATTENDEE.qr_url}" style="width:100%;height:100%;"></div>`;
        } else if (el.type === 'img') {
            html += `<img src="${el.src}" style="position:absolute;left:${el.x}px;top:${el.y}px;width:${el.w}px;height:${el.h}px;object-fit:contain;">`;
        } else if (el.type === 'rect' || el.type === 'line') {
            html += `<div style="position:absolute;left:${el.x}px;top:${el.y}px;width:${el.w}px;height:${el.h}px;background:${el.fill};opacity:${el.opacity??1};border-radius:${el.radius||0}px;"></div>`;
        }
    });
    html += `</div>`;
    return html;
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function printBadge() {
    window.print();
    setTimeout(() => {
        window.location.href = '?attendee_id=<?= $attendee_id ?>&print=1<?= $from ? '&from=' . $from : '' ?>';
    }, 1000);
}

loadTemplates();
</script>
</body>
</html>
