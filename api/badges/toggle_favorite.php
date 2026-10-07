<?php
require_once __DIR__ . '/../../core/bootstrap.php';
requireRole(['super_admin', 'admin', 'staff']);
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo json_encode(['success' => false]); exit; }

$stmt = $conn->prepare("UPDATE badge_templates SET is_favorite = 1 - is_favorite WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

$q = $conn->prepare("SELECT is_favorite FROM badge_templates WHERE id = ?");
$q->bind_param("i", $id);
$q->execute();
$row = $q->get_result()->fetch_assoc();

echo json_encode(['success' => true, 'is_favorite' => (int)($row['is_favorite'] ?? 0)]);
