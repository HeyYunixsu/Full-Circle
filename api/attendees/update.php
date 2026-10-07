<?php

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../includes/sms.php';
requireLogin();
header('Content-Type: application/json');

function out($ok, $msg, $extra = []) {
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(false, 'Invalid request method.');

$id = (int)($_POST['id'] ?? 0);
if (!$id) out(false, 'Missing attendee id.');

$stmt = $conn->prepare("SELECT a.*, e.status AS event_status FROM attendees a
                        JOIN events e ON e.id = a.event_id WHERE a.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$a = $stmt->get_result()->fetch_assoc();

if (!$a) out(false, 'Attendee not found.');

if ($a['event_status'] === 'archived') {
    out(false, 'This event is archived. Attendee records are locked.');
}

$full_name   = capitalizeWords($_POST['full_name']     ?? $a['full_name']);
$email       = trim($_POST['email']         ?? $a['email']);
$company     = capitalizeWords($_POST['company']       ?? $a['company']);
$designation = capitalizeWords($_POST['designation']   ?? ($a['designation'] ?? ''));
$mobile_raw  = trim($_POST['mobile_number'] ?? ($a['mobile_number'] ?? ''));

if ($full_name === '') out(false, 'Full name cannot be empty.');
if ($company === '')   out(false, 'Company cannot be empty.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(false, 'Invalid email address.');

$mobile = '';
if ($mobile_raw !== '') {
    $n = normalizePHMobile($mobile_raw);
    if (!$n) out(false, 'Invalid PH mobile number. Use 09XXXXXXXXX.');
    $mobile = '0' . substr($n, 2);
}

if (strcasecmp($email, $a['email']) !== 0) {
    $dup = $conn->prepare("SELECT id FROM attendees WHERE event_id = ? AND email = ? AND id != ?");
    $dup->bind_param("isi", $a['event_id'], $email, $id);
    $dup->execute();
    if ($dup->get_result()->num_rows > 0) {
        out(false, 'Another attendee in this event already uses that email.');
    }
}

$changes = [];
$compare = [
    'full_name'     => [$a['full_name'], $full_name],
    'email'         => [$a['email'], $email],
    'company'       => [$a['company'], $company],
    'designation'   => [$a['designation'] ?? '', $designation],
    'mobile_number' => [$a['mobile_number'] ?? '', $mobile],
];
foreach ($compare as $field => [$old, $new]) {
    if ((string)$old !== (string)$new) {
        $changes[] = "{$field}: '" . ($old === '' ? '—' : $old) . "' -> '" . ($new === '' ? '—' : $new) . "'";
    }
}

if (!$changes) out(true, 'No changes to save.', ['attendee' => $a]);

$user_id = (int)($_SESSION['user_id'] ?? 0);
$now     = date('Y-m-d H:i:s');

$upd = $conn->prepare("UPDATE attendees
                       SET full_name = ?, email = ?, company = ?, designation = ?,
                           mobile_number = ?, name_edited_by = ?, name_edited_at = ?
                       WHERE id = ?");
$upd->bind_param("sssssisi", $full_name, $email, $company, $designation, $mobile, $user_id, $now, $id);

if (!$upd->execute()) out(false, 'Database error: ' . $conn->error);

if (strcasecmp($company, $a['company']) !== 0) {
    $cc = $conn->prepare("SELECT id FROM companies WHERE event_id = ? AND company_name = ?");
    $cc->bind_param("is", $a['event_id'], $company);
    $cc->execute();
    if ($cc->get_result()->num_rows === 0) {
        $ac = $conn->prepare("INSERT INTO companies (event_id, company_name) VALUES (?, ?)");
        $ac->bind_param("is", $a['event_id'], $company);
        $ac->execute();
    }
}

logActivity('Attendee Edited', "#{$id} ({$a['attendee_code']}): " . implode(' | ', $changes));

$a['full_name']     = $full_name;
$a['email']         = $email;
$a['company']       = $company;
$a['designation']   = $designation;
$a['mobile_number'] = $mobile;

out(true, 'Attendee updated.', ['attendee' => $a, 'changes' => $changes]);
