<?php

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../includes/qrcode.php';
requireLogin();

$attendee_id = (int)($_GET['attendee_id'] ?? 0);

$stmt = $conn->prepare("SELECT a.*, e.event_name, e.badge_template_id, e.status AS event_status FROM attendees a JOIN events e ON a.event_id = e.id WHERE a.id = ?");
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
$tpl = badgeTemplateFor($attendee['badge_template_id']);   // the event's design (or the favorite); null = standard badge
$can_edit = canEditAttendee($attendee['event_status']);    // fix a wrong name right before printing
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
    .badge-fix-toggle { display: flex; justify-content: center; }
    .badge-fix { margin-top: 8px; padding: 16px; border: 1px solid var(--color-border); border-radius: 12px; background: var(--color-bg); }
    .badge-fix .form-actions { margin-top: 4px; }
    .badge-fix-msg { font-size: 13px; margin: 0 0 12px; }
    .badge-fix-msg.is-error { color: var(--color-danger, #b42318); }

    @media print {
        body { background: white; margin: 0; padding: 0; }
        .sidebar, .page-header, .no-print, .badge-actions { display: none !important; }
        .main-content { margin: 0 !important; padding: 0 !important; }
        .panel { border: none; padding: 0; }
        .badge { box-shadow: none !important; margin: 0 auto !important; page-break-inside: avoid; }
        /* Print the design's background and colour blocks even when "Background graphics" is off in the print dialog */
        .badge, .badge *, .badge-render, .badge-render * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
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
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/mobile.css?v=<?= ASSET_VER ?>" media="(max-width: 768px)">
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <div class="no-print"><?php include __DIR__ . '/../../includes/sidebar.php'; ?></div>

    <main class="main-content">
        <div class="no-print"><?php include __DIR__ . '/../../includes/header.php'; ?></div>

        <div class="badge-screen">
            <div class="no-print">
                <div class="page-intro">
                    <div>
                        <h2>Badge Preview</h2>
                        <p>Review the badge before printing</p>
                    </div>
                    <?php if ($attendee['badge_printed']): ?><span class="pill-info"><?= icon('printer', ['class' => 'icon-svg icon-sm']) ?> Already printed</span><?php endif; ?>
                </div>
            </div>

            <div class="panel">
                <!-- The design is chosen per event (Create Event / event page), so every badge of the event matches -->
                <p class="muted no-print" style="font-size: 13px; margin: 0;">
                    Badge design: <b><?= $tpl ? htmlspecialchars($tpl['template_name']) : 'Standard badge' ?></b> &middot; set on the event page
                </p>

                <div class="badge-stage">
                    <div class="badge" id="defaultBadge"<?= $tpl ? ' style="display:none"' : '' ?>>
                        <div class="badge-stripe"></div>
                        <div class="badge-content">
                            <div class="badge-top">
                                <div>
                                    <div class="badge-code"><?= htmlspecialchars($attendee['attendee_code']) ?></div>
                                </div>
                                <div class="badge-event"><?= htmlspecialchars($attendee['event_name']) ?></div>
                            </div>

                            <div>
                                <div class="badge-name" id="stdName"><?= htmlspecialchars($attendee['full_name']) ?></div>
                                <div class="badge-company" id="stdCompany"><?= htmlspecialchars($attendee['company']) ?></div>
                            </div>

                            <div class="badge-qr-section">
                                <div class="badge-footer">
                                    <?= $attendee['registration_type'] === 'walk-in' ? 'Walk-in' : 'Pre-registered' ?>
                                </div>
                                <img src="<?= $qr_url ?>" class="badge-qr" alt="QR code for <?= htmlspecialchars($attendee['attendee_code']) ?>">
                            </div>
                        </div>
                    </div>

                    <div id="customBadge" data-template="<?= $tpl ? (int)$tpl['id'] : 0 ?>" style="<?= $tpl ? '' : 'display:none;' ?>margin:24px auto;"></div>
                </div>

                <?php if ($can_edit): ?>
                <div class="no-print">
                    <div class="badge-fix-toggle">
                        <button type="button" class="btn-sm light" id="fixToggle" aria-expanded="false" aria-controls="fixForm" onclick="toggleFix(true)">
                            <?= icon('edit', ['class' => 'icon-svg icon-sm']) ?> Wrong name? Edit details
                        </button>
                    </div>
                    <!-- Typing updates the badge above right away; Save fixes the attendee record everywhere (logged) -->
                    <form class="badge-fix" id="fixForm" hidden onsubmit="saveFix(event)" autocomplete="off">
                        <p class="badge-fix-msg" id="fixMsg" role="status">Changes show on the badge as you type. Save to fix the attendee list too.</p>
                        <div class="form-group">
                            <label for="fixName">Full name</label>
                            <input type="text" id="fixName" class="form-input" required value="<?= htmlspecialchars($attendee['full_name']) ?>">
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="fixCompany">Company</label>
                                <input type="text" id="fixCompany" class="form-input" required value="<?= htmlspecialchars($attendee['company']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="fixDesignation">Designation <span class="optional">(optional)</span></label>
                                <input type="text" id="fixDesignation" class="form-input" value="<?= htmlspecialchars($attendee['designation'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="toggleFix(false)">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="fixSave">Save changes</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

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

<script src="<?= BASE_URL ?>/assets/js/badge-render.js?v=<?= ASSET_VER ?>"></script>
<script>
const ATTENDEE = {
    name:        <?= json_encode($attendee['full_name']) ?>,
    company:     <?= json_encode($attendee['company']) ?>,
    designation: <?= json_encode($attendee['designation'] ?? '') ?>,
    code:        <?= json_encode($attendee['attendee_code']) ?>,
    event:       <?= json_encode($attendee['event_name']) ?>,
    qr_url:      <?= json_encode($qr_url) ?>
};
const LAYOUT = <?= $tpl ? json_encode(json_decode($tpl['layout_json'], true), JSON_HEX_TAG) : 'null' ?>;   // null = standard badge

// Draw the badge on screen from ATTENDEE (also after every edit)
function redraw() {
    if (LAYOUT) {
        const box = document.getElementById('customBadge');
        box.innerHTML = renderBadge(LAYOUT, ATTENDEE);
        fitBadgeText(box);   // long names shrink so they never run under the QR code
    }
    document.getElementById('stdName').textContent = ATTENDEE.name;
    document.getElementById('stdCompany').textContent = ATTENDEE.company;
}
// Draw the event's design straight away (again once the fonts load, so the fit is measured right)
redraw();
if (document.fonts) document.fonts.ready.then(redraw);

// ---- Wrong name? Edit details (only rendered for users allowed to edit this attendee)
const SAVED = { name: ATTENDEE.name, company: ATTENDEE.company, designation: ATTENDEE.designation };
const fixForm = document.getElementById('fixForm');
const fixVal = id => document.getElementById(id).value.trim();
const fixDirty = () => !!fixForm && !fixForm.hidden &&
    (fixVal('fixName') !== SAVED.name || fixVal('fixCompany') !== SAVED.company || fixVal('fixDesignation') !== (SAVED.designation || ''));

function toggleFix(open) {
    fixForm.hidden = !open;
    document.getElementById('fixToggle').setAttribute('aria-expanded', open);
    document.getElementById('fixToggle').style.display = open ? 'none' : '';
    if (open) { document.getElementById('fixName').focus(); return; }
    // Cancel: put the saved details back on the badge
    ['fixName', 'fixCompany', 'fixDesignation'].forEach((id, i) => document.getElementById(id).value = [SAVED.name, SAVED.company, SAVED.designation || ''][i]);
    Object.assign(ATTENDEE, SAVED);
    redraw();
}

if (fixForm) fixForm.addEventListener('input', () => {
    ATTENDEE.name = fixVal('fixName');
    ATTENDEE.company = fixVal('fixCompany');
    ATTENDEE.designation = fixVal('fixDesignation');
    redraw();
});

async function saveFix(e) {
    if (e) e.preventDefault();
    const msg = document.getElementById('fixMsg'), btn = document.getElementById('fixSave');
    if (!fixVal('fixName') || !fixVal('fixCompany')) {
        msg.textContent = 'Name and company cannot be empty.'; msg.classList.add('is-error');
        return false;
    }
    btn.disabled = true;
    try {
        const body = new FormData();
        body.append('id', <?= (int)$attendee_id ?>);
        body.append('full_name', fixVal('fixName'));
        body.append('company', fixVal('fixCompany'));
        body.append('designation', fixVal('fixDesignation'));
        const d = await (await fetch('<?= BASE_URL ?>/api/attendees/update.php', { method: 'POST', body })).json();
        if (!d.success) throw new Error(d.message);
        // Use what was stored (names are capitalized on save)
        Object.assign(SAVED, { name: d.attendee.full_name, company: d.attendee.company, designation: d.attendee.designation || '' });
        Object.assign(ATTENDEE, SAVED);
        redraw();
        toggleFix(false);
        msg.textContent = 'Changes show on the badge as you type. Save to fix the attendee list too.'; msg.classList.remove('is-error');
        appToast('Saved. ' + SAVED.name + ' is updated in the attendee list.', 'success');
        return true;
    } catch (err) {
        msg.textContent = err.message || 'Could not save. Check the connection and try again.'; msg.classList.add('is-error');
        return false;
    } finally {
        btn.disabled = false;
    }
}

async function printBadge() {
    // Unsaved edits are saved first, so the printed name and the attendee list always match
    if (fixDirty() && !(await saveFix())) return;
    window.print();
    setTimeout(() => {
        window.location.href = '?attendee_id=<?= $attendee_id ?>&print=1<?= $from ? '&from=' . $from : '' ?>';
    }, 1000);
}

</script>
</body>
</html>
