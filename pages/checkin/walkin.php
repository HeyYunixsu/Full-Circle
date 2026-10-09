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

// Same list as the event page: companies set up for the event plus those in the attendee list, most registered first
$companies = array_column(companyCapacity($event_id), 'name');

// Attendees of this event with the same name (spaces and capitals ignored) -- pre-registered under another email, for example
function sameNameAttendees($event_id, $name) {
    global $conn;
    $name = preg_replace('/\s+/u', ' ', trim($name));
    $s = $conn->prepare("SELECT id, full_name, company, email, status, check_in_time FROM attendees
                         WHERE event_id = ? AND REGEXP_REPLACE(TRIM(full_name), '[[:space:]]+', ' ') = ? LIMIT 5");
    $s->bind_param("is", $event_id, $name);
    $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}
// j***@gmail.com: enough for staff to recognise the person without showing the whole address
function maskEmail($email) {
    [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');
    return mb_substr($user, 0, 1) . '***@' . $domain;
}

$error = '';
$same_name = [];
$closed = checkinClosedReason($event);   // walk-ins are checked in on the spot, so the check-in rule applies

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$closed && ($use_id = (int)($_POST['use_existing'] ?? 0))) {
    // "Yes, it's them": check in the person already on the list instead of registering them twice
    $s = $conn->prepare("SELECT id, full_name, status FROM attendees WHERE id = ? AND event_id = ?");
    $s->bind_param("ii", $use_id, $event_id);
    $s->execute();
    if ($row = $s->get_result()->fetch_assoc()) {
        $msg = $row['full_name'] . ' was already checked in.';
        if ($row['status'] !== 'checked_in') {
            $u = $conn->prepare("UPDATE attendees SET status = 'checked_in', check_in_time = NOW() WHERE id = ? AND status <> 'checked_in'");
            $u->bind_param("i", $use_id);
            $u->execute();
            logActivity('Check-in', 'Checked in: ' . $row['full_name'] . ' (at the walk-in desk, already on the list)');
            markOngoingIfEventDay($event);
            $msg = 'Checked in ' . $row['full_name'] . '. They were already on the list, so no new registration was made.';
        }
        redirect(BASE_URL . '/pages/checkin/badge.php?attendee_id=' . $use_id, $msg);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$closed) {
    $full_name     = capitalizeWords($_POST['full_name'] ?? '');
    $email         = sanitize($_POST['email'] ?? '');
    $company       = capitalizeWords($_POST['company'] ?? '');       // pick from the list or type a new one
    $designation   = capitalizeWords($_POST['designation'] ?? '') ?: null;   // optional
    $mobile_raw    = sanitize($_POST['mobile_number'] ?? '');
    $send_sms      = isset($_POST['send_sms']);

    $mobile = '';
    if ($mobile_raw !== '') {
        $n = normalizePHMobile($mobile_raw);
        if ($n) $mobile = '0' . substr($n, 2);
    }

    $duplicate = false;
    if (empty($full_name) || empty($email) || empty($company) || empty($mobile_raw)) {
        $error = 'Please fill in the name, email, mobile number and company.';
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
            $duplicate = true;
        } elseif (empty($_POST['confirm_new']) && ($same_name = sameNameAttendees($event_id, $full_name))) {
            // Nothing is saved yet: the form shows "Is this the same person?" with both choices
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
                markOngoingIfEventDay($event);

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

                logActivity('Walk-in Registered', "{$full_name} (" . implode(' — ', array_filter([$company, $designation])) . ")");
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
    .alert a { font-weight: 600; text-decoration: underline; }
    .dupe-box { border: 1px solid rgba(245, 165, 36, .45); background: rgba(245, 165, 36, .08); border-radius: 12px; padding: 16px; margin-bottom: 20px; }
    .dupe-box strong { color: var(--color-text-strong); }
    .dupe-box > p { font-size: 14px; margin: 4px 0 12px; }
    .dupe-list { list-style: none; margin: 0 0 12px; padding: 0; display: grid; gap: 8px; }
    .dupe-list li { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 10px; padding: 10px 12px; }
    .dupe-list span { display: block; font-size: 13px; color: var(--color-text-muted); }
</style>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/mobile.css?v=<?= ASSET_VER ?>" media="(max-width: 768px)">
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php $back_url = BASE_URL . '/pages/checkin/index.php?event_id=' . (int)$event_id; $back_label = 'Back to Check-In'; include __DIR__ . '/../../includes/header.php'; ?>


        <div class="narrow">
            <div class="panel">
                <div class="form-head">
                    <div class="kicker">Registering for</div>
                    <h3><?= htmlspecialchars($event['event_name']) ?></h3>
                    <p>They are checked in as soon as you register, and their badge opens next.</p>
                </div>

                <?php if ($closed): ?>
                    <div class="empty-state">
                        <?= icon('calendar', ['class' => 'icon-svg']) ?>
                        <strong>Check-in is closed</strong>
                        <p><?= htmlspecialchars($closed) ?></p>
                        <a class="btn-sm light" href="<?= BASE_URL ?>/pages/events/view.php?id=<?= (int)$event_id ?>">Go to event page</a>
                    </div>
                <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert alert-error" role="alert"><?= icon('alert', ['class' => 'icon-svg icon-sm']) ?> <span><?= htmlspecialchars($error) ?>
                        <?php if (!empty($duplicate)): ?> <a href="<?= BASE_URL ?>/pages/attendees/index.php?event_id=<?= (int)$event_id ?>&search=<?= urlencode($email) ?>">Find them in Attendees</a><?php endif; ?></span></div>
                <?php endif; ?>

                <!-- autocomplete off: on a shared check-in laptop the browser would suggest the previous guest's details -->
                <form method="POST" autocomplete="off">
                    <?php if ($same_name): ?>
                    <div class="dupe-box" role="alert">
                        <strong>Is this the same person?</strong>
                        <p><?= count($same_name) === 1 ? 'Someone with this name is' : 'People with this name are' ?> already on the list for this event. Check them in instead, so they are not counted twice.</p>
                        <ul class="dupe-list">
                            <?php foreach ($same_name as $m): ?>
                            <li>
                                <div>
                                    <b><?= htmlspecialchars($m['full_name']) ?></b>
                                    <span><?= htmlspecialchars($m['company']) ?> &middot; <?= htmlspecialchars(maskEmail($m['email'])) ?> &middot;
                                        <?= $m['status'] === 'checked_in' ? 'Checked in ' . date('g:i A', strtotime($m['check_in_time'])) : 'Not checked in yet' ?></span>
                                </div>
                                <button type="submit" name="use_existing" value="<?= (int)$m['id'] ?>" class="btn-sm" formnovalidate>
                                    <?= $m['status'] === 'checked_in' ? 'Yes, open their badge' : 'Yes, check them in' ?>
                                </button>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="submit" name="confirm_new" value="1" class="btn-sm light">No, register a new person</button>
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="full_name">Full name</label>
                        <input type="text" name="full_name" id="full_name" class="form-input" required autofocus value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" placeholder="e.g. Juan Dela Cruz">
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" name="email" id="email" class="form-input" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="name@company.com">
                        </div>
                        <div class="form-group">
                            <label for="mobile_number">Mobile number</label>
                            <input type="tel" name="mobile_number" id="mobile_number" class="form-input" required value="<?= htmlspecialchars($_POST['mobile_number'] ?? '') ?>" placeholder="09XX XXX XXXX" inputmode="tel" aria-describedby="mobile-help">
                            <div class="form-help" id="mobile-help">Philippine number, e.g. 0917 123 4567</div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="company">Company</label>
                            <input type="text" name="company" id="company" class="form-input" required list="company-list" value="<?= htmlspecialchars($_POST['company'] ?? '') ?>" placeholder="Choose or type a company" aria-describedby="company-help">
                            <datalist id="company-list">
                                <?php foreach ($companies as $c): ?><option value="<?= htmlspecialchars($c) ?>"></option><?php endforeach; ?>
                            </datalist>
                            <div class="form-help" id="company-help">Start typing to pick one of this event's companies, or enter a new one.</div>
                        </div>
                        <div class="form-group">
                            <label for="designation">Designation <span class="optional">(optional)</span></label>
                            <input type="text" name="designation" id="designation" class="form-input" value="<?= htmlspecialchars($_POST['designation'] ?? '') ?>" placeholder="e.g. IT Manager">
                        </div>
                    </div>

                    <?php if (smsIsConfigured()): ?>
                    <label class="check">
                        <input type="checkbox" name="send_sms" value="1" <?= isset($_POST['send_sms']) || $_SERVER['REQUEST_METHOD'] !== 'POST' ? 'checked' : '' ?>>
                        Text their QR code link to this number<?= smsIsMock() ? ' <span class="optional">(test mode: no real text is sent)</span>' : '' ?>
                    </label>
                    <?php endif; ?>

                    <div class="form-actions">
                        <a href="<?= BASE_URL ?>/pages/checkin/index.php?event_id=<?= $event_id ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><?= icon('ticket', ['class' => 'icon-svg icon-sm']) ?> Register &amp; Generate Badge</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

</body>
</html>
