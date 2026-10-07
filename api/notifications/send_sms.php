<?php

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../includes/sms.php';
requireLogin();

$event_id  = (int)($_GET['event_id'] ?? 0);
$single_id = (int)($_GET['attendee_id'] ?? 0);
$resend    = isset($_GET['resend']) && $_GET['resend'] == '1';
$type      = ($_GET['type'] ?? 'qr_code') === 'reminder' ? 'reminder' : 'qr_code';

$back = BASE_URL . '/pages/attendees/index.php';

if (!smsIsConfigured()) {
    redirect($back, 'SMS is not set up yet. Add SEMAPHORE_API_KEY in config.php.', 'error');
}
if (!$event_id && !$single_id) {
    redirect($back, 'Invalid request.', 'error');
}

function buildSMSBody($type, $attendee, $event) {
    return $type === 'reminder'
        ? getReminderSMSTemplate($attendee, $event)
        : getQRSMSTemplate($attendee, $event);
}

function logSMS($conn, $attendee_id, $type, $number, $body, $status, $ref = null, $credits = 0, $error = null) {
    $sql = "INSERT INTO sms_queue (attendee_id, sms_type, recipient_number, message, status, provider_ref, credits_used, sent_at, error_message)
            VALUES (?, ?, ?, ?, ?, ?, ?, " . ($status === 'sent' ? 'NOW()' : 'NULL') . ", ?)";
    $s = $conn->prepare($sql);
    $s->bind_param("isssssis", $attendee_id, $type, $number, $body, $status, $ref, $credits, $error);
    $s->execute();
}

if ($single_id) {
    $stmt = $conn->prepare("SELECT a.*, e.event_name, e.event_date, e.event_time, e.location
                            FROM attendees a JOIN events e ON a.event_id = e.id WHERE a.id = ?");
    $stmt->bind_param("i", $single_id);
    $stmt->execute();
    $a = $stmt->get_result()->fetch_assoc();

    if (!$a) redirect($back, 'Attendee not found.', 'error');

    $back .= '?event_id=' . (int)$a['event_id'];

    $number = normalizePHMobile($a['mobile_number'] ?? '');
    if (!$number) {
        redirect($back, 'No valid mobile number for ' . $a['full_name'] . '.', 'error');
    }

    $body   = buildSMSBody($type, $a, $a);
    $result = sendSMS($number, $body);

    if ($result['success']) {
        $upd = $conn->prepare("UPDATE attendees SET sms_sent = 1 WHERE id = ?");
        $upd->bind_param("i", $single_id);
        $upd->execute();

        logSMS($conn, $single_id, $type, $number, $body, 'sent', $result['refs'][0] ?? null, $result['credits']);
        logActivity('SMS Sent', 'SMS to ' . $number . ' (' . $a['full_name'] . ')');
        redirect($back, 'SMS sent to ' . $a['full_name'] . '.', 'success');
    }

    logSMS($conn, $single_id, $type, $number, $body, 'failed', null, 0, $result['message']);
    redirect($back, 'SMS failed: ' . $result['message'], 'error');
}

$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) redirect(BASE_URL . '/pages/events/index.php', 'Event not found.', 'error');

$back .= '?event_id=' . $event_id;

$sql = "SELECT * FROM attendees WHERE event_id = ? AND mobile_number IS NOT NULL AND mobile_number != ''";
if (!$resend && $type === 'qr_code') $sql .= " AND sms_sent = 0";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$rows = $stmt->get_result();

if ($rows->num_rows === 0) {
    redirect($back, 'No attendees with a mobile number need an SMS. (Use Resend to send again.)', 'info');
}

$sent = 0; $failed = 0; $invalid = 0; $credits = 0; $errors = [];

while ($a = $rows->fetch_assoc()) {
    $number = normalizePHMobile($a['mobile_number']);
    if (!$number) {
        $invalid++;
        logSMS($conn, $a['id'], $type, $a['mobile_number'], '', 'failed', null, 0, 'Invalid PH mobile number format');
        continue;
    }

    $body   = buildSMSBody($type, $a, $event);
    $result = sendSMS($number, $body);

    if ($result['success']) {
        $sent++;
        $credits += $result['credits'];
        logSMS($conn, $a['id'], $type, $number, $body, 'sent', $result['refs'][0] ?? null, $result['credits']);
        if ($type === 'qr_code') {
            $u = $conn->prepare("UPDATE attendees SET sms_sent = 1 WHERE id = ?");
            $u->bind_param("i", $a['id']);
            $u->execute();
        }
    } else {
        $failed++;
        if (count($errors) < 3) $errors[] = $result['message'];
        logSMS($conn, $a['id'], $type, $number, $body, 'failed', null, 0, $result['message']);
    }

    usleep(120000); 
}

logActivity('Bulk SMS', "Event #{$event_id} — sent: {$sent}, failed: {$failed}, invalid: {$invalid}, credits: {$credits}");

$msg = "SMS blast done — {$sent} sent";
if ($failed)  $msg .= ", {$failed} failed";
if ($invalid) $msg .= ", {$invalid} invalid number(s)";
$msg .= ". Approx {$credits} credit(s) used.";
if ($errors)  $msg .= ' First error: ' . $errors[0];

redirect($back, $msg, $failed || $invalid ? 'info' : 'success');
