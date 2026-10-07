<?php

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../includes/qrcode.php';
require_once __DIR__ . '/../../includes/sms.php';
requireLogin();

$event_id = (int)($_GET['event_id'] ?? 0);
if (!$event_id) {
    redirect(BASE_URL . '/pages/checkin/index.php', 'Select event first.', 'error');
}

$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) {
    redirect(BASE_URL . '/pages/checkin/index.php', 'Event not found.', 'error');
}

if ($event['status'] === 'archived') {
    redirect(BASE_URL . '/pages/events/view.php?id=' . $event_id,
             'This event is archived. Walk-in registration is closed.', 'error');
}

$companies = [];
$c_result = $conn->query("SELECT DISTINCT company_name FROM companies WHERE event_id = $event_id ORDER BY company_name");
while ($r = $c_result->fetch_assoc()) $companies[] = $r['company_name'];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name     = capitalizeWords($_POST['full_name'] ?? '');
    $email         = sanitize($_POST['email'] ?? '');
    $company       = capitalizeWords($_POST['company'] ?? '');
    $other_company = capitalizeWords($_POST['other_company'] ?? '');
    $designation   = capitalizeWords($_POST['designation'] ?? '');
    $mobile_raw    = sanitize($_POST['mobile_number'] ?? '');
    $send_sms      = isset($_POST['send_sms']);

    if ($company === 'Others' && !empty($other_company)) {
        $company = $other_company;
    }

    $mobile = '';
    if ($mobile_raw !== '') {
        $n = normalizePHMobile($mobile_raw);
        if ($n) $mobile = '0' . substr($n, 2);
    }

    if (empty($full_name) || empty($email) || empty($company) || empty($designation) || empty($mobile_raw)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif ($mobile === '') {
        $error = 'Invalid mobile number. Use 09XXXXXXXXX format.';
    } else {
        $check = $conn->prepare("SELECT id FROM attendees WHERE event_id=? AND email=?");
        $check->bind_param("is", $event_id, $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'This email is already registered for this event.';
        } else {
            $attendee_code = generateAttendeeCode($event_id);
            $qr_data = generateQRData($attendee_code);
            $qr_path = saveQRCode($qr_data, $attendee_code . '_' . time(), 300);

            $sql = "INSERT INTO attendees
                    (event_id, attendee_code, full_name, email, mobile_number, company, designation,
                     qr_code, qr_image_path, registration_type, status, check_in_time)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'walk-in', 'checked_in', NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssssss", $event_id, $attendee_code, $full_name, $email,
                              $mobile, $company, $designation, $qr_data, $qr_path);

            if ($stmt->execute()) {
                $new_id = $conn->insert_id;

                $cc = $conn->prepare("SELECT id FROM companies WHERE event_id=? AND company_name=?");
                $cc->bind_param("is", $event_id, $company);
                $cc->execute();
                if ($cc->get_result()->num_rows === 0) {
                    $ac = $conn->prepare("INSERT INTO companies (event_id, company_name) VALUES (?, ?)");
                    $ac->bind_param("is", $event_id, $company);
                    $ac->execute();
                }

                $sms_note = '';
                if ($send_sms && smsIsConfigured()) {
                    $att = ['full_name' => $full_name, 'attendee_code' => $attendee_code];
                    $body = getQRSMSTemplate($att, $event);
                    $res  = sendSMS(normalizePHMobile($mobile), $body);

                    $status = $res['success'] ? 'sent' : 'failed';
                    $ref    = $res['refs'][0] ?? null;
                    $err    = $res['success'] ? null : $res['message'];
                    $cred   = $res['credits'];
                    $type   = 'qr_code';
                    $num    = normalizePHMobile($mobile);

                    $q = $conn->prepare("INSERT INTO sms_queue
                        (attendee_id, sms_type, recipient_number, message, status, provider_ref, credits_used, sent_at, error_message)
                        VALUES (?, ?, ?, ?, ?, ?, ?, " . ($res['success'] ? 'NOW()' : 'NULL') . ", ?)");
                    $q->bind_param("isssssis", $new_id, $type, $num, $body, $status, $ref, $cred, $err);
                    $q->execute();

                    if ($res['success']) {
                        $u = $conn->prepare("UPDATE attendees SET sms_sent = 1 WHERE id = ?");
                        $u->bind_param("i", $new_id);
                        $u->execute();
                        $sms_note = ' SMS sent.';
                    } else {
                        $sms_note = ' SMS failed: ' . $res['message'];
                    }
                }

                logActivity('Walk-in Registered', "{$full_name} ({$company} — {$designation})");
                redirect(BASE_URL . '/pages/checkin/badge.php?attendee_id=' . $new_id,
                         'Walk-in registered!' . $sms_note);
            } else {
                $error = 'Registration failed: ' . $conn->error;
            }
        }
    }
}

$page_title = 'Walk-in Registration';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $page_title ?> &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<style>
    #other_company_group { display: none; }
    .walkin-head { margin-bottom: 18px; }
    .walkin-head h3 { margin-bottom: 2px; }
    .walkin-head p { font-size: 13px; color: var(--color-text-muted); }
</style>
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php $back_url = BASE_URL . '/pages/checkin/index.php?event_id=' . (int)$event_id; $back_label = 'Back to Check-In'; include __DIR__ . '/../../includes/header.php'; ?>


        <div class="narrow">
            <div class="panel">
                <div class="walkin-head">
                    <h3><span class="panel-title"><?= icon('walk', ['class' => 'icon-svg icon-md']) ?> Walk-in Registration</span></h3>
                    <p><?= htmlspecialchars($event['event_name']) ?> &middot; the attendee is checked in immediately and gets a badge.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error" role="alert"><?= icon('alert', ['class' => 'icon-svg icon-sm']) ?> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="company">Company Name *</label>
                        <select name="company" id="company" class="form-input" required onchange="toggleOther(this)">
                            <option value="">-- Select Company --</option>
                            <?php foreach ($companies as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= (($_POST['company'] ?? '') === $c) ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                            <option value="Others" <?= (($_POST['company'] ?? '') === 'Others') ? 'selected' : '' ?>>Others</option>
                        </select>
                    </div>

                    <div class="form-group" id="other_company_group">
                        <label for="other_company">Other Company Name</label>
                        <input type="text" name="other_company" id="other_company" class="form-input" placeholder="Type company name" value="<?= htmlspecialchars($_POST['other_company'] ?? '') ?>">
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="full_name">Full Name *</label>
                            <input type="text" name="full_name" id="full_name" class="form-input" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" placeholder="e.g. Juan Dela Cruz">
                        </div>
                        <div class="form-group">
                            <label for="designation">Designation *</label>
                            <input type="text" name="designation" id="designation" class="form-input" required value="<?= htmlspecialchars($_POST['designation'] ?? '') ?>" placeholder="e.g. IT Manager">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" name="email" id="email" class="form-input" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="email@example.com">
                        </div>
                        <div class="form-group">
                            <label for="mobile_number">Mobile Number *</label>
                            <input type="tel" name="mobile_number" id="mobile_number" class="form-input" required value="<?= htmlspecialchars($_POST['mobile_number'] ?? '') ?>" placeholder="09XXXXXXXXX" inputmode="numeric" aria-describedby="mobile-help">
                            <div class="form-help" id="mobile-help">Globe, Smart, Sun, DITO &mdash; 09XXXXXXXXX or +639XXXXXXXXX</div>
                        </div>
                    </div>

                    <?php if (smsIsConfigured()): ?>
                    <label class="check">
                        <input type="checkbox" name="send_sms" value="1" <?= isset($_POST['send_sms']) || $_SERVER['REQUEST_METHOD'] !== 'POST' ? 'checked' : '' ?>>
                        Send confirmation SMS to this number
                    </label>
                    <?php endif; ?>

                    <div class="form-actions">
                        <a href="<?= BASE_URL ?>/pages/checkin/index.php?event_id=<?= $event_id ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><?= icon('ticket', ['class' => 'icon-svg icon-sm']) ?> Register &amp; Generate Badge</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
function toggleOther(select) {
    document.getElementById('other_company_group').style.display = select.value === 'Others' ? 'block' : 'none';
}
toggleOther(document.querySelector('select[name="company"]'));
</script>
</body>
</html>
