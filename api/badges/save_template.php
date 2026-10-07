<?php
require_once __DIR__ . '/../../core/bootstrap.php';
requireRole(['super_admin', 'admin', 'staff']);
header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['elements'])) {
    echo json_encode(['success' => false, 'message' => 'No layout data.']);
    exit;
}

$name    = trim($data['template_name'] ?? 'My Badge Template');
$layout  = json_encode([
    'elements' => $data['elements'],
    'bg'       => $data['bg'] ?? ['color' => '#ffffff'],
    'size'     => $data['size'] ?? ['w' => 360, 'h' => 225],
]);
$user_id = currentUserId();
$tpl_id  = (int)($data['id'] ?? 0);

if ($tpl_id > 0) {
    $stmt = $conn->prepare("UPDATE badge_templates SET template_name=?, layout_json=? WHERE id=?");
    $stmt->bind_param("ssi", $name, $layout, $tpl_id);
    $ok = $stmt->execute();
    $id = $tpl_id;
} else {
    $stmt = $conn->prepare("INSERT INTO badge_templates (event_id, template_name, layout_json, is_active, created_by) VALUES (NULL, ?, ?, 0, ?)");
    $stmt->bind_param("ssi", $name, $layout, $user_id);
    $ok = $stmt->execute();
    $id = $conn->insert_id;
}

if ($ok) {
    logActivity('Badge Template Saved', $name . ' (#' . $id . ')');
    echo json_encode(['success' => true, 'message' => 'Template saved!', 'id' => $id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Save failed: ' . $conn->error]);
}
