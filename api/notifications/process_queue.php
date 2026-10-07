<?php

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../includes/mailer.php';

header('Content-Type: application/json');

if (php_sapi_name() !== 'cli') {
    requireLogin();
}

$event_id   = (int)($_GET['event_id'] ?? 0);
$batch_size = max(1, min(100, (int)($_GET['batch'] ?? 25)));

if (!$event_id) {
    echo json_encode(['error' => 'event_id required']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) {
    echo json_encode(['error' => 'Event not found']);
    exit;
}

set_time_limit(120);
ignore_user_abort(true);

$stmt = $conn->prepare("
    SELECT eq.id AS queue_id, eq.attendee_id, eq.subject,
           a.email, a.full_name, a.company, a.attendee_code, a.qr_image_path
    FROM email_queue eq
    JOIN attendees a ON a.id = eq.attendee_id
    WHERE a.event_id = ?
      AND eq.email_type = 'qr_code'
      AND eq.status = 'pending'
    ORDER BY eq.id ASC
    LIMIT ?
");
$stmt->bind_param("ii", $event_id, $batch_size);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$sent = 0;
$failed = 0;
$processed = 0;

if (!empty($rows)) {
    
    try {
        $mail = getBulkMailer();
    } catch (Throwable $e) {
        echo json_encode([
            'error' => 'SMTP init failed: ' . $e->getMessage(),
            'processed' => 0, 'sent' => 0, 'failed' => 0,
            'remaining' => count($rows), 'done' => false
        ]);
        exit;
    }

    $mark_sent = $conn->prepare("
        UPDATE email_queue SET status='sent', sent_at=NOW(), error_message=NULL WHERE id=?
    ");
    $mark_failed = $conn->prepare("
        UPDATE email_queue SET status='failed', error_message=? WHERE id=?
    ");
    $mark_attendee = $conn->prepare("UPDATE attendees SET email_sent=TRUE WHERE id=?");

    foreach ($rows as $row) {
        $processed++;

        $attendee = [
            'full_name'     => $row['full_name'],
            'company'       => $row['company'],
            'attendee_code' => $row['attendee_code'],
        ];

        $body    = getQREmailTemplate($attendee, $event);
        $subject = $row['subject'] ?: ("Your QR Code for " . $event['event_name']);
        $qr_path = $row['qr_image_path'];

        $result = sendEmailWithMailer($mail, $row['email'], $row['full_name'], $subject, $body, $qr_path);

        if ($result['success']) {
            $mark_sent->bind_param("i", $row['queue_id']);
            $mark_sent->execute();
            $mark_attendee->bind_param("i", $row['attendee_id']);
            $mark_attendee->execute();
            $sent++;
        } else {
            $err = substr($result['message'], 0, 250);
            $mark_failed->bind_param("si", $err, $row['queue_id']);
            $mark_failed->execute();
            $failed++;
        }
    }

    try { $mail->smtpClose(); } catch (Throwable $e) {}
}

$rem_stmt = $conn->prepare("
    SELECT COUNT(*) AS c
    FROM email_queue eq
    JOIN attendees a ON a.id = eq.attendee_id
    WHERE a.event_id = ? AND eq.email_type='qr_code' AND eq.status='pending'
");
$rem_stmt->bind_param("i", $event_id);
$rem_stmt->execute();
$remaining = (int)$rem_stmt->get_result()->fetch_assoc()['c'];

echo json_encode([
    'processed' => $processed,
    'sent'      => $sent,
    'failed'    => $failed,
    'remaining' => $remaining,
    'done'      => $remaining === 0,
]);
