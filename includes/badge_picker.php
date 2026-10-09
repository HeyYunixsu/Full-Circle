<?php
// Badge design picker: one card per saved design with a live preview. Posts badge_template_id.
// Used inside the Create Event form and the event page. Set $bp_picked (template id) before including.
$bp_templates = [];
$bp_res = $conn->query("SELECT id, template_name, layout_json, is_favorite FROM badge_templates
                        WHERE layout_json IS NOT NULL ORDER BY is_favorite DESC, id");
while ($r = $bp_res->fetch_assoc()) $bp_templates[] = $r;
$bp_ids = array_map('intval', array_column($bp_templates, 'id'));
// Nothing chosen yet (or the chosen design was deleted): start on the first favorite, else the first design
if (!in_array((int)($bp_picked ?? 0), $bp_ids, true)) $bp_picked = $bp_ids[0] ?? 0;
?>
<?php if ($bp_templates): ?>
<style>
    .bp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(176px, 1fr)); gap: 12px; }
    .bp-card { position: relative; display: flex; flex-direction: column; gap: 8px; padding: 10px; border: 1px solid var(--color-border); border-radius: 12px; background: var(--color-surface); cursor: pointer; transition: border-color var(--dur-fast), box-shadow var(--dur-fast); }
    .bp-card:hover { border-color: var(--magenta); }
    .bp-card input { position: absolute; opacity: 0; pointer-events: none; }
    .bp-card:has(input:checked) { border-color: var(--magenta); box-shadow: 0 0 0 3px var(--pink-soft); }
    .bp-card:has(input:focus-visible) { outline: 2px solid var(--magenta); outline-offset: 2px; }
    .bp-thumb { display: flex; justify-content: center; padding: 6px 0; border-radius: 8px; background: var(--color-bg); }
    .bp-name { font-size: 13px; font-weight: 600; color: var(--color-text-strong); display: flex; align-items: center; gap: 6px; }
    .bp-name .icon-svg { width: 14px; height: 14px; color: var(--magenta); flex: none; }
    .bp-check { margin-left: auto; display: none; }
    .bp-card:has(input:checked) .bp-check { display: inline-flex; }
</style>
<div class="bp-grid" role="radiogroup" aria-label="Badge design">
    <?php foreach ($bp_templates as $t): ?>
        <label class="bp-card">
            <input type="radio" name="badge_template_id" value="<?= (int)$t['id'] ?>" <?= (int)$t['id'] === (int)$bp_picked ? 'checked' : '' ?>>
            <span class="bp-thumb" data-bp-thumb="<?= (int)$t['id'] ?>"></span>
            <span class="bp-name">
                <?= htmlspecialchars($t['template_name']) ?>
                <?php if ($t['is_favorite']): ?><span title="Favorite" aria-label="Favorite"><?= icon('star', ['class' => 'icon-svg']) ?></span><?php endif; ?>
                <span class="bp-check" aria-hidden="true"><?= icon('check', ['class' => 'icon-svg']) ?></span>
            </span>
        </label>
    <?php endforeach; ?>
</div>
<?php if (empty($badge_render_loaded)): $badge_render_loaded = true; ?>
<script src="<?= BASE_URL ?>/assets/js/badge-render.js?v=<?= ASSET_VER ?>"></script>
<?php endif; ?>
<script>
(() => {
    const layouts = <?= json_encode(array_column(array_map(fn($t) => ['id' => (int)$t['id'], 'l' => json_decode($t['layout_json'], true)], $bp_templates), 'l', 'id')) ?>;
    document.querySelectorAll('[data-bp-thumb]').forEach(el => { el.innerHTML = renderBadge(layouts[el.dataset.bpThumb], BADGE_SAMPLE, 0.42); });
})();
</script>
<?php else: ?>
<p class="muted" style="font-size: 14px;">No saved badge designs yet. Make one in the Badge Designer; until then the standard badge is used.</p>
<?php endif; ?>
