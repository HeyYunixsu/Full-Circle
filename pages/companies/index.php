<?php
require_once __DIR__ . '/../../core/bootstrap.php';
requireRole(['admin', 'super_admin']);

[$events, $event] = selectedEvent();
$eid    = (int)($event['id'] ?? 0);
$locked = $event && isEventLocked($event['status']);
$back   = BASE_URL . '/pages/companies/index.php?event_id=' . $eid;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $eid) {
    checkCsrf();
    if ($locked) redirect($back, 'This event is archived. Companies are read-only.', 'error');

    $action = $_POST['action'] ?? '';
    $name   = mb_substr(capitalizeWords($_POST['name'] ?? ''), 0, 255);
    $old    = trim($_POST['old'] ?? '');
    $cap    = trim($_POST['max'] ?? '') === '' ? null : max(1, (int)$_POST['max']);   // blank = no limit

    if ($action === 'add') {
        if ($name === '') redirect($back, 'Enter a company name.', 'error');
        $s = $conn->prepare("INSERT INTO companies (event_id, company_name, max_attendees)
                             SELECT ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM companies WHERE event_id = ? AND company_name = ?)");
        $s->bind_param("isiis", $eid, $name, $cap, $eid, $name);
        $s->execute();
        redirect($back, $s->affected_rows ? 'Company added.' : 'That company is already listed.', $s->affected_rows ? 'success' : 'info');
    }

    if ($action === 'rename') {
        if ($name === '' || $old === '') redirect($back, 'Enter the new company name.', 'error');
        // Renaming onto an existing name merges the two.
        $conn->begin_transaction();
        $s = $conn->prepare("UPDATE attendees SET company = ? WHERE event_id = ? AND company = ?");
        $s->bind_param("sis", $name, $eid, $old);
        $s->execute();
        $moved = $s->affected_rows;
        $s = $conn->prepare("DELETE FROM companies WHERE event_id = ? AND company_name IN (?, ?)");
        $s->bind_param("iss", $eid, $old, $name);
        $s->execute();
        $s = $conn->prepare("INSERT INTO companies (event_id, company_name, max_attendees) VALUES (?, ?, ?)");
        $s->bind_param("isi", $eid, $name, $cap);
        $s->execute();
        $conn->commit();
        if ($name === $old) redirect($back, $cap ? "Saved. {$name} is limited to {$cap} attendee(s)." : "Saved. {$name} has no limit.");
        logActivity('Company Renamed', "Event #{$eid}: '{$old}' -> '{$name}' ({$moved} attendees)");
        redirect($back, "Renamed. {$moved} attendee(s) updated.");
    }

    if ($action === 'delete') {
        $s = $conn->prepare("SELECT COUNT(*) c FROM attendees WHERE event_id = ? AND company = ?");
        $s->bind_param("is", $eid, $old);
        $s->execute();
        if ($s->get_result()->fetch_assoc()['c'] > 0) {
            redirect($back, 'This company still has attendees. Rename it into another company instead.', 'error');
        }
        $s = $conn->prepare("DELETE FROM companies WHERE event_id = ? AND company_name = ?");
        $s->bind_param("is", $eid, $old);
        $s->execute();
        redirect($back, 'Company removed.');
    }
    redirect($back, 'Unknown action.', 'error');
}

$rows = [];
if ($eid) {
    // Companies listed for the event plus any company name typed on an attendee.
    $rows = companyCapacity($eid);
}

$flash = getFlashMessage();
$page_title = 'Companies';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $page_title ?> &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?>"><?= $flash['message'] ?></div>
        <?php endif; ?>

        <?php if (!$event): ?>
            <div class="empty-note">No events yet. Create an event first.</div>
        <?php else: ?>
        <div class="toolbar">
            <form method="GET">
                <select name="event_id" onchange="this.form.submit()" aria-label="Event">
                    <?php foreach ($events as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $e['id'] == $eid ? 'selected' : '' ?>><?= htmlspecialchars($e['event_name']) ?> (<?= formatDate($e['event_date'], 'M j, Y') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </form>
            <span class="spacer"></span>
            <?php if (!$locked): ?>
            <form method="POST" class="inline-form">
                <?= csrfField() ?>
                <input type="hidden" name="event_id" value="<?= $eid ?>">
                <input type="hidden" name="action" value="add">
                <input type="text" name="name" placeholder="New company name" required aria-label="New company name" maxlength="255">
                <input type="number" name="max" min="1" placeholder="Limit" aria-label="Attendee limit (optional)">
                <button class="btn-sm"><?= icon('plus', ['class' => 'icon-svg icon-sm']) ?> Add</button>
            </form>
            <?php endif; ?>
        </div>

        <?php if ($locked): ?>
            <div class="alert alert-warning">This event is archived. Companies are read-only.</div>
        <?php endif; ?>

        <div class="panel">
            <h3><?= htmlspecialchars($event['event_name']) ?> &mdash; <?= count($rows) ?> compan<?= count($rows) === 1 ? 'y' : 'ies' ?></h3>
            <?php if (!$rows): ?>
                <p class="muted">No companies yet. Add one above, or they appear automatically when attendees are uploaded.</p>
            <?php else: ?>
            <table class="tbl">
                <tr><th>Company</th><th class="num">Registered / limit</th><th class="num">Checked in</th><th>Check-in rate</th><?php if (!$locked): ?><th class="no-print">Name &amp; limit</th><th class="no-print"></th><?php endif; ?></tr>
                <?php foreach ($rows as $r): $pct = $r['total'] ? round($r['checked_in'] / $r['total'] * 100) : 0; ?>
                    <tr>
                        <td><?= htmlspecialchars($r['name']) ?></td>
                        <td class="num"><span class="cap-count"><?= capacityBadge((int)$r['total'], (int)$r['cap']) ?><strong><?= (int)$r['total'] ?></strong><?= $r['cap'] ? ' / ' . (int)$r['cap'] : ' <span class="faint">/ no limit</span>' ?></span></td>
                        <td class="num"><?= (int)$r['checked_in'] ?></td>
                        <td><?= meter($pct, 100, $pct . '%') ?></td>
                        <?php if (!$locked): ?>
                        <td class="no-print">
                            <form method="POST" class="inline-form">
                                <?= csrfField() ?>
                                <input type="hidden" name="event_id" value="<?= $eid ?>">
                                <input type="hidden" name="action" value="rename">
                                <input type="hidden" name="old" value="<?= htmlspecialchars($r['name']) ?>">
                                <input type="text" name="name" value="<?= htmlspecialchars($r['name']) ?>" required maxlength="255" aria-label="New name for <?= htmlspecialchars($r['name']) ?>">
                                <input type="number" name="max" min="1" value="<?= $r['cap'] ? (int)$r['cap'] : '' ?>" placeholder="No limit" aria-label="Attendee limit for <?= htmlspecialchars($r['name']) ?>">
                                <button class="btn-sm light">Save</button>
                            </form>
                        </td>
                        <td class="no-print">
                            <?php if (!$r['total']): ?>
                            <form method="POST" class="inline-form" data-confirm="It has no attendees, so nothing else changes." data-confirm-title="Remove this company?" data-confirm-ok="Remove" data-confirm-danger>
                                <?= csrfField() ?>
                                <input type="hidden" name="event_id" value="<?= $eid ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="old" value="<?= htmlspecialchars($r['name']) ?>">
                                <button class="btn-sm danger">Remove</button>
                            </form>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </table>
            <p class="form-help" style="margin-top:12px;">Leave the limit blank for no limit. To fix duplicates like "SAP" and "SAP Inc.", rename one to match the other and they merge.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
