<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../includes/qrcode.php';
require_once __DIR__ . '/../../includes/sms.php';
requireLogin();
$event_id       = (int)($_GET['event_id'] ?? 0);
$filter_company = $_GET['company'] ?? '';
$filter_status  = $_GET['status'] ?? '';
$search         = $_GET['search'] ?? '';
$events_list = $conn->query("SELECT id, event_name FROM events ORDER BY event_date DESC");
if (!$event_id) {
    $latest = $conn->query("SELECT id FROM events ORDER BY event_date DESC LIMIT 1");
    if ($latest->num_rows > 0) $event_id = $latest->fetch_assoc()['id'];
}
$attendees       = [];
$companies_list  = [];
$current_event   = null;
if ($event_id) {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $current_event = $stmt->get_result()->fetch_assoc();
    $comp_result = $conn->query("SELECT DISTINCT company FROM attendees WHERE event_id = $event_id ORDER BY company");
    while ($r = $comp_result->fetch_assoc()) $companies_list[] = $r['company'];
    $sql    = "SELECT a.*,
                    (SELECT eq.status FROM email_queue eq
                     WHERE eq.attendee_id = a.id AND eq.email_type = 'qr_code'
                     ORDER BY eq.id DESC LIMIT 1) AS last_email_status,
                    (SELECT eq.error_message FROM email_queue eq
                     WHERE eq.attendee_id = a.id AND eq.email_type = 'qr_code'
                     ORDER BY eq.id DESC LIMIT 1) AS last_email_error
               FROM attendees a WHERE a.event_id = ?";
    $params = [$event_id];
    $types  = "i";
    if ($filter_company) { $sql .= " AND a.company = ?";           $params[] = $filter_company;          $types .= "s"; }
    if ($filter_status)  { $sql .= " AND a.status = ?";            $params[] = $filter_status;           $types .= "s"; }
    if ($search) {
        $sql .= " AND (a.full_name LIKE ? OR a.email LIKE ? OR a.attendee_code LIKE ? OR a.mobile_number LIKE ? OR a.designation LIKE ? OR a.company LIKE ?)";
        $sp = "%$search%";
        for ($i = 0; $i < 6; $i++) $params[] = $sp;
        $types .= "ssssss";
    }
    $sql .= " ORDER BY a.id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($r = $result->fetch_assoc()) $attendees[] = $r;
}
$sms_ready    = smsIsConfigured();
$event_locked = ($current_event['status'] ?? '') === 'archived';
$sms_pending  = 0;
if ($event_id && $sms_ready) {
    $r = $conn->query("SELECT COUNT(*) c FROM attendees
                       WHERE event_id = $event_id AND sms_sent = 0
                       AND mobile_number IS NOT NULL AND mobile_number != ''");
    if ($r) $sms_pending = (int)$r->fetch_assoc()['c'];
}
$flash      = getFlashMessage();
$page_title = 'Attendees';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $page_title ?> — <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<style>
    /* Event bar: which event, when and where, and how far check-in has got */
    .event-switch { display: flex; align-items: center; gap: 10px 20px; flex-wrap: wrap; margin-bottom: 16px; }
    .es-select { position: relative; display: inline-flex; align-items: center; }
    .es-select > .icon-svg { position: absolute; left: 12px; width: 16px; height: 16px; color: var(--magenta); pointer-events: none; z-index: 1; }
    .es-select select { -webkit-appearance: none; appearance: none; height: 42px; padding: 0 36px 0 38px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-surface) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%234a3d52' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 12px center / 12px; font-family: var(--font-display); font-size: 15px; font-weight: 600; color: var(--color-text-strong); max-width: 300px; text-overflow: ellipsis; cursor: pointer; transition: border-color var(--dur-fast), box-shadow var(--dur-fast); }
    .es-select select:hover { border-color: var(--gray-mid); }
    .es-select select:focus-visible { border-color: var(--magenta); box-shadow: var(--focus-ring); outline: none; }
    @supports (appearance: base-select) { .es-select select:not([multiple]) { appearance: base-select; background-image: none; padding-right: 12px; } }
    .es-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 13px; color: var(--color-text-muted); }
    .es-meta .sep { color: var(--color-border); }
    .es-progress { margin-left: auto; display: flex; align-items: center; gap: 12px; font-size: 13px; color: var(--color-text-muted); white-space: nowrap; }
    .es-progress strong { color: var(--color-text-strong); }
    .es-progress .progress { width: 150px; }
    /* Search and filters live inside the table card, right above the rows they filter */
    .filter-bar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .filter-bar select, .fb-search { height: 40px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background-color: var(--color-surface); font-size: 14px; color: var(--color-text); transition: border-color var(--dur-fast), box-shadow var(--dur-fast); }
    .filter-bar select { -webkit-appearance: none; appearance: none; padding: 0 34px 0 12px; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%234a3d52' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; background-size: 12px; cursor: pointer; max-width: 220px; text-overflow: ellipsis; }
    .filter-bar select:hover, .fb-search:hover { border-color: var(--gray-mid); }
    .filter-bar select:focus, .filter-bar select:focus-visible, .fb-search:focus-within { border-color: var(--magenta); box-shadow: var(--focus-ring); outline: none; }
    .fb-search { flex: 0 1 360px; min-width: 220px; display: flex; align-items: center; gap: 8px; padding: 0 12px; color: var(--color-text-faint); cursor: text; }
    .fb-search .icon-svg { width: 16px; height: 16px; flex-shrink: 0; }
    .fb-search input { border: none; background: transparent; flex: 1; min-width: 0; height: 100%; font-size: 14px; color: var(--color-text); padding: 0; }
    .fb-clear { font-size: 13px; font-weight: 600; color: var(--magenta); white-space: nowrap; padding: 0 6px; }
    .fb-clear:hover { text-decoration: underline; }
    .tc-filters { padding: 12px 18px; border-bottom: 1px solid var(--color-border); }
    .tc-filters select, .tc-filters .fb-search { height: 38px; }
    .tc-showing { margin-left: auto; font-size: 13px; color: var(--color-text-muted); white-space: nowrap; }
    .tc-title { font-family: var(--font-display); font-size: 15px; font-weight: 600; color: var(--color-text-strong); }
    .tc-nomatch { text-align: center; padding: 40px 16px !important; color: var(--color-text-muted); }
    .tc-nomatch a { color: var(--magenta); font-weight: 600; }
    /* No overflow clipping here, so the Send Invite menu can drop over the table; the scroll area rounds the bottom corners instead */
    .table-container { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); position: relative; }
    .table-actions { padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--color-border); flex-wrap: wrap; gap: 12px; }
    .table-stats { color: var(--color-text-muted); font-size: 14px; }
    .table-stats strong { color: var(--color-text-strong); }
    .btn-group { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .btn-export { background: linear-gradient(135deg,#7a2f5f,#5c2249); color: white; }
    .btn-email  { background: #7a2f5f; color: white; }
    .btn-scan   { background: linear-gradient(135deg,#5c2249,#2e0f26); color: white; }
    table { width: 100%; border-collapse: collapse; }
    .table-scroll { overflow-x: auto; border-radius: 0 0 var(--radius-lg) var(--radius-lg); }
    th { background: var(--off-white); padding: 10px 8px; white-space: nowrap; text-align: left; font-size: 12px; color: var(--purple-mid); text-transform: uppercase; letter-spacing: 0.5px; }
    td { padding: 9px 8px; border-bottom: 1px solid #eee; font-size: 13px; }
    #attendees-table tbody tr { background: var(--white); transition: background .15s; }
    #attendees-table tbody tr:hover { background: var(--off-white); }
    /* Two-line cells keep the table within a laptop screen, so no sideways scrolling at 1280px and up */
    #attendees-table td { vertical-align: middle; }
    #attendees-table .nowrap { white-space: nowrap; }
    #attendees-table .cell-main { display: block; color: var(--color-text-strong); font-weight: 500; }
    #attendees-table .cell-sub { display: block; font-size: 12px; color: var(--color-text-muted); margin-top: 2px; }
    #attendees-table .cell-person .cell-main, #attendees-table .cell-person .cell-sub { max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    #attendees-table .cell-company { max-width: 190px; }
    #attendees-table .email-status-note { white-space: normal; }
    /* Actions stay visible on the right when the table does scroll (phones) */
    #attendees-table th:last-child, #attendees-table td:last-child { position: sticky; right: 0; background: inherit; box-shadow: -1px 0 0 var(--color-border); }
    #attendees-table th:last-child { background: var(--off-white); }
    .status-pill { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; transition: all 0.4s; }
    .status-not_yet    { background: rgba(245,158,11,.15); color: #9a5a00; }
    .status-checked_in { background: rgba(16,185,129,.15); color: #1b7a4b; }
    .row-actions { display: flex; gap: 4px; }
    .row-btn { width: 28px; height: 28px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; background: var(--off-white); cursor: pointer; transition: transform .15s ease, background .2s ease, box-shadow .15s ease; text-decoration: none; }
    .row-btn:hover { background: var(--pink-soft); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,.12); }
    .row-btn:active { transform: translateY(0); }
    .row-btn-danger { background: #fef2f2; color: #dc2626; }
    .row-btn-danger:hover { background: #fee2e2; }
    .row-btn-done { background: #ecfdf5; color: #059669; }
    .row-btn-done:hover { background: #d1fae5; }
    .row-btn-muted { background: #f5f5f5; color: #c4c4c4; cursor: not-allowed; }
    .row-btn-muted:hover { background: #f5f5f5; transform: none; box-shadow: none; }
    button.row-btn { border: none; font: inherit; }
    .row-form { display: contents; }
    .btn-sms  { background: #0f9d58; color: white; }
    .btn-both { background: linear-gradient(135deg, #7a2f5f, #5c2249); color: white; }

    .invite-wrap { position: relative; display: inline-block; }
    .invite-menu { position: absolute; top: calc(100% + 6px); right: 0; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); box-shadow: var(--shadow-md); padding: 6px; min-width: 220px; z-index: 60; opacity: 0; transform: translateY(-8px) scale(.97); pointer-events: none; transition: opacity .18s ease, transform .18s ease; }
    .invite-wrap.open .invite-menu { opacity: 1; transform: translateY(0) scale(1); pointer-events: auto; }
    .invite-menu a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; text-decoration: none; color: var(--gray-dark); font-size: 13px; font-weight: 600; transition: background .15s ease, transform .15s ease; }
    .invite-menu a:hover { background: var(--off-white); transform: translateX(3px); }
    .invite-menu a .dot { width: 8px; height: 8px; border-radius: 50%; flex: none; }
    .dot-email { background: var(--info); }
    .dot-sms   { background: #0f9d58; }
    .dot-both  { background: linear-gradient(135deg, #7a2f5f, #5c2249); }
    .invite-menu small { display: block; font-weight: 400; color: var(--gray-mid); font-size: 11px; }
    .chevron { transition: transform .2s ease; }
    .invite-wrap.open .chevron { transform: rotate(180deg); }

    @keyframes flashIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .alert { animation: flashIn .35s ease; }
    @keyframes rowGlow { 0% { background: rgba(115,103,240,.14); } 100% { background: transparent; } }
    tr.just-sent td { animation: rowGlow 1.6s ease; }

    .edit-overlay { display: none; position: fixed; inset: 0; background: rgba(26,10,46,0.6); backdrop-filter: blur(4px); z-index: 1200; align-items: center; justify-content: center; padding: 20px; }
    .edit-overlay.show { display: flex; }
    .edit-modal { background: #fff; border-radius: 18px; width: 100%; max-width: 440px; max-height: 92vh; overflow-y: auto; box-shadow: 0 25px 60px rgba(0,0,0,0.3); }
    .edit-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 8px; }
    .edit-modal-header h3 { margin: 0; font-size: 18px; color: var(--purple-mid); }
    .edit-modal-body { padding: 8px 24px 4px; }
    .edit-modal-body label { display: block; font-size: 12px; font-weight: 600; color: var(--gray-mid); margin: 12px 0 4px; }
    .edit-modal-body input { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; outline: none; }
    .edit-modal-body input:focus { border-color: var(--purple-mid); }
    .edit-code { font-size: 12px; font-weight: 700; color: var(--purple-mid); letter-spacing: 0.5px; }
    .edit-msg { min-height: 18px; font-size: 12px; margin-top: 12px; }
    .edit-msg.err { color: #dc2626; }
    .edit-msg.ok  { color: #059669; }
    .edit-modal .modal-actions { display: flex; gap: 10px; padding: 12px 24px 22px; }
    .edit-modal .modal-actions .btn { flex: 1; }
    .email-status-note { font-size: 11px; color: #dc2626; margin-top: 2px; display: block; }
    .empty { text-align: center; padding: 60px; color: var(--gray-mid); }
    .row-flash { animation: rowFlash 1.2s ease; }
    @keyframes rowFlash { 0%,100% { background: transparent; } 30%,70% { background: rgba(194,59,142,.10); } }
    .scan-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.65); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 16px; }
    .scan-overlay.open { display: flex; }
    .scan-modal { background: white; border-radius: 20px; width: 100%; max-width: 520px; overflow: hidden; box-shadow: 0 24px 60px rgba(0,0,0,.35); }
    .scan-modal-header { padding: 18px 20px; background: linear-gradient(135deg, var(--purple-mid), var(--purple-light)); color: white; display: flex; justify-content: space-between; align-items: center; }
    .scan-modal-header h3 { margin: 0; font-size: 17px; }
    .scan-modal-header small { opacity: .8; font-size: 12px; }
    .btn-close-modal { background: rgba(255,255,255,.2); border: none; color: white; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: 18px; line-height: 1; display: flex; align-items: center; justify-content: center; }
    .btn-close-modal:hover { background: rgba(255,255,255,.35); }
    #qr-reader { width: 100%; }
    #qr-reader video { border-radius: 0; }
    .scan-manual { padding: 16px 20px; border-top: 1px solid #eee; display: flex; gap: 8px; }
    .scan-manual input { flex: 1; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; outline: none; }
    .scan-manual input:focus { border-color: var(--purple-light); }
    .badge-view { display: none; padding: 20px; }
    .badge-view.show { display: block; }
    .scan-result-msg { padding: 10px 14px; border-radius: 8px; font-size: 14px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
    .scan-result-msg.success { background: rgba(16,185,129,.12); color: var(--success); border-left: 3px solid var(--success); }
    .scan-result-msg.warning { background: rgba(245,158,11,.12); color: var(--warning); border-left: 3px solid var(--warning); }
    .scan-result-msg.error   { background: rgba(239,68,68,.12);  color: var(--danger);  border-left: 3px solid var(--danger);  }
    .scan-result-msg.info    { background: var(--info-bg); color: var(--info-text); border-left: 3px solid var(--info); }
    .badge-card { width: 100%; max-width: 380px; min-height: 200px; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.12); margin: 0 auto 16px; display: flex; overflow: hidden; border: 1px solid #eee; }
    .badge-stripe { width: 10px; flex-shrink: 0; background: linear-gradient(180deg, var(--pink-accent), var(--purple-light)); }
    .badge-body { flex: 1; padding: 18px; display: flex; flex-direction: column; justify-content: space-between; }
    .badge-top { display: flex; justify-content: space-between; align-items: flex-start; }
    .badge-code { font-size: 11px; color: var(--gray-mid); font-family: monospace; font-weight: 600; }
    .badge-event-name { font-size: 10px; color: var(--purple-mid); font-weight: 600; text-transform: uppercase; letter-spacing: .8px; text-align: right; max-width: 140px; line-height: 1.3; }
    .badge-name    { font-size: 22px; font-weight: 700; color: var(--purple-mid); margin: 10px 0 4px; line-height: 1.1; }
    .badge-company { font-size: 13px; color: var(--gray-dark); font-weight: 600; }
    .badge-bottom  { display: flex; align-items: flex-end; justify-content: space-between; margin-top: 12px; }
    .badge-type    { font-size: 9px; color: var(--gray-mid); font-style: italic; }
    .badge-qr-img  { width: 64px; height: 64px; border: 1px solid #eee; border-radius: 4px; padding: 2px; }
    .badge-edit-fields { display: none; gap: 10px; flex-direction: column; margin-bottom: 12px; }
    .badge-edit-fields.show { display: flex; }
    .badge-edit-fields input { padding: 9px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; outline: none; }
    .badge-edit-fields input:focus { border-color: var(--purple-light); }
    .modal-actions { display: flex; gap: 8px; margin-top: 4px; }
    .modal-actions .btn { flex: 1; justify-content: center; display: inline-flex; align-items: center; gap: 6px; padding: 12px; font-size: 14px; font-weight: 600; border-radius: 10px; border: none; cursor: pointer; transition: all .2s; }
    .btn-confirm {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #fff;
        box-shadow: 0 4px 12px rgba(16, 185, 129, .3);
    }
    .btn-confirm:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(16, 185, 129, .45);
    }
    .btn-confirm:disabled {
        opacity: .6;
        cursor: wait;
        transform: none;
    }
    .btn-reject {
        background: var(--gray-light);
        color: var(--gray-dark);
    }
    .btn-reject:hover {
        background: rgba(239, 68, 68, .15);
        color: var(--danger);
    }
    .edit-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 14px;
        background: var(--off-white);
        border-radius: 8px;
        margin-bottom: 12px;
        font-size: 13px;
    }
    .edit-bar-text { color: var(--gray-mid); }
    .edit-bar-btn {
        background: white;
        border: 1.5px solid var(--purple-light);
        color: var(--purple-mid);
        padding: 6px 14px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all .2s;
    }
    .edit-bar-btn:hover { background: var(--purple-light); color: white; }
    .edit-bar-btn.active { background: var(--purple-mid); color: white; border-color: var(--purple-mid); }
    .checkin-success-pulse {
        animation: pulseSuccess 1.5s ease;
    }
    @keyframes pulseSuccess {
        0%   { box-shadow: 0 0 0 0 rgba(16, 185, 129, .6); }
        70%  { box-shadow: 0 0 0 20px rgba(16, 185, 129, 0); }
        100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
    @media print {
        body * { visibility: hidden; }
        .print-badge, .print-badge * { visibility: visible; }
        .print-badge { position: fixed; top: 0; left: 0; width: 360px; height: 220px; display: flex !important; box-shadow: none !important; border: none !important; }
    }
</style>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/mobile.css?v=<?= ASSET_VER ?>" media="(max-width: 768px)">
</head>
<body class="dashboard-body page-attendees">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php $page_has_search = true; $back_url = $current_event ? BASE_URL . '/pages/events/view.php?id=' . (int)$event_id : null; $back_label = 'Back to event'; include __DIR__ . '/../../includes/header.php'; ?>
        <?php
            $ev_stats    = $current_event ? getEventStats($event_id) : null;
            $ev_total    = $ev_stats['total'] ?? 0;
            $has_filters = $filter_company || $filter_status || $search !== '';
            $ev_pill     = ['upcoming' => 'pill-info', 'ongoing' => 'pill-ok', 'completed' => 'pill-muted', 'archived' => 'pill-muted'];
        ?>
        <form method="GET" class="event-switch">
            <label class="es-select">
                <?= icon('calendar', ['class' => 'icon-svg']) ?>
                <select name="event_id" onchange="this.form.submit()" aria-label="Event">
                    <?php if (!$event_id): ?><option value="" selected disabled hidden>Select an event</option><?php endif; ?>
                    <?php while ($e = $events_list->fetch_assoc()): ?>
                        <option value="<?= $e['id'] ?>" <?= $event_id == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['event_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </label>
            <?php if ($current_event): ?>
                <div class="es-meta">
                    <span><?= date('M j, Y', strtotime($current_event['event_date'])) ?></span>
                    <?php if (!empty($current_event['location'])): ?><span class="sep">&bull;</span><span><?= htmlspecialchars($current_event['location']) ?></span><?php endif; ?>
                    <span class="<?= $ev_pill[$current_event['status']] ?? 'pill-muted' ?>"><?= $current_event['status'] === 'ongoing' ? 'Live' : ucfirst($current_event['status']) ?></span>
                </div>
                <?php if ($ev_total): ?>
                    <div class="es-progress">
                        <span><strong><?= $ev_stats['checked_in'] ?></strong> of <?= $ev_total ?> checked in</span>
                        <div class="progress" role="progressbar" aria-label="Checked in" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= round($ev_stats['percentage']) ?>"><i style="width: <?= $ev_stats['percentage'] ?>%"></i></div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </form>
        <?php if (!$event_id): ?>
            <div class="empty-state">
                <?= icon('calendar', ['class' => 'icon-svg']) ?>
                <strong>Select an event</strong>
                <p>Choose an event above to see and manage its attendees.</p>
            </div>
        <?php elseif ($ev_total === 0): ?>
            <div class="table-container">
                <div class="table-actions" style="justify-content: flex-end;">
                    <div class="btn-group">
                        <?php if ($current_event && ($current_event['status'] ?? '') !== 'archived'):
                            $is_completed = ($current_event['status'] ?? '') === 'completed';
                        ?>
                            <button type="button" class="btn-sm <?= $is_completed ? 'danger' : 'light' ?>"
                                    onclick="openEndEventModal()">
                                <?= icon($is_completed ? 'shield' : 'check-circle', ['class' => 'icon-svg icon-sm']) ?>
                                <?= $is_completed ? 'Archive Event' : 'End Event' ?>
                            </button>
                        <?php elseif ($current_event && $current_event['status'] === 'archived'): ?>
                            <span class="pill-muted">Archived</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="empty">
                    <h3 style="color: var(--purple-mid);">No attendees yet</h3>
                    <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])): ?>
                    <p style="margin: 12px 0;">Upload a CSV file to add attendees.</p>
                    <a href="<?= BASE_URL ?>/pages/attendees/upload.php?event_id=<?= $event_id ?>" class="btn btn-primary" style="display: inline-flex; align-items:center; gap:8px; width: auto; padding: 10px 20px;"><?= icon('upload', ['class' => 'icon-svg icon-sm']) ?> Upload Attendees</a>
                    <?php else: ?>
                    <p style="margin: 12px 0;">An admin can upload the attendee list. Walk-ins can still be registered at check-in.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="table-container">
                <div class="table-actions">
                    <div class="tc-title"><?= $ev_total ?> attendee<?= $ev_total === 1 ? '' : 's' ?></div>
                    <div class="btn-group">
                        <?php $scan_closed = $current_event ? checkinClosedReason($current_event) : null; ?>
                        <button type="button" class="btn-sm light" onclick="openScanner()"<?= $scan_closed ? ' disabled title="' . htmlspecialchars($scan_closed) . '"' : '' ?>><?= icon('camera', ['class' => 'icon-svg icon-sm']) ?> Scan QR</button>
                        <?php
                        if ($current_event && ($current_event['status'] ?? '') !== 'archived'
                            && in_array($_SESSION['role'] ?? '', ['admin', 'event_manager', 'super_admin'])):
                            $is_completed = ($current_event['status'] ?? '') === 'completed';
                        ?>
                            <button type="button" class="btn-sm <?= $is_completed ? 'danger' : 'light' ?>"
                                    onclick="openEndEventModal()">
                                <?= icon($is_completed ? 'shield' : 'check-circle', ['class' => 'icon-svg icon-sm']) ?>
                                <?= $is_completed ? 'Archive Event' : 'End Event' ?>
                            </button>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/api/attendees/export.php?event_id=<?= $event_id ?>" class="btn-sm light"><?= icon('download', ['class' => 'icon-svg icon-sm']) ?> Export CSV</a>
                        <div class="invite-wrap" id="invite-wrap">
                            <button type="button" class="btn-sm" onclick="toggleInvite(event)">
                                <?= icon('send', ['class' => 'icon-svg icon-sm']) ?>
                                Send Invite
                                <?= icon('chevron-down', ['class' => 'icon-svg icon-sm chevron']) ?>
                            </button>
                            <div class="invite-menu">
                                <a href="<?= BASE_URL ?>/api/notifications/send_both.php?event_id=<?= $event_id ?>&channel=both"
                                   data-confirm="Everyone who has not received their QR code yet gets it by email and SMS." data-confirm-title="Send email and SMS?" data-confirm-ok="Send both">
                                    <span class="dot dot-both"></span>
                                    <span>Email &amp; SMS<small>Send both at once</small></span>
                                </a>
                                <a href="<?= BASE_URL ?>/api/notifications/send_both.php?event_id=<?= $event_id ?>&channel=email"
                                   data-confirm="Everyone who has not received their QR code email yet gets one now." data-confirm-title="Send QR code emails?" data-confirm-ok="Send emails">
                                    <span class="dot dot-email"></span>
                                    <span>Email only<small>QR code to their inbox</small></span>
                                </a>
                                <?php if ($sms_ready && !$event_locked): ?>
                                <a href="<?= BASE_URL ?>/api/notifications/send_both.php?event_id=<?= $event_id ?>&channel=sms"
                                   data-confirm="<?= $sms_pending ?> attendee(s) with a mobile number and no SMS yet will get their QR link by text.<?= smsIsMock() ? '&#10;&#10;Mock mode is on: no real text is sent and nothing is charged.' : '&#10;&#10;This uses paid Semaphore credits.' ?>" data-confirm-title="Send SMS?" data-confirm-ok="Send SMS">
                                    <span class="dot dot-sms"></span>
                                    <span>SMS only<?= $sms_pending ? ' (' . $sms_pending . ')' : '' ?><small>QR link to their phone</small></span>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <form method="GET" id="filterForm" class="filter-bar tc-filters">
                    <input type="hidden" name="event_id" value="<?= (int)$event_id ?>">
                    <label class="fb-search">
                        <?= icon('search', ['class' => 'icon-svg']) ?>
                        <input type="search" name="search" id="searchInput" placeholder="Search name, email, code or company" title="Search by name, email, mobile, code, company or designation" value="<?= htmlspecialchars($search) ?>" aria-label="Search attendees" autocomplete="off" spellcheck="false">
                    </label>
                    <select name="company" onchange="this.form.submit()" aria-label="Company">
                        <option value="">All companies</option>
                        <?php foreach ($companies_list as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= $filter_company === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status" onchange="this.form.submit()" aria-label="Check-in status">
                        <option value="">All statuses</option>
                        <option value="not_yet"    <?= $filter_status === 'not_yet'    ? 'selected' : '' ?>>Not yet</option>
                        <option value="checked_in" <?= $filter_status === 'checked_in' ? 'selected' : '' ?>>Checked in</option>
                    </select>
                    <?php if ($has_filters): ?>
                        <a class="fb-clear" href="?event_id=<?= (int)$event_id ?>">Clear</a>
                        <span class="tc-showing">Showing <?= count($attendees) ?> of <?= $ev_total ?></span>
                    <?php endif; ?>
                </form>
                <div class="table-scroll">
                <table id="attendees-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Attendee</th>
                            <th>Company</th>
                            <th>Mobile</th>
                            <th>Status</th>
                            <th>Check-In</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$attendees): ?>
                            <tr><td colspan="7" class="tc-nomatch">No attendees match these filters. <a href="?event_id=<?= (int)$event_id ?>">Clear filters</a></td></tr>
                        <?php endif; ?>
                        <?php foreach ($attendees as $a): ?>
                            <tr id="row-<?= $a['id'] ?>">
                                <td class="nowrap">
                                    <strong style="color: var(--purple-mid);"><?= htmlspecialchars($a['attendee_code']) ?></strong>
                                    <span class="cell-sub"><?= ucfirst($a['registration_type']) ?></span>
                                </td>
                                <td class="cell-person">
                                    <span class="cell-main"><?= htmlspecialchars($a['full_name']) ?></span>
                                    <span class="cell-sub" title="<?= htmlspecialchars($a['email']) ?>"><?= htmlspecialchars($a['email']) ?></span>
                                    <?php if ($a['last_email_status'] === 'failed'): ?>
                                        <span class="email-status-note" title="<?= htmlspecialchars($a['last_email_error'] ?? '') ?>">Last send failed — tap resend</span>
                                    <?php endif; ?>
                                </td>
                                <td class="cell-company">
                                    <span class="cell-main"><?= htmlspecialchars($a['company']) ?></span>
                                    <?php if (!empty($a['designation'])): ?><span class="cell-sub"><?= htmlspecialchars($a['designation']) ?></span><?php endif; ?>
                                </td>
                                <td class="nowrap"><?= htmlspecialchars($a['mobile_number'] ?: '—') ?></td>
                                <td id="status-<?= $a['id'] ?>" class="nowrap">
                                    <span class="status-pill status-<?= $a['status'] ?>"><?= ucfirst(str_replace('_', ' ', $a['status'])) ?></span>
                                </td>
                                <td id="time-<?= $a['id'] ?>" class="nowrap" style="font-size: 12px; color: var(--gray-dark);"><?= $a['check_in_time'] ? date('M d, h:i A', strtotime($a['check_in_time'])) : '—' ?></td>
                                <td>
                                    <div class="row-actions">
                                        <a href="<?= BASE_URL ?>/pages/attendees/view_qr.php?id=<?= $a['id'] ?>" class="row-btn" title="View QR"><?= icon('qr', ['class' => 'icon-svg icon-sm']) ?></a>
                                        <a href="<?= BASE_URL ?>/pages/checkin/badge.php?attendee_id=<?= $a['id'] ?>&from=attendees" class="row-btn" title="Print Badge"><?= icon('printer', ['class' => 'icon-svg icon-sm']) ?></a>
                                        <?php
                                            $email_failed = $a['last_email_status'] === 'failed';
                                            $email_btn_icon  = $email_failed ? 'alert' : ($a['email_sent'] ? 'mail-check' : 'mail');
                                            $email_btn_class = 'row-btn' . ($email_failed ? ' row-btn-danger' : '');
                                            $email_btn_title = $email_failed
                                                ? 'Resend — last attempt failed: ' . htmlspecialchars($a['last_email_error'] ?? 'unknown error')
                                                : ($a['email_sent'] ? 'Resend Email' : 'Send Email');
                                        ?>
                                        <a href="<?= BASE_URL ?>/api/notifications/send_both.php?attendee_id=<?= $a['id'] ?>&channel=email" class="<?= $email_btn_class ?>" title="<?= $email_btn_title ?>" data-confirm="To <?= htmlspecialchars($a['email']) ?>" data-confirm-title="<?= $a['email_sent'] ? 'Resend' : 'Send' ?> QR code email?" data-confirm-ok="<?= $a['email_sent'] ? 'Resend' : 'Send' ?>"><?= icon($email_btn_icon, ['class' => 'icon-svg icon-sm']) ?></a>
                                        <?php if ($sms_ready): ?>
                                            <?php $has_mobile = normalizePHMobile($a['mobile_number'] ?? '') !== false; ?>
                                            <?php if ($has_mobile): ?>
                                                <a href="<?= BASE_URL ?>/api/notifications/send_both.php?attendee_id=<?= $a['id'] ?>&channel=sms" class="row-btn<?= $a['sms_sent'] ? ' row-btn-done' : '' ?>" title="<?= $a['sms_sent'] ? 'Resend SMS' : 'Send SMS' ?>" data-confirm="To <?= htmlspecialchars($a['mobile_number']) ?>" data-confirm-title="<?= $a['sms_sent'] ? 'Resend' : 'Send' ?> SMS?" data-confirm-ok="<?= $a['sms_sent'] ? 'Resend' : 'Send' ?>"><?= icon('smartphone', ['class' => 'icon-svg icon-sm']) ?></a>
                                                <a href="<?= BASE_URL ?>/api/notifications/send_both.php?attendee_id=<?= $a['id'] ?>&channel=both" class="row-btn" title="Send Email + SMS" data-confirm="<?= htmlspecialchars($a['full_name']) ?> gets their QR code by email and SMS." data-confirm-title="Send email and SMS?" data-confirm-ok="Send both"><?= icon('send', ['class' => 'icon-svg icon-sm']) ?></a>
                                            <?php else: ?>
                                                <span class="row-btn row-btn-muted" title="No valid mobile number"><?= icon('smartphone', ['class' => 'icon-svg icon-sm']) ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if (!$event_locked && $a['status'] === 'checked_in' && in_array(currentRole(), ['admin', 'super_admin'])): ?>
                                            <form method="POST" action="<?= BASE_URL ?>/api/checkin/undo.php" class="row-form"
                                                  data-confirm="<?= htmlspecialchars($a['full_name']) ?> goes back to Not yet. Use this when the wrong person was checked in. It is saved in the activity log."
                                                  data-confirm-title="Undo check-in?" data-confirm-ok="Undo check-in" data-confirm-danger>
                                                <input type="hidden" name="attendee_id" value="<?= (int)$a['id'] ?>">
                                                <input type="hidden" name="reason" value="Undone from the attendee list">
                                                <button type="submit" class="row-btn" title="Undo check-in" aria-label="Undo check-in for <?= htmlspecialchars($a['full_name']) ?>"><?= icon('undo', ['class' => 'icon-svg icon-sm']) ?></button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (!$event_locked): ?>
                                            <button type="button" class="row-btn" title="Edit details"
                                                    onclick='openEditAttendee(<?= json_encode([
                                                        "id"            => (int)$a["id"],
                                                        "code"          => $a["attendee_code"],
                                                        "full_name"     => $a["full_name"],
                                                        "email"         => $a["email"],
                                                        "company"       => $a["company"],
                                                        "designation"   => $a["designation"] ?? "",
                                                        "mobile_number" => $a["mobile_number"] ?? "",
                                                    ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><?= icon('edit', ['class' => 'icon-svg icon-sm']) ?></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>
<div class="edit-overlay" id="edit-overlay" onclick="if(event.target===this) closeEditAttendee()">
    <div class="edit-modal">
        <div class="edit-modal-header">
            <h3 style="display:flex; align-items:center; gap:8px;"><?= icon('edit', ['class' => 'icon-svg']) ?> Edit Attendee</h3>
            <button class="btn-close-modal" onclick="closeEditAttendee()"><?= icon('x', ['class' => 'icon-svg icon-sm']) ?></button>
        </div>
        <div class="edit-modal-body">
            <div class="edit-code" id="ea-code"></div>
            <input type="hidden" id="ea-id">
            <label>Full Name</label>
            <input type="text" id="ea-name" placeholder="Full Name">
            <label>Company</label>
            <input type="text" id="ea-company" list="company-options" placeholder="Company">
            <datalist id="company-options">
                <?php foreach ($companies_list as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <label>Designation</label>
            <input type="text" id="ea-designation" placeholder="e.g. IT Manager">
            <label>Email</label>
            <input type="email" id="ea-email" placeholder="email@example.com">
            <label>Mobile Number</label>
            <input type="tel" id="ea-mobile" placeholder="09XXXXXXXXX" inputmode="numeric">
            <div class="edit-msg" id="ea-msg"></div>
        </div>
        <div class="modal-actions">
            <button class="btn btn-reject" onclick="closeEditAttendee()">Cancel</button>
            <button class="btn btn-confirm" onclick="saveEditAttendee()" id="ea-save">Save Changes</button>
        </div>
    </div>
</div>

<div class="scan-overlay" id="scan-overlay">
    <div class="scan-modal">
        <div class="scan-modal-header">
            <div>
                <h3 style="display:flex; align-items:center; gap:8px;"><?= icon('camera', ['class' => 'icon-svg']) ?> Scan QR Code</h3>
                <?php if ($current_event): ?>
                    <small><?= htmlspecialchars($current_event['event_name']) ?></small>
                <?php endif; ?>
            </div>
            <button class="btn-close-modal" onclick="closeScanner()"><?= icon('x', ['class' => 'icon-svg icon-sm']) ?></button>
        </div>
        <div id="scan-view">
            <div id="qr-reader"></div>
            <div class="scan-manual">
                <input type="text" id="manual-code" placeholder="Or type QR / 9-digit attendee code" autocomplete="off">
                <button type="button" class="btn-sm" onclick="manualLookup()">Go</button>
            </div>
        </div>
        <div class="badge-view" id="badge-view">
            <div class="scan-result-msg" id="result-msg"></div>
            <div class="edit-bar" id="edit-bar">
                <span class="edit-bar-text">Wrong details?</span>
                <button type="button" class="edit-bar-btn" onclick="toggleEdit()" id="btn-edit">
                    <?= icon('edit', ['class' => 'icon-svg icon-sm']) ?> Edit
                </button>
            </div>
            <div class="badge-edit-fields" id="edit-fields">
                <input type="text" id="edit-name" placeholder="Full Name">
                <input type="text" id="edit-company" placeholder="Company">
                <input type="text" id="edit-designation" placeholder="Seminar 1 / Seminar 2">
                <input type="text" id="edit-mobile" placeholder="Mobile Number">
                <small style="color:var(--gray-mid);font-size:11px;">Changes saved when you confirm check-in.</small>
            </div>
            <div class="badge-card print-badge" id="badge-preview">
                <div class="badge-stripe"></div>
                <div class="badge-body">
                    <div class="badge-top">
                        <div class="badge-code" id="b-code"></div>
                        <div class="badge-event-name" id="b-event"></div>
                    </div>
                    <div>
                        <div class="badge-name" id="b-name"></div>
                        <div class="badge-company" id="b-company"></div>
                        <div class="badge-company" style="font-size:12px; margin-top:4px;" id="b-designation"></div>
                        <div class="badge-company" style="font-size:11px; color:var(--gray-mid);" id="b-mobile"></div>
                    </div>
                    <div class="badge-bottom">
                        <div class="badge-type" id="b-type"></div>
                        <img class="badge-qr-img" id="b-qr" src="" alt="QR">
                    </div>
                </div>
            </div>
            <div class="modal-actions" id="actions-confirm">
                <button class="btn btn-reject" onclick="rejectScan()">
                    <?= icon('x', ['class' => 'icon-svg icon-sm']) ?> Not this person
                </button>
                <button class="btn btn-confirm" onclick="confirmCheckIn()" id="btn-confirm-checkin">
                    <?= icon('check', ['class' => 'icon-svg icon-sm']) ?> Confirm & Check In
                </button>
            </div>
            <div class="modal-actions" id="actions-done" style="display:none;">
                <button class="btn btn-secondary" onclick="printBadge()"><?= icon('printer', ['class' => 'icon-svg icon-sm']) ?> Reprint Badge</button>
                <button class="btn btn-primary" onclick="scanNext()"><?= icon('camera', ['class' => 'icon-svg icon-sm']) ?> Scan Next</button>
            </div>
        </div>
    </div>
</div>
<script>
const EVENT_ID   = <?= $event_id ?: 0 ?>;

function toggleInvite(e) {
    e.stopPropagation();
    document.getElementById('invite-wrap').classList.toggle('open');
}
document.addEventListener('click', function (e) {
    const w = document.getElementById('invite-wrap');
    if (w && !w.contains(e.target)) w.classList.remove('open');
});

const CHECKIN_URL= '<?= BASE_URL ?>/api/checkin/checkin.php';
const POLL_URL   = '<?= BASE_URL ?>/api/attendees/poll.php';
const ICON_CHECK       = '<svg class="icon-svg icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>';
const ICON_CHECK_CIRCLE= '<svg class="icon-svg icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="9 12 12 15 16 9"/></svg>';
const ICON_X_CIRCLE    = '<svg class="icon-svg icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
const ICON_ALERT       = '<svg class="icon-svg icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2L1 21h22L12 2z"/><line x1="12" y1="10" x2="12" y2="14"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
const ICON_EDIT        = '<svg class="icon-svg icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
let scanner      = null;
let isProcessing = false;
let pollTimer    = null;
// MySQL's clock, same one that stamps attendees.updated_at (browser time is UTC and would refetch every row).
let lastPollTime = '<?= $conn->query("SELECT NOW() n")->fetch_assoc()['n'] ?>';
let editMode     = false;
let currentAttendee = null;
let searchTimer = null;
(() => { const si = document.getElementById('searchInput'); if (si && si.value) { si.focus(); si.setSelectionRange(si.value.length, si.value.length); } })();
document.getElementById('searchInput')?.addEventListener('input', function(){
    clearTimeout(searchTimer);
    searchTimer = setTimeout(()=>document.getElementById('filterForm').submit(), 400);
});
function openScanner() {
    document.getElementById('scan-overlay').classList.add('open');
    document.body.style.overflow = 'hidden';
    showScanView();
    if (!scanner) {
        scanner = new Html5Qrcode("qr-reader");
    }
    scanner.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 240, height: 240 } },
        (decodedText) => processScan(decodedText)
    ).catch(() => {
        document.getElementById('qr-reader').innerHTML =
            '<div style="padding:32px;text-align:center;color:var(--gray-mid);">Camera unavailable — use manual entry below</div>';
    });
}
function closeScanner() {
    document.getElementById('scan-overlay').classList.remove('open');
    document.body.style.overflow = '';
    stopCamera();
    currentAttendee = null;
    isProcessing = false;
    exitEditMode();
}
function stopCamera() {
    if (scanner) {
        scanner.stop().catch(() => {});
        scanner = null;
    }
}
function showScanView() {
    document.getElementById('scan-view').style.display  = '';
    document.getElementById('badge-view').classList.remove('show');
    isProcessing = false;
    document.getElementById('manual-code').value = '';
}
function showBadgeView(data, msgType, msgText) {
    document.getElementById('scan-view').style.display = 'none';
    document.getElementById('badge-view').classList.add('show');
    const msg = document.getElementById('result-msg');
    msg.className = 'scan-result-msg ' + msgType;
    msg.textContent = msgText;
    const a = data.attendee;
    document.getElementById('b-code').textContent     = a.attendee_code;
    document.getElementById('b-event').textContent    = a.event_name;
    document.getElementById('b-name').textContent     = a.full_name;
    document.getElementById('b-company').textContent  = a.company;
    document.getElementById('b-designation').textContent = a.designation || '';
    document.getElementById('b-mobile').textContent   = a.mobile_number || '';
    document.getElementById('b-type').textContent     = a.registration_type === 'walk-in' ? 'Walk-in' : 'Pre-registered';
    document.getElementById('b-qr').src               = data.qr_url || '';
    document.getElementById('edit-name').value        = a.full_name;
    document.getElementById('edit-company').value      = a.company;
    document.getElementById('edit-designation').value  = a.designation || '';
    document.getElementById('edit-mobile').value       = a.mobile_number || '';
    exitEditMode();
}
async function processScan(code) {
    if (isProcessing) return;
    isProcessing = true;
    stopCamera();
    try {
        const fd = new FormData();
        fd.append('event_id', EVENT_ID);
        fd.append('code', code);
        const res  = await fetch(CHECKIN_URL, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            currentAttendee = data.attendee;
            if (data.already_checked_in) {
                showBadgeView(data, 'warning',
                    'Already checked in at ' + new Date(data.attendee.check_in_time).toLocaleTimeString());
                document.getElementById('actions-confirm').style.display = 'none';
                document.getElementById('actions-done').style.display    = 'flex';
                document.getElementById('edit-bar').style.display        = 'none';
            } else {
                showBadgeView(data, 'info', 'Please verify identity before checking in');
                document.getElementById('actions-confirm').style.display = 'flex';
                document.getElementById('actions-done').style.display    = 'none';
                document.getElementById('edit-bar').style.display        = 'flex';
            }
        } else {
            showInlineError(data.message || 'QR code not recognized');
            setTimeout(() => {
                showScanView();
                restartCamera();
                isProcessing = false;
            }, 2200);
        }
    } catch (e) {
        console.error(e);
        showInlineError('Network error. Please try again.');
        setTimeout(() => {
            showScanView();
            restartCamera();
            isProcessing = false;
        }, 2200);
    }
}
function showInlineError(message) {
    const msgEl = document.createElement('div');
    msgEl.className = 'scan-result-msg error';
    msgEl.style.margin = '0 16px 12px';
    msgEl.textContent = message;
    const scanView = document.getElementById('scan-view');
    const existing = scanView.querySelector('.scan-result-msg');
    if (existing) existing.remove();
    scanView.appendChild(msgEl);
    setTimeout(() => msgEl.remove(), 3000);
}
function rejectScan() {
    currentAttendee = null;
    isProcessing = false;
    exitEditMode();
    showScanView();
    restartCamera();
}
async function confirmCheckIn() {
    if (!currentAttendee) return;
    const btn = document.getElementById('btn-confirm-checkin');
    btn.disabled = true;
    btn.innerHTML = '⏳ Checking in...';
    if (editMode) applyEdits();
    const finalName       = document.getElementById('b-name').textContent.trim();
    const finalCompany     = document.getElementById('b-company').textContent.trim();
    const finalDesignation = document.getElementById('edit-designation').value.trim();
    const finalMobile      = document.getElementById('edit-mobile').value.trim();
    try {
        const fd = new FormData();
        fd.append('event_id', EVENT_ID);
        fd.append('code', currentAttendee.attendee_code);
        fd.append('confirmed', '1');
        fd.append('full_name', finalName);
        fd.append('company', finalCompany);
        fd.append('designation', finalDesignation);
        fd.append('mobile_number', finalMobile);
        const res  = await fetch(CHECKIN_URL, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            const msg = document.getElementById('result-msg');
            msg.className = 'scan-result-msg success checkin-success-pulse';
            const editsApplied = (data.edits_applied && data.edits_applied.length > 0);
            msg.innerHTML = ICON_CHECK_CIRCLE + ' Checked in successfully!' +
                (editsApplied ? '<br><small>Updated: ' + data.edits_applied.join(' · ') + '</small>' : '');
            document.getElementById('actions-confirm').style.display = 'none';
            document.getElementById('actions-done').style.display    = 'flex';
            document.getElementById('edit-bar').style.display        = 'none';
            flashRow(data.attendee.id, 'checked_in', data.attendee.check_in_time);
            setTimeout(() => {
                if (editMode) applyEdits();
                window.print();
            }, 800);
        } else if (data.already_checked_in) {
            const msg = document.getElementById('result-msg');
            msg.className = 'scan-result-msg warning';
            msg.innerHTML = ICON_ALERT + ' ' + (data.message || 'Already checked in');
            document.getElementById('actions-confirm').style.display = 'none';
            document.getElementById('actions-done').style.display    = 'flex';
        } else {
            const msg = document.getElementById('result-msg');
            msg.className = 'scan-result-msg error';
            msg.innerHTML = ICON_X_CIRCLE + ' ' + (data.message || 'Check-in failed');
            btn.disabled = false;
            btn.innerHTML = ICON_CHECK + ' Confirm & Check In';
        }
    } catch (e) {
        console.error(e);
        const msg = document.getElementById('result-msg');
        msg.className = 'scan-result-msg error';
        msg.innerHTML = ICON_X_CIRCLE + ' Network error. Please try again.';
        btn.disabled = false;
        btn.innerHTML = ICON_CHECK + ' Confirm & Check In';
    }
}
function restartCamera() {
    if (!scanner) {
        scanner = new Html5Qrcode("qr-reader");
    }
    scanner.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 240, height: 240 } },
        (decodedText) => processScan(decodedText)
    ).catch(() => {});
}
function manualLookup() {
    const code = document.getElementById('manual-code').value.trim();
    if (code) processScan(code);
}
document.addEventListener('keydown', e => {
    if (e.key === 'Enter' && document.activeElement.id === 'manual-code') manualLookup();
    if (e.key === 'Escape') closeScanner();
});
function scanNext() {
    currentAttendee = null;
    isProcessing = false;
    exitEditMode();
    showScanView();
    restartCamera();
}
function toggleEdit() {
    editMode = !editMode;
    const btn = document.getElementById('btn-edit');
    if (editMode) {
        document.getElementById('edit-fields').classList.add('show');
        btn.innerHTML = ICON_CHECK + ' Done';
        btn.classList.add('active');
    } else {
        applyEdits();
        exitEditMode();
    }
}
function applyEdits() {
    const name       = document.getElementById('edit-name').value.trim();
    const company    = document.getElementById('edit-company').value.trim();
    const designation= document.getElementById('edit-designation').value.trim();
    const mobile     = document.getElementById('edit-mobile').value.trim();
    if (name)        document.getElementById('b-name').textContent        = name;
    if (company)     document.getElementById('b-company').textContent     = company;
    if (designation) document.getElementById('b-designation').textContent = designation;
    if (mobile)      document.getElementById('b-mobile').textContent      = mobile;
}
function exitEditMode() {
    editMode = false;
    const fields = document.getElementById('edit-fields');
    const btn = document.getElementById('btn-edit');
    if (fields) fields.classList.remove('show');
    if (btn) {
        btn.innerHTML = ICON_EDIT + ' Edit';
        btn.classList.remove('active');
    }
}
function printBadge() {
    if (editMode) applyEdits();
    window.print();
}
function flashRow(id, status, checkInTime) {
    const row = document.getElementById('row-' + id);
    if (!row) return;
    const statusCell = document.getElementById('status-' + id);
    // Poll re-reports recent rows; only flash when the status actually changed
    if (statusCell && statusCell.querySelector('.status-' + status)) return;
    const timeCell   = document.getElementById('time-'   + id);
    if (statusCell) {
        const label = status === 'checked_in' ? 'Checked In' : status.replace('_', ' ');
        statusCell.innerHTML = `<span class="status-pill status-${status}">${label.charAt(0).toUpperCase() + label.slice(1)}</span>`;
    }
    if (timeCell && checkInTime) {
        const d = new Date(checkInTime);
        timeCell.textContent = d.toLocaleDateString('en-US', { month:'short', day:'2-digit' }) + ', ' +
            d.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit' });
    }
    row.classList.remove('row-flash');
    void row.offsetWidth;
    row.classList.add('row-flash');
}
async function pollCheckins() {
    if (!EVENT_ID) return;
    try {
        const url  = `${POLL_URL}?event_id=${EVENT_ID}&since=${encodeURIComponent(lastPollTime)}`;
        const res  = await fetch(url);
        const data = await res.json();
        if (data.server_time) lastPollTime = data.server_time;
        (data.attendees || []).forEach(a => flashRow(a.id, a.status, a.check_in_time));
    } catch (e) {}
}
if (EVENT_ID) {
    pollTimer = setInterval(pollCheckins, 3000);
}
document.getElementById('scan-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeScanner();
});
function openEndEventModal() {
    var m = document.getElementById('endEventModal');
    if (m) m.classList.add('show');
}
function closeEndEventModal() {
    var m = document.getElementById('endEventModal');
    if (m) m.classList.remove('show');
}
</script>
<?php if ($current_event && ($current_event['status'] ?? '') !== 'archived'
    && in_array($_SESSION['role'] ?? '', ['admin', 'event_manager', 'super_admin'])):
    $is_completed = ($current_event['status'] ?? '') === 'completed';
?>
<div class="end-event-modal" id="endEventModal" onclick="if(event.target===this) closeEndEventModal()">
    <div class="end-event-modal__box">
        <div class="end-event-modal__icon">
            <svg viewBox="0 0 24 24" width="32" height="32" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" fill="none">
                <?php if ($is_completed): ?>
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                <?php else: ?>
                    <circle cx="12" cy="12" r="10"/><polyline points="9 12 12 15 16 9"/>
                <?php endif; ?>
            </svg>
        </div>
        <h3 class="end-event-modal__title">
            <?= $is_completed ? 'Archive this event?' : 'End this event?' ?>
        </h3>
        <p class="end-event-modal__text">
            <strong><?= htmlspecialchars($current_event['event_name']) ?></strong><br>
            <?php if ($is_completed): ?>
                This event is already completed. Archiving moves it out of active views.
            <?php else: ?>
                The event status will be set to <strong>Completed</strong>. Any pending QR-code emails will be cancelled. You can archive it later.
            <?php endif; ?>
        </p>
        <div class="end-event-modal__actions">
            <button type="button" class="end-event-modal__btn end-event-modal__btn--cancel" onclick="closeEndEventModal()">
                Cancel
            </button>
            <a href="<?= BASE_URL ?>/api/events/end.php?event_id=<?= $event_id ?><?= $is_completed ? '&archive=1' : '' ?>"
               class="end-event-modal__btn end-event-modal__btn--confirm">
                <?= $is_completed ? 'Yes, Archive' : 'Yes, End Event' ?>
            </a>
        </div>
    </div>
</div>
<style>
<style>
.btn-end-event {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: #fff;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
}
.btn-end-event:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(239, 68, 68, 0.35);
}
.btn-end-event.is-archive {
    background: linear-gradient(135deg, #6b3d7a 0%, #4a2c5a 100%);
    box-shadow: 0 4px 12px rgba(107, 61, 122, 0.25);
}
.btn-end-event.is-archive:hover {
    box-shadow: 0 6px 16px rgba(107, 61, 122, 0.35);
}
.end-event-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(26, 10, 46, 0.6);
    backdrop-filter: blur(4px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.end-event-modal.show {
    display: flex;
}
.end-event-modal__box {
    background: white;
    border-radius: 20px;
    width: 100%;
    max-width: 420px;
    padding: 28px 24px;
    box-shadow: 0 24px 60px rgba(0,0,0,.3);
    text-align: center;
}
.end-event-modal__icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(239,68,68,.15), rgba(220,38,38,.15));
    color: #dc2626;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}
.end-event-modal__title {
    font-size: 18px;
    margin: 0 0 10px;
    color: var(--purple-mid);
}
.end-event-modal__text {
    font-size: 14px;
    color: var(--gray-mid);
    line-height: 1.5;
    margin-bottom: 24px;
}
.end-event-modal__actions {
    display: flex;
    gap: 10px;
}
.end-event-modal__btn {
    flex: 1;
    padding: 12px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all .2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.end-event-modal__btn--cancel {
    background: var(--off-white);
    color: var(--gray-dark);
}
.end-event-modal__btn--cancel:hover {
    background: #e5e7eb;
}
.end-event-modal__btn--confirm {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(239,68,68,.25);
}
.end-event-modal__btn--confirm:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(239,68,68,.35);
}
</style>
<?php endif; ?>
<script>
const UPDATE_ATTENDEE_URL = '<?= BASE_URL ?>/api/attendees/update.php';

function openEditAttendee(a) {
    document.getElementById('ea-id').value          = a.id;
    document.getElementById('ea-code').textContent  = a.code;
    document.getElementById('ea-name').value        = a.full_name || '';
    document.getElementById('ea-company').value     = a.company || '';
    document.getElementById('ea-designation').value = a.designation || '';
    document.getElementById('ea-email').value       = a.email || '';
    document.getElementById('ea-mobile').value      = a.mobile_number || '';
    const msg = document.getElementById('ea-msg');
    msg.textContent = '';
    msg.className = 'edit-msg';
    document.getElementById('edit-overlay').classList.add('show');
    setTimeout(() => document.getElementById('ea-name').focus(), 50);
}

function closeEditAttendee() {
    document.getElementById('edit-overlay').classList.remove('show');
}

async function saveEditAttendee() {
    const btn = document.getElementById('ea-save');
    const msg = document.getElementById('ea-msg');

    const fd = new FormData();
    fd.append('id',            document.getElementById('ea-id').value);
    fd.append('full_name',     document.getElementById('ea-name').value.trim());
    fd.append('company',       document.getElementById('ea-company').value.trim());
    fd.append('designation',   document.getElementById('ea-designation').value.trim());
    fd.append('email',         document.getElementById('ea-email').value.trim());
    fd.append('mobile_number', document.getElementById('ea-mobile').value.trim());

    btn.disabled = true;
    btn.textContent = 'Saving...';
    msg.className = 'edit-msg';
    msg.textContent = '';

    try {
        const res  = await fetch(UPDATE_ATTENDEE_URL, { method: 'POST', body: fd });
        const data = await res.json();

        if (!data.success) {
            msg.className = 'edit-msg err';
            msg.textContent = data.message;
            return;
        }

        msg.className = 'edit-msg ok';
        msg.textContent = 'Saved. Refreshing...';
        appToastNext(data.message, 'success');
        setTimeout(() => location.reload(), 500);
    } catch (e) {
        msg.className = 'edit-msg err';
        msg.textContent = 'Network error. Try again.';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Save Changes';
    }
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeEditAttendee();
    if (e.key === 'Enter' && document.getElementById('edit-overlay').classList.contains('show')) {
        saveEditAttendee();
    }
});
</script>
