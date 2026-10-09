<?php

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../includes/excel_parser.php';
require_once __DIR__ . '/../../includes/qrcode.php';
requireRole(['admin', 'super_admin']);   // importing attendee lists is for admins; staff run check-in

$event_id = (int)($_GET['event_id'] ?? $_POST['event_id'] ?? 0);

if (!$event_id) {
    redirect(BASE_URL . '/pages/attendees/index.php', 'Please select an event first.', 'error');
}

$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) {
    redirect(BASE_URL . '/pages/events/index.php', 'Event not found.', 'error');
}

$step           = 'upload';
$parsed_data    = [];
$parse_errors   = [];
$parse_info     = [];
$default_company = $_POST['default_company'] ?? $event['event_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {

    $tmp_file = $_FILES['csv_file']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['csv', 'txt', 'xlsx'])) {
        $parse_errors[] = "Only .csv, .txt, or .xlsx files are supported.";
    } else {
        $result = ($ext === 'xlsx')
            ? parseAttendeesXLSX($tmp_file, $default_company)
            : parseAttendeesCSV($tmp_file, $default_company);

        if ($result['success']) {
            
            $_SESSION['pending_import'] = [
                'event_id' => $event_id,
                'attendees' => $result['data'],
                'uploaded_at' => time(),
            ];
            $parsed_data = $result['data'];
            $parse_info = [
                'total' => $result['total'],
                'skipped' => $result['skipped'] ?? 0,
                'delimiter' => $result['delimiter'] ?? ',',
            ];
            $parse_errors = $result['errors'];
            $step = 'preview';
        } else {
            $parse_errors = $result['errors'] ?: ['Could not parse the file.'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {

    if (!isset($_SESSION['pending_import']) || $_SESSION['pending_import']['event_id'] !== $event_id) {
        redirect(BASE_URL . '/pages/attendees/upload.php?event_id=' . $event_id, 'Import session expired. Please upload again.', 'error');
    }

    $attendees_to_import = $_SESSION['pending_import']['attendees'];

    $edited_names    = $_POST['name'] ?? [];
    $edited_emails   = $_POST['email'] ?? [];
    $edited_companies = $_POST['company'] ?? [];
    $edited_mobiles  = $_POST['mobile'] ?? [];
    $edited_designations = $_POST['designation'] ?? [];
    $include_flags   = $_POST['include'] ?? [];

    $imported = 0;
    $skipped = 0;
    $errors = [];

    $conn->begin_transaction();

    $existing_emails = [];
    $existing_result = $conn->query("SELECT LOWER(email) AS email FROM attendees WHERE event_id = " . (int)$event_id);
    while ($r = $existing_result->fetch_assoc()) {
        $existing_emails[$r['email']] = true;
    }

    

    $existing_codes = [];
    $codes_result = $conn->query("SELECT attendee_code FROM attendees");
    while ($r = $codes_result->fetch_assoc()) {
        $existing_codes[$r['attendee_code']] = true;
    }

    
    $generate_code = function () use (&$existing_codes) {
        do {
            $code = (string) random_int(100000000, 999999999);
        } while (isset($existing_codes[$code]));
        $existing_codes[$code] = true; 
        return $code;
    };

    $insert_sql = "INSERT INTO attendees (event_id, attendee_code, full_name, email, company, mobile_number, designation, qr_code, qr_image_path, registration_type, status)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pre-registered', 'not_yet')";
    $insert_stmt = $conn->prepare($insert_sql);

    foreach ($attendees_to_import as $i => $row) {
        
        if (!isset($include_flags[$i])) {
            $skipped++;
            continue;
        }

        $name    = capitalizeWords($edited_names[$i] ?? $row['name']);
        $email   = trim($edited_emails[$i] ?? $row['email']);
        $company = capitalizeWords($edited_companies[$i] ?? $row['company']);
        $designation = capitalizeWords($edited_designations[$i] ?? ($row['designation'] ?? '')) ?: null;
        $mobile_raw  = trim($edited_mobiles[$i] ?? ($row['mobile'] ?? ''));
        $mobile = null;
        if ($mobile_raw !== '') {
            $n = normalizePHMobile($mobile_raw);
            if ($n) $mobile = '0' . substr($n, 2);
            else $errors[] = "Invalid mobile '{$mobile_raw}' for {$email} (imported without mobile)";
        }
        $email_lower = strtolower($email);

        if (empty($name) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Skipped: invalid name or email ({$email})";
            continue;
        }

        if (isset($existing_emails[$email_lower])) {
            $skipped++;
            continue;
        }

        $code = $generate_code();
        $qr_data = generateQRData($code);
        $qr_filename = $code . '_' . time();
        $qr_path = saveQRCode($qr_data, $qr_filename);

        if (!$qr_path) {
            $qr_path = null;  
        }

        $insert_stmt->bind_param("issssssss", $event_id, $code, $name, $email, $company, $mobile, $designation, $qr_data, $qr_path);

        if ($insert_stmt->execute()) {
            $imported++;
            $existing_emails[$email_lower] = true; 
        } else {
            $errors[] = "Failed to import {$email}: " . $conn->error;
        }
    }

    $conn->commit();

    unset($_SESSION['pending_import']);

    logActivity('Attendees Imported', "Event #{$event_id}: {$imported} imported, {$skipped} skipped");

    $msg = "Imported {$imported} attendee" . ($imported !== 1 ? 's' : '');
    if ($skipped > 0) $msg .= ", skipped {$skipped} (duplicates or unchecked)";
    $bad_mobiles = count(preg_grep('/^Invalid mobile/', $errors));
    if ($bad_mobiles > 0) $msg .= ". {$bad_mobiles} invalid mobile number" . ($bad_mobiles !== 1 ? 's' : '') . " left blank";
    $type = $imported > 0 && !$bad_mobiles ? 'success' : 'warning';

    redirect(BASE_URL . '/pages/attendees/index.php?event_id=' . $event_id, $msg, $type);
}

if ($step === 'upload' && isset($_SESSION['pending_import']) && $_SESSION['pending_import']['event_id'] === $event_id) {
    $age = time() - $_SESSION['pending_import']['uploaded_at'];
    if ($age < 600) { 
        $parsed_data = $_SESSION['pending_import']['attendees'];
        $step = 'preview';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Upload Attendees &mdash; <?= htmlspecialchars($event['event_name']) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<style>
    .upload-wrap { max-width: 1100px; margin: 0 auto; }
    .upload-head { margin-bottom: 18px; }
    .upload-head p { font-size: 13px; color: var(--color-text-muted); }
    .file-info { color: var(--wine-mid); font-weight: 600; margin-top: 12px; font-size: 14px; }
    .file-info:empty { display: none; }
    .alert ul { margin: 6px 0 0; padding-left: 20px; font-weight: 400; }
    .alert.block { display: block; }
    .preview-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
    .preview-summary { font-size: 14px; color: var(--color-text-muted); }
    .bulk-action { display: flex; gap: 8px; align-items: center; }
    .preview-table th.checkbox-cell, .preview-table td.checkbox-cell { width: 40px; text-align: center; }
    .preview-table .row-num { width: 40px; color: var(--color-text-muted); font-size: 12px; }
    .preview-table td { padding: 4px 6px; }
    .preview-table input[type=text], .preview-table input[type=email], .preview-table input[type=tel] { width: 100%; min-width: 120px; padding: 7px 10px; border: 1px solid transparent; border-radius: 6px; font-size: 13px; background: transparent; }
    .preview-table input:hover { background: var(--color-surface-alt); }
    .preview-table input:focus { border-color: var(--magenta); background: var(--color-surface); box-shadow: var(--focus-ring); }
    .preview-table input[type=checkbox] { width: 16px; height: 16px; accent-color: var(--magenta); }
    .preview-table tr.unchecked td { opacity: .4; }
</style>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/mobile.css?v=<?= ASSET_VER ?>" media="(max-width: 768px)">
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php $page_title = 'Upload Attendees'; $back_url = BASE_URL . '/pages/attendees/index.php?event_id=' . (int)$event_id; $back_label = 'Back to Attendees'; include __DIR__ . '/../../includes/header.php'; ?>


        <div class="upload-wrap">
            <div class="panel">

                <div class="upload-head">
                    <h3><span class="panel-title"><?= icon('upload', ['class' => 'icon-svg icon-md']) ?> Import Attendees &mdash; <?= htmlspecialchars($event['event_name']) ?></span></h3>
                    <p>Upload a CSV or Excel file. Names, emails, company, mobile and designation columns are detected automatically, even if the file is messy.</p>
                </div>

                <div class="steps">
                    <div class="step <?= $step === 'upload' ? 'active' : 'done' ?>">1. Upload file</div>
                    <div class="step <?= $step === 'preview' ? 'active' : '' ?>">2. Preview &amp; confirm</div>
                </div>

                <?php if (!empty($parse_errors) && $step === 'upload'): ?>
                    <div class="alert alert-error block" role="alert">
                        <strong>Could not process the file:</strong>
                        <ul>
                            <?php foreach ($parse_errors as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($step === 'upload'): ?>
                    <!-- STEP 1: UPLOAD -->
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <input type="hidden" name="event_id" value="<?= $event_id ?>">

                        <div class="form-group">
                            <label for="default_company">Default company <span class="optional">(optional)</span></label>
                            <input type="text" name="default_company" id="default_company" class="form-input" value="<?= htmlspecialchars($default_company) ?>" placeholder="e.g. SAP" aria-describedby="default-company-help">
                            <div class="form-help" id="default-company-help">Used when the file has no Company column, or the cell is empty.</div>
                        </div>

                        <label class="drop-zone" id="dropZone">
                            <div class="icon-circle"><?= icon('upload', ['class' => 'icon-svg']) ?></div>
                            <h3>Drop your file here</h3>
                            <p>or click to browse</p>
                            <p class="faint" style="font-size:12px;margin-top:10px;">Accepts .csv, .txt or .xlsx with name and email columns</p>
                            <input type="file" name="csv_file" id="fileInput" accept=".csv,.txt,.xlsx" required>
                            <div class="file-info" id="fileInfo" aria-live="polite"></div>
                        </label>

                        <div class="form-actions">
                            <a href="<?= BASE_URL ?>/pages/attendees/index.php?event_id=<?= $event_id ?>" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Parse file</button>
                        </div>
                    </form>

                <?php else: ?>
                    <!-- STEP 2: PREVIEW -->

                    <?php if (!empty($parse_info)): ?>
                        <div class="alert alert-info">
                            <?= icon('check-circle', ['class' => 'icon-svg icon-sm']) ?>
                            <span>Detected <strong><?= $parse_info['total'] ?></strong> valid attendees
                            <?php if ($parse_info['skipped'] > 0): ?>
                                &middot; skipped <?= $parse_info['skipped'] ?> row(s) (junk, headers or duplicates)
                            <?php endif; ?>
                            &middot; delimiter <code><?= htmlspecialchars($parse_info['delimiter']) ?></code></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($parse_errors)): ?>
                        <div class="alert alert-warning block">
                            <strong>Some rows were skipped:</strong>
                            <ul>
                                <?php foreach ($parse_errors as $e): ?>
                                    <li><?= htmlspecialchars($e) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="confirmForm">
                        <input type="hidden" name="event_id" value="<?= $event_id ?>">
                        <input type="hidden" name="confirm_import" value="1">

                        <div class="preview-toolbar">
                            <div class="preview-summary">
                                Edit any field, untick rows to skip, then confirm the import.
                            </div>
                            <div class="bulk-action">
                                <input type="text" id="bulkCompany" class="input" placeholder="Set company for all..." value="<?= htmlspecialchars($default_company) ?>" aria-label="Company to apply to all rows">
                                <button type="button" class="btn-sm light" onclick="applyBulkCompany()">Apply to all</button>
                            </div>
                        </div>

                        <div class="table-wrap">
                        <table class="tbl preview-table">
                            <thead>
                                <tr>
                                    <th class="checkbox-cell">
                                        <input type="checkbox" id="checkAll" checked onchange="toggleAll(this)" aria-label="Include all rows">
                                    </th>
                                    <th class="row-num">#</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Company</th>
                                    <th>Mobile</th>
                                    <th>Designation</th>
                                </tr>
                            </thead>
                            <tbody id="previewTbody">
                                <?php foreach ($parsed_data as $i => $row): ?>
                                    <tr>
                                        <td class="checkbox-cell">
                                            <input type="checkbox" name="include[<?= $i ?>]" value="1" checked onchange="updateRowStyle(this)" aria-label="Include row <?= $i + 1 ?>">
                                        </td>
                                        <td class="row-num"><?= $i + 1 ?></td>
                                        <td>
                                            <input type="text" name="name[<?= $i ?>]" value="<?= htmlspecialchars($row['name']) ?>" aria-label="Name, row <?= $i + 1 ?>">
                                        </td>
                                        <td>
                                            <input type="email" name="email[<?= $i ?>]" value="<?= htmlspecialchars($row['email']) ?>" aria-label="Email, row <?= $i + 1 ?>">
                                        </td>
                                        <td>
                                            <input type="text" name="company[<?= $i ?>]" value="<?= htmlspecialchars($row['company']) ?>" class="company-input" aria-label="Company, row <?= $i + 1 ?>">
                                        </td>
                                        <td>
                                            <input type="tel" name="mobile[<?= $i ?>]" value="<?= htmlspecialchars($row['mobile'] ?? '') ?>" placeholder="09XXXXXXXXX" inputmode="numeric" aria-label="Mobile, row <?= $i + 1 ?>">
                                        </td>
                                        <td>
                                            <input type="text" name="designation[<?= $i ?>]" value="<?= htmlspecialchars($row['designation'] ?? '') ?>" aria-label="Designation, row <?= $i + 1 ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>

                        <div class="form-actions">
                            <a href="<?= BASE_URL ?>/pages/attendees/upload.php?event_id=<?= $event_id ?>&reset=1"
                               class="btn btn-secondary"
                               data-confirm="Your edits to the preview will be lost." data-confirm-title="Discard this import?" data-confirm-ok="Discard" data-confirm-danger>
                                Upload a different file
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <?= icon('check', ['class' => 'icon-svg icon-sm']) ?> Confirm import (<?= count($parsed_data) ?> attendees)
                            </button>
                        </div>
                    </form>

                <?php endif; ?>

            </div>
        </div>
    </main>
</div>

<script>

const dropZone  = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const fileInfo  = document.getElementById('fileInfo');
const submitBtn = document.getElementById('submitBtn');

if (dropZone && fileInput) {
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) {
            const f = fileInput.files[0];
            fileInfo.textContent = f.name + ' (' + Math.round(f.size / 1024) + ' KB) — ready to upload';
            submitBtn.disabled = false;
        }
    });

    ['dragenter', 'dragover'].forEach(ev => {
        dropZone.addEventListener(ev, e => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });
    });
    ['dragleave', 'drop'].forEach(ev => {
        dropZone.addEventListener(ev, e => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
        });
    });
    dropZone.addEventListener('drop', e => {
        if (e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files;
            fileInput.dispatchEvent(new Event('change'));
        }
    });
}

function applyBulkCompany() {
    const val = document.getElementById('bulkCompany').value.trim();
    if (!val) return;
    document.querySelectorAll('.company-input').forEach(inp => { inp.value = val; });
}

function toggleAll(checkbox) {
    document.querySelectorAll('input[name^="include["]').forEach(cb => {
        cb.checked = checkbox.checked;
        updateRowStyle(cb);
    });
}

function updateRowStyle(cb) {
    const row = cb.closest('tr');
    if (row) row.classList.toggle('unchecked', !cb.checked);
}
</script>

</body>
</html>
