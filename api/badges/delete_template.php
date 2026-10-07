<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['super_admin', 'admin']);

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("DELETE FROM badge_templates WHERE id = ?");
$stmt->bind_param("i", $id);
$back = BASE_URL . '/pages/badges/index.php';
if ($stmt->execute()) {
    logActivity('Badge Template Deleted', "#{$id}");
    redirect($back, 'Template deleted.', 'success');
}
redirect($back, 'Delete failed.', 'error');
