<?php
// Automated notifications (Objective 3). Used by cron/notify.php (hourly)
// and by the "Send feedback links now" button on the Feedback page.
//
// Every send is logged in email_queue / sms_queue. The logs double as the
// "already sent?" check, so running this many times never sends twice.

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/sms.php';

const AUTO_MAX_TRIES = 3;    // failed sends are retried on later runs, up to this many attempts
const AUTO_BATCH     = 200;  // max messages per type per run

function feedbackLink($qr_code) {
    return rtrim(BASE_URL, '/') . '/pages/feedback/form.php?c=' . urlencode($qr_code);
}

function autoLogEmail($attendee_id, $type, $to, $subject, $body, $result) {
    global $conn;
    $status = $result['success'] ? 'sent' : 'failed';
    $error  = $result['success'] ? null : substr($result['message'], 0, 250);
    $stmt = $conn->prepare("INSERT INTO email_queue (attendee_id, email_type, recipient_email, subject, body, status, sent_at, error_message)
                            VALUES (?, ?, ?, ?, ?, ?, " . ($result['success'] ? 'NOW()' : 'NULL') . ", ?)");
    $stmt->bind_param("issssss", $attendee_id, $type, $to, $subject, $body, $status, $error);
    $stmt->execute();
}

function autoLogSMS($attendee_id, $type, $number, $body, $result) {
    global $conn;
    $status  = $result['success'] ? 'sent' : 'failed';
    $ref     = $result['refs'][0] ?? null;
    $credits = (int)($result['credits'] ?? 0);
    $error   = $result['success'] ? null : substr($result['message'], 0, 250);
    $stmt = $conn->prepare("INSERT INTO sms_queue (attendee_id, sms_type, recipient_number, message, status, provider_ref, credits_used, sent_at, error_message)
                            VALUES (?, ?, ?, ?, ?, ?, ?, " . ($result['success'] ? 'NOW()' : 'NULL') . ", ?)");
    $stmt->bind_param("isssssis", $attendee_id, $type, $number, $body, $status, $ref, $credits, $error);
    $stmt->execute();
}

// SQL condition: attendee has no successful $type message in $table and fewer than AUTO_MAX_TRIES failures.
function notYetSent($table, $type_col, $type) {
    $max = AUTO_MAX_TRIES;
    return "NOT EXISTS (SELECT 1 FROM {$table} q WHERE q.attendee_id = a.id AND q.{$type_col} = '{$type}' AND q.status = 'sent')
            AND (SELECT COUNT(*) FROM {$table} q WHERE q.attendee_id = a.id AND q.{$type_col} = '{$type}' AND q.status = 'failed') < {$max}";
}

function fetchRows($sql, $types, ...$params) {
    global $conn;
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Day-before reminder: email + SMS to everyone registered for tomorrow's events.
function sendDueReminders() {
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $out = ['email_sent' => 0, 'email_failed' => 0, 'sms_sent' => 0, 'sms_failed' => 0];
    $base = "SELECT a.id, a.full_name, a.email, a.mobile_number, a.attendee_code,
                    e.event_name, e.event_date, e.event_time, e.location
             FROM attendees a JOIN events e ON e.id = a.event_id
             WHERE e.event_date = ? AND e.status IN ('upcoming', 'ongoing')";

    $rows = fetchRows("$base AND a.email <> '' AND " . notYetSent('email_queue', 'email_type', 'reminder') . " LIMIT " . AUTO_BATCH, "s", $tomorrow);
    if ($rows) {
        $mail = getBulkMailer();
        foreach ($rows as $a) {
            $subject = 'Reminder: ' . $a['event_name'] . ' is tomorrow';
            $body    = getReminderEmailTemplate($a, $a);
            $res     = sendEmailWithMailer($mail, $a['email'], $a['full_name'], $subject, $body);
            autoLogEmail($a['id'], 'reminder', $a['email'], $subject, $body, $res);
            $out[$res['success'] ? 'email_sent' : 'email_failed']++;
        }
        try { $mail->smtpClose(); } catch (Throwable $e) {}
    }

    if (smsIsConfigured()) {
        $rows = fetchRows("$base AND a.mobile_number <> '' AND " . notYetSent('sms_queue', 'sms_type', 'reminder') . " LIMIT " . AUTO_BATCH, "s", $tomorrow);
        foreach ($rows as $a) {
            $number = normalizePHMobile($a['mobile_number']);
            $body   = getReminderSMSTemplate($a, $a);
            $res    = $number ? sendSMS($number, $body) : ['success' => false, 'message' => 'Invalid PH mobile number'];
            autoLogSMS($a['id'], 'reminder', $number ?: $a['mobile_number'], $body, $res);
            $out[$res['success'] ? 'sms_sent' : 'sms_failed']++;
        }
    }
    return $out;
}

// Feedback link email to checked-in attendees once an event is over
// (marked completed, or its date has passed). Only events from the last 7 days,
// so old events don't get a burst of emails the first time cron runs.
// $event_id: send for that one event now, regardless of date/status (manual button).
function sendFeedbackLinks($event_id = null) {
    $out = ['email_sent' => 0, 'email_failed' => 0];
    $sql = "SELECT a.id, a.full_name, a.email, a.qr_code, e.event_name, e.event_date, e.event_time, e.location
            FROM attendees a JOIN events e ON e.id = a.event_id
            WHERE a.status = 'checked_in' AND a.email <> '' AND a.qr_code IS NOT NULL
              AND " . notYetSent('email_queue', 'email_type', 'feedback');
    if ($event_id) {
        $rows = fetchRows("$sql AND e.id = ? LIMIT " . AUTO_BATCH, "i", $event_id);
    } else {
        $today = date('Y-m-d');
        $since = date('Y-m-d', strtotime('-7 days'));
        $rows = fetchRows("$sql AND e.event_date >= ? AND (e.status = 'completed' OR (e.status IN ('upcoming', 'ongoing') AND e.event_date < ?))
                           LIMIT " . AUTO_BATCH, "ss", $since, $today);
    }
    if (!$rows) return $out;

    $mail = getBulkMailer();
    foreach ($rows as $a) {
        $subject = 'How was ' . $a['event_name'] . '? Share your feedback';
        $body    = getFeedbackEmailTemplate($a, $a, feedbackLink($a['qr_code']));
        $res     = sendEmailWithMailer($mail, $a['email'], $a['full_name'], $subject, $body);
        autoLogEmail($a['id'], 'feedback', $a['email'], $subject, $body, $res);
        $out[$res['success'] ? 'email_sent' : 'email_failed']++;
    }
    try { $mail->smtpClose(); } catch (Throwable $e) {}
    return $out;
}
