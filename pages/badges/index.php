<?php
require_once __DIR__ . '/../../core/bootstrap.php';
requireRole(['super_admin', 'admin', 'staff']);

$templates = [];
$res = $conn->query("SELECT * FROM badge_templates WHERE layout_json IS NOT NULL
                     ORDER BY is_favorite DESC, id DESC");
if ($res) while ($r = $res->fetch_assoc()) $templates[] = $r;

$can_delete = in_array(currentRole(), ['super_admin', 'admin']);
$flash = getFlashMessage();
$page_title = 'Badge Templates';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Badge Templates &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<style>
    .bt-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }
    .bt-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; position: relative; display: flex; flex-direction: column; transition: border-color var(--dur-fast); }
    .bt-card:hover { border-color: var(--magenta); }
    .bt-fav { position: absolute; top: 10px; right: 10px; width: 34px; height: 34px; border-radius: 50%; background: var(--color-surface); border: 1px solid var(--color-border); cursor: pointer; display: grid; place-items: center; color: var(--color-text-faint); z-index: 5; transition: color var(--dur-fast), border-color var(--dur-fast); }
    .bt-fav:hover { color: var(--warning); border-color: var(--warning); }
    .bt-fav.on { color: var(--warning); }
    .bt-fav.on .icon-svg { fill: var(--warning); }
    .bt-preview { height: 150px; background-color: var(--color-bg); background-image: radial-gradient(rgba(92, 34, 73, .14) 1px, transparent 1px); background-size: 16px 16px; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; border-bottom: 1px solid var(--color-border); }
    .bt-mini { width: 200px; height: 125px; border-radius: 8px; box-shadow: var(--shadow-md); position: relative; overflow: hidden; transform: scale(.85); }
    .bt-info { padding: 14px 16px 0; }
    .bt-name { font-size: 15px; font-weight: 700; color: var(--color-text-strong); margin-bottom: 2px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .bt-meta { font-size: 12px; color: var(--color-text-muted); }
    .bt-actions { display: flex; gap: 8px; padding: 14px 16px 16px; margin-top: auto; }
    .bt-actions .btn-sm { flex: 1; }
</style>
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?>"><?= icon($flash['type'] === 'success' ? 'check' : 'alert', ['class' => 'icon-svg icon-sm']) ?> <?= $flash['message'] ?></div>
        <?php endif; ?>

        <div class="page-intro">
            <div>
                <h2>Badge Templates</h2>
                <p>Saved designs &middot; <?= count($templates) ?> template<?= count($templates) === 1 ? '' : 's' ?>. Favorites stay on top and are offered first at check-in.</p>
            </div>
            <a href="<?= BASE_URL ?>/pages/badges/editor.php" class="btn-sm"><?= icon('plus', ['class' => 'icon-svg icon-sm']) ?> New Badge Design</a>
        </div>

        <?php if (empty($templates)): ?>
            <div class="empty-state">
                <?= icon('ticket', ['class' => 'icon-svg']) ?>
                <strong>No saved templates yet</strong>
                <p>Create a badge design, save it, and it will appear here. Favorite it to keep it at the top.</p>
                <a href="<?= BASE_URL ?>/pages/badges/editor.php" class="btn-sm"><?= icon('plus', ['class' => 'icon-svg icon-sm']) ?> Open the designer</a>
            </div>
        <?php else: ?>
            <div class="bt-grid">
                <?php foreach ($templates as $t):
                    $layout = json_decode($t['layout_json'], true);
                    $bg = $layout['bg']['color'] ?? '#ffffff';
                ?>
                    <div class="bt-card" id="tpl-card-<?= $t['id'] ?>">
                        <button class="bt-fav <?= $t['is_favorite'] ? 'on' : '' ?>" onclick="toggleFav(<?= $t['id'] ?>, this)" title="<?= $t['is_favorite'] ? 'Remove from favorites' : 'Mark as favorite' ?>" aria-label="<?= $t['is_favorite'] ? 'Remove from favorites' : 'Mark as favorite' ?>" aria-pressed="<?= $t['is_favorite'] ? 'true' : 'false' ?>">
                            <?= icon('star', ['class' => 'icon-svg icon-sm']) ?>
                        </button>
                        <div class="bt-preview">
                            <div class="bt-mini" id="mini-<?= $t['id'] ?>" style="background:<?= htmlspecialchars($bg) ?>;"></div>
                        </div>
                        <div class="bt-info">
                            <div class="bt-name">
                                <?= htmlspecialchars($t['template_name']) ?>
                                <?php if ($t['is_favorite']): ?><span class="pill-warn">Favorite</span><?php endif; ?>
                            </div>
                            <div class="bt-meta"><?= count($layout['elements'] ?? []) ?> elements &middot; <?= date('M j, Y', strtotime($t['created_at'])) ?></div>
                        </div>
                        <div class="bt-actions">
                            <a class="btn-sm light" href="<?= BASE_URL ?>/pages/badges/editor.php?id=<?= $t['id'] ?>"><?= icon('edit', ['class' => 'icon-svg icon-sm']) ?> Edit</a>
                            <?php if ($can_delete): ?>
                                <a class="btn-sm danger" href="<?= BASE_URL ?>/api/badges/delete_template.php?id=<?= $t['id'] ?>" data-confirm="This badge design will be permanently removed." data-confirm-title="Delete template?" data-confirm-ok="Delete" data-confirm-danger>Delete</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
const MINI_DATA = <?= json_encode(array_map(function($t){
    $l = json_decode($t['layout_json'], true);
    return ['id'=>(int)$t['id'], 'layout'=>$l];
}, $templates)) ?>;

function renderMini(id, layout) {
    const box = document.getElementById('mini-' + id);
    if (!box || !layout || !layout.elements) return;
    const sw = 200 / (layout.size?.w || 360);
    const sh = 125 / (layout.size?.h || 225);
    layout.elements.forEach(el => {
        const d = document.createElement('div');
        d.style.position = 'absolute';
        d.style.left = (el.x * sw) + 'px';
        d.style.top  = (el.y * sh) + 'px';
        if (el.type === 'text') {
            d.textContent = el.content || '';
            d.style.fontSize = ((el.fontSize||14) * sw) + 'px';
            d.style.fontWeight = el.fontWeight || '400';
            d.style.color = el.color || '#000';
            d.style.whiteSpace = 'nowrap';
        } else if (el.type === 'qr') {
            d.style.width = (el.size * sw) + 'px';
            d.style.height = (el.size * sh) + 'px';
            d.style.background = '#fff';
            d.style.border = '1px solid #ccc';
        } else if (el.type === 'rect' || el.type === 'line') {
            d.style.width = (el.w * sw) + 'px';
            d.style.height = (el.h * sh) + 'px';
            d.style.background = el.fill || '#ccc';
            d.style.opacity = el.opacity ?? 1;
        } else if (el.type === 'img') {
            d.style.width = (el.w * sw) + 'px';
            d.style.height = (el.h * sh) + 'px';
            if (el.src) { d.style.backgroundImage = `url(${el.src})`; d.style.backgroundSize = 'contain'; d.style.backgroundRepeat = 'no-repeat'; }
        }
        box.appendChild(d);
    });
}
MINI_DATA.forEach(t => renderMini(t.id, t.layout));

async function toggleFav(id, btn) {
    try {
        const res = await fetch('<?= BASE_URL ?>/api/badges/toggle_favorite.php?id=' + id);
        const d = await res.json();
        if (d.success) {
            btn.classList.toggle('on', d.is_favorite === 1);
            btn.setAttribute('aria-pressed', d.is_favorite ? 'true' : 'false');
            setTimeout(() => location.reload(), 400);
        }
    } catch (e) {}
}
</script>
</body>
</html>
