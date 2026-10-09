<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/sms.php';
requireLogin();

$event_id  = (int)($_GET['event_id'] ?? 0);
$single_id = (int)($_GET['attendee_id'] ?? 0);
$channel   = $_GET['channel'] ?? 'both'; 
$resend    = isset($_GET['resend']) && $_GET['resend'] == '1';

$do_email = in_array($channel, ['both', 'email']);
$do_sms   = in_array($channel, ['both', 'sms']);

$back = BASE_URL . '/pages/attendees/index.php';
if (!$event_id && !$single_id) {
    redirect($back, 'Invalid request.', 'error');
}

function queueEmail($conn, $id, $email, $subject) {
    $del = $conn->prepare("DELETE FROM email_queue WHERE attendee_id = ? AND email_type = 'qr_code' AND status = 'pending'");
    $del->bind_param("i", $id);
    $del->execute();
    $ins = $conn->prepare("INSERT INTO email_queue (attendee_id, email_type, recipient_email, subject, body, status) VALUES (?, 'qr_code', ?, ?, '', 'pending')");
    $ins->bind_param("iss", $id, $email, $subject);
    return $ins->execute();
}

function sendOneSMS($conn, $a, $event) {
    $number = normalizePHMobile($a['mobile_number'] ?? '');
    if (!$number) {
        $q = $conn->prepare("INSERT INTO sms_queue (attendee_id, sms_type, recipient_number, message, status, error_message) VALUES (?, 'qr_code', ?, '', 'failed', 'Invalid PH mobile number')");
        $bad = $a['mobile_number'] ?? '';
        $q->bind_param("is", $a['id'], $bad);
        $q->execute();
        return ['ok' => false, 'invalid' => true, 'credits' => 0];
    }
    $body = getQRSMSTemplate($a, $event);
    $res  = sendSMS($number, $body);
    $status = $res['success'] ? 'sent' : 'failed';
    $ref    = $res['refs'][0] ?? null;
    $err    = $res['success'] ? null : $res['message'];
    $cred   = $res['credits'];
    $q = $conn->prepare("INSERT INTO sms_queue (attendee_id, sms_type, recipient_number, message, status, provider_ref, credits_used, sent_at, error_message) VALUES (?, 'qr_code', ?, ?, ?, ?, ?, " . ($res['success'] ? 'NOW()' : 'NULL') . ", ?)");
    $q->bind_param("issssis", $a['id'], $number, $body, $status, $ref, $cred, $err);
    $q->execute();
    if ($res['success']) {
        $u = $conn->prepare("UPDATE attendees SET sms_sent = 1 WHERE id = ?");
        $u->bind_param("i", $a['id']);
        $u->execute();
    }
    return ['ok' => $res['success'], 'invalid' => false, 'credits' => $cred, 'msg' => $res['message']];
}

if ($single_id) {
    $stmt = $conn->prepare("SELECT a.*, e.event_name, e.event_date, e.event_time, e.location, e.status AS event_status FROM attendees a JOIN events e ON a.event_id = e.id WHERE a.id = ?");
    $stmt->bind_param("i", $single_id);
    $stmt->execute();
    $a = $stmt->get_result()->fetch_assoc();
    if (!$a) redirect($back, 'Attendee not found.', 'error');
    $back .= '?event_id=' . (int)$a['event_id'];

    $done = [];

    if ($do_email) {
        $subject = "Your QR Code for " . $a['event_name'];
        $body    = getQREmailTemplate($a, $a);
        $res     = sendEmail($a['email'], $a['full_name'], $subject, $body, $a['qr_image_path']);
        if ($res['success']) {
            $u = $conn->prepare("UPDATE attendees SET email_sent = TRUE WHERE id = ?");
            $u->bind_param("i", $single_id); $u->execute();
            $q = $conn->prepare("INSERT INTO email_queue (attendee_id, email_type, recipient_email, subject, body, status, sent_at) VALUES (?, 'qr_code', ?, ?, ?, 'sent', NOW())");
            $q->bind_param("isss", $single_id, $a['email'], $subject, $body); $q->execute();
            $done[] = 'email sent';
        } else {
            $done[] = 'email failed: ' . $res['message'];
        }
    }

    if ($do_sms) {
        $r = sendOneSMS($conn, $a, $a);
        if ($r['ok'])            $done[] = 'SMS sent';
        elseif ($r['invalid'])   $done[] = 'no valid mobile';
        else                     $done[] = 'SMS failed: ' . ($r['msg'] ?? '');
    }

    logActivity('Invite Sent', ucfirst($channel) . ' to ' . $a['full_name'] . ' — ' . implode(', ', $done));
    $failed = (bool)preg_grep('/failed|no valid/i', $done);
    redirect($back, 'QR code for ' . $a['full_name'] . ': ' . implode(', ', $done) . '.', $failed ? 'error' : 'success');
}

$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
if (!$event) redirect(BASE_URL . '/pages/events/index.php', 'Event not found.', 'error');
$back .= '?event_id=' . $event_id;

$emailed = 0; $sms_sent = 0; $sms_invalid = 0; $credits = 0;

if ($do_email) {
    $sql = "SELECT id, full_name, email FROM attendees WHERE event_id = ?";
    if (!$resend) $sql .= " AND email_sent = FALSE";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $rows = $stmt->get_result();
    $subject = "Your QR Code for " . $event['event_name'];
    while ($a = $rows->fetch_assoc()) {
        if (queueEmail($conn, $a['id'], $a['email'], $subject)) $emailed++;
    }
    logActivity('Emails Queued', "Queued: {$emailed} for event #{$event_id}");
}

if ($do_sms) {
    $sql = "SELECT * FROM attendees WHERE event_id = ? AND mobile_number IS NOT NULL AND mobile_number != ''";
    if (!$resend) $sql .= " AND sms_sent = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $rows = $stmt->get_result();
    while ($a = $rows->fetch_assoc()) {
        $r = sendOneSMS($conn, $a, $event);
        if ($r['ok'])          { $sms_sent++; $credits += $r['credits']; }
        elseif ($r['invalid']) { $sms_invalid++; }
        usleep(80000);
    }
    logActivity('Bulk SMS', "Event #{$event_id} — sent: {$sms_sent}, invalid: {$sms_invalid}, credits: {$credits}");
}

if ($do_email && $emailed > 0) {
    $extra = $do_sms ? "&sms_note=" . urlencode("SMS: {$sms_sent} sent" . ($sms_invalid ? ", {$sms_invalid} invalid" : "")) : "";
    header("Location: " . BASE_URL . "/pages/attendees/send_progress.php?event_id={$event_id}&total={$emailed}{$extra}");
    exit;
}

$parts = [];
if ($do_sms)   $parts[] = "SMS: {$sms_sent} sent" . ($sms_invalid ? ", {$sms_invalid} invalid number(s)" : "") . ($credits ? " (~{$credits} credits)" : "");
if ($do_email) $parts[] = "Email: nothing new to send (all already sent — use Resend)";

$msg = $parts ? implode(' | ', $parts) : 'Nothing was sent.';
redirect($back, $msg, ($sms_invalid ? 'info' : 'success'));
