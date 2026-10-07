<?php

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../includes/excel_parser.php';
requireLogin();

$event_id = (int)($_GET['event_id'] ?? 0);
if (!$event_id) {
    redirect(BASE_URL . '/pages/attendees/index.php', 'Invalid event.', 'error');
}

$stmt = $conn->prepare("SELECT event_name FROM events WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

$filename = ($event ? str_replace(' ', '_', $event['event_name']) : 'attendees') . '_' . date('Y-m-d') . '.csv';

exportAttendeesCSV($event_id, $filename);
