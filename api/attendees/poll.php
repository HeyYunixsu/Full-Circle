<?php

require_once __DIR__ . '/../../core/bootstrap.php';
requireLogin();
header('Content-Type: application/json');

$event_id = (int)($_GET['event_id'] ?? 0);
// Use MySQL's clock everywhere: updated_at is stamped by MySQL, not PHP.
$now      = $conn->query("SELECT NOW() n")->fetch_assoc()['n'];
$since    = $_GET['since'] ?? $now;

if (!$event_id) {
    echo json_encode(['attendees' => [], 'server_time' => $now]);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, full_name, company, status, check_in_time
    FROM attendees
    WHERE event_id = ? AND updated_at >= ?
    ORDER BY check_in_time DESC
");
$stmt->bind_param("is", $event_id, $since);
$stmt->execute();
$result = $stmt->get_result();

$attendees = [];
while ($r = $result->fetch_assoc()) {
    $attendees[] = $r;
}

$stats = getEventStats($event_id);

$company_stats = [];
$cs = $conn->prepare("
    SELECT company, COUNT(*) as total,
           SUM(CASE WHEN status='checked_in' THEN 1 ELSE 0 END) as checked
    FROM attendees WHERE event_id = ? GROUP BY company ORDER BY company
");
$cs->bind_param("i", $event_id);
$cs->execute();
$cs_result = $cs->get_result();
while ($r = $cs_result->fetch_assoc()) $company_stats[] = $r;

echo json_encode([
    'attendees'     => $attendees,
    'stats'         => $stats,
    'company_stats' => $company_stats,
    'server_time'   => $now
]);