<?php

require_once __DIR__ . '/icons.php';

$page_title = $page_title ?? 'Dashboard';
$user_initials = strtoupper(substr($_SESSION['first_name'] ?? 'U', 0, 1) . substr($_SESSION['last_name'] ?? 'U', 0, 1));
$user_role_display = ucfirst(str_replace('_', ' ', $_SESSION['role'] ?? 'User'));
$user_full_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?: $user_role_display;
$is_admin = in_array($_SESSION['role'] ?? '', ['admin', 'super_admin']);
?>

<header class="page-header">
    <div class="page-heading">
        <?php if (!empty($back_url)): $back_label = $back_label ?? 'Back'; ?>
        <a class="topbar-back" href="<?= htmlspecialchars($back_url) ?>" title="<?= htmlspecialchars($back_label) ?>" aria-label="<?= htmlspecialchars($back_label) ?>"><?= icon('arrow-left', ['class' => 'icon-svg']) ?></a>
        <?php endif; ?>
        <h1 class="page-title"><?= htmlspecialchars($page_title) ?></h1>
    </div>

    <div class="topbar-right">
        <?php if (empty($page_has_search)): // pages with their own search box hide this shortcut ?>
        <form class="topbar-search" method="GET" action="<?= BASE_URL ?>/pages/attendees/index.php" role="search">
            <?= icon('search', ['class' => 'icon-svg']) ?>
            <input type="search" name="search" placeholder="Search attendee, code or company" aria-label="Search attendees" autocomplete="off" spellcheck="false">
        </form>
        <?php endif; ?>
        <?php if ($is_admin): ?>
        <a href="<?= BASE_URL ?>/pages/events/create.php" class="btn-sm topbar-add"><?= icon('plus', ['class' => 'icon-svg icon-sm']) ?> <span>Add Event</span></a>
        <?php endif; ?>

        <details class="user-menu" id="userMenu">
            <summary class="user-info" aria-label="Account menu">
                <div class="user-avatar" aria-hidden="true"><?= htmlspecialchars($user_initials) ?></div>
                <div class="user-text">
                    <span class="user-name"><?= htmlspecialchars($user_full_name) ?></span>
                    <span class="user-role"><?= htmlspecialchars($user_role_display) ?></span>
                </div>
                <?= icon('chevron-down', ['class' => 'icon-svg chevron']) ?>
            </summary>
            <div class="user-dd">
                <div class="user-dd-head">
                    <b><?= htmlspecialchars($user_full_name) ?></b>
                    <small><?= htmlspecialchars($_SESSION['email'] ?? $user_role_display) ?></small>
                </div>
                <?php if (function_exists('canManageAccounts') && canManageAccounts()): ?>
                <a href="<?= BASE_URL ?>/pages/accounts/index.php"><?= icon('users', ['class' => 'icon-svg']) ?> Manage accounts</a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/pages/auth/logout.php" class="danger"><?= icon('log-out', ['class' => 'icon-svg']) ?> Sign out</a>
            </div>
        </details>
    </div>
</header>
<script src="<?= BASE_URL ?>/assets/js/dialog.js?v=<?= ASSET_VER ?>" defer></script>
<script>
// Names, companies, designations and titles: capital first letter of each word when leaving the field.
// Same rule as capitalizeWords() in core/helpers.php, which applies it on save.
document.addEventListener('blur', e => {
    const el = e.target;
    if (!(el instanceof HTMLInputElement) || !['text', ''].includes(el.getAttribute('type') || '')) return;
    if (!/name|compan|designation|location|speaker|role|manager|f-loc/i.test(el.name + ' ' + el.id) || /email|code|hex/i.test(el.name + ' ' + el.id)) return;
    el.value = el.value.replace(/(^|[\s\-(])(\p{Ll})/gu, (m, a, b) => a + b.toUpperCase());
}, true);
</script>
<script>
// Close the account menu on outside click or Escape; a native details element does not do this itself
document.addEventListener('click', e => { const m = document.getElementById('userMenu'); if (m && m.open && !m.contains(e.target)) m.open = false; });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { const m = document.getElementById('userMenu'); if (m) m.open = false; } });
</script>
<?php
// Result of the last action (set by redirect() in core/flash.php), shown as a pop-up on whichever page loads next.
// Pages that already read it keep it in $flash; otherwise read it here.
$flash = $flash ?? getFlashMessage();
if ($flash):
    $ft_type = in_array($flash['type'], ['success', 'error', 'warning', 'info'], true) ? $flash['type'] : 'success';
    $ft_icon = ['success' => 'check-circle', 'error' => 'x-circle', 'warning' => 'alert', 'info' => 'info'][$ft_type];
    $ft_life = ['success' => 5, 'info' => 6, 'warning' => 8, 'error' => 9][$ft_type];   // seconds; hover pauses it
?>
<div class="flash-stack">
    <div class="flash-toast flash-<?= $ft_type ?>" role="<?= in_array($ft_type, ['error', 'warning']) ? 'alert' : 'status' ?>" style="--ft-life: <?= $ft_life ?>s">
        <span class="ft-icon"><?= icon($ft_icon, ['class' => 'icon-svg']) ?></span>
        <div class="ft-body"><?= $flash['message'] ?></div>
        <button type="button" class="ft-close" aria-label="Dismiss notification"><?= icon('x', ['class' => 'icon-svg icon-sm']) ?></button>
        <span class="ft-timer" aria-hidden="true"></span>
    </div>
</div>
<script>
// Hides when the timer bar runs out (the bar pauses while hovered) or when the close button is clicked
document.querySelectorAll('.flash-toast').forEach(t => {
    const hide = () => { t.classList.add('hide'); setTimeout(() => t.parentElement?.remove(), 250); };
    t.querySelector('.ft-close').addEventListener('click', hide);
    t.querySelector('.ft-timer').addEventListener('animationend', hide);
});
</script>
<?php endif; ?>
