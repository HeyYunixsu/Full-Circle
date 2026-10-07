<?php
require_once __DIR__ . '/../../core/bootstrap.php';
requireLogin();
header('Content-Type: application/json');

$templates = [];
$res = $conn->query("SELECT id, template_name, layout_json, is_favorite
                     FROM badge_templates
                     WHERE layout_json IS NOT NULL
                     ORDER BY is_favorite DESC, id DESC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $templates[] = [
            'id'          => (int)$r['id'],
            'name'        => $r['template_name'],
            'is_favorite' => (int)$r['is_favorite'],
            'layout'      => json_decode($r['layout_json'], true),
        ];
    }
}
echo json_encode(['success' => true, 'templates' => $templates]);
