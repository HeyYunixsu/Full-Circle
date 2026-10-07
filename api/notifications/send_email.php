<?php

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../includes/mailer.php';
requireLogin();

$event_id  = (int)($_GET['event_id'] ?? 0);
$single_id = (int)($_GET['attendee_id'] ?? 0);
$resend    = isset($_GET['resend']) && $_GET['resend'] == '1';

if (!$event_id && !$single_id) {
    redirect(BASE_URL . '/pages/attendees/index.php', 'Invalid request.', 'error');
}

if ($single_id) {
    $stmt = $conn->prepare("SELECT a.*, e.* FROM attendees a JOIN events e ON a.event_id = e.id WHERE a.id = ?");
    $stmt->bind_param("i", $single_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if (!$data) {
        redirect(BASE_URL . '/pages/attendees/index.php', 'Attendee not found.', 'error');
    }

    $event_id = $data['event_id'];

    $subject = "Your QR Code for " . $data['event_name'];
    $body    = getQREmailTemplate($data, $data);
    $qr_path = $data['qr_image_path'];

    $result = sendEmail($data['email'], $data['full_name'], $subject, $body, $qr_path);

    if ($result['success']) {
        $upd = $conn->prepare("UPDATE attendees SET email_sent = TRUE WHERE id = ?");
        $upd->bind_param("i", $single_id);
        $upd->execute();

        $q = $conn->prepare("INSERT INTO email_queue (attendee_id, email_type, recipient_email, subject, body, status, sent_at) VALUES (?, 'qr_code', ?, ?, ?, 'sent', NOW())");
        $q->bind_param("isss", $single_id, $data['email'], $subject, $body);
        $q->execute();

        logActivity('Email Sent', 'QR code emailed to: ' . $data['email']);
        redirect(BASE_URL . '/pages/attendees/index.php?event_id=' . $event_id, 'Email sent successfully to ' . $data['full_name'] . '.', 'success');
    } else {
        $q = $conn->prepare("INSERT INTO email_queue (attendee_id, email_type, recipient_email, subject, body, status, error_message) VALUES (?, 'qr_code', ?, ?, ?, 'failed', ?)");
        $q->bind_param("issss", $single_id, $data['email'], $subject, $body, $result['message']);
        $q->execute();

        redirect(BASE_URL . '/pages/attendees/index.php?event_id=' . $event_id, 'Failed: ' . $result['message'], 'error');
    }
}

$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) {
    redirect(BASE_URL . '/pages/events/index.php', 'Event not found.', 'error');
}

$sql = "SELECT id, full_name, email FROM attendees WHERE event_id = ?";
if (!$resend) {
    $sql .= " AND email_sent = FALSE";
}
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirect(
        BASE_URL . '/pages/attendees/index.php?event_id=' . $event_id,
        'No attendees need emails. All already sent. (Use Resend to send again.)',
        'info'
    );
}

$clear = $conn->prepare("
    DELETE eq FROM email_queue eq
    JOIN attendees a ON a.id = eq.attendee_id
    WHERE a.event_id = ? AND eq.email_type = 'qr_code' AND eq.status = 'pending'
");
$clear->bind_param("i", $event_id);
$clear->execute();

$queued = 0;
$ins = $conn->prepare("
    INSERT INTO email_queue (attendee_id, email_type, recipient_email, subject, body, status)
    VALUES (?, 'qr_code', ?, ?, '', 'pending')
");

$subject_template = "Your QR Code for " . $event['event_name'];

while ($a = $result->fetch_assoc()) {
    $ins->bind_param("iss", $a['id'], $a['email'], $subject_template);
    if ($ins->execute()) {
        $queued++;
    }
}

logActivity('Emails Queued', "Queued: {$queued} for event #{$event_id}");

header("Location: " . BASE_URL . "/pages/attendees/send_progress.php?event_id={$event_id}&total={$queued}");
exit;
