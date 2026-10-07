<?php
require_once __DIR__ . '/../../core/bootstrap.php';
requireLogin();
if (!canManageAccounts()) {
    redirect(BASE_URL . '/pages/dashboard/index.php', 'You do not have access to Accounts.', 'error');
}

$self = currentUserId();
$back = BASE_URL . '/pages/accounts/index.php';

// Super admin manages admins + staff; admin manages staff only. Nobody manages their own account here.
function canManageUser($u) {
    return $u && (int)$u['id'] !== currentUserId() && canCreateRole($u['role']);
}

function findUser($id) {
    global $conn;
    $s = $conn->prepare("SELECT id, email, role FROM users WHERE id = ?");
    $s->bind_param("i", $id);
    $s->execute();
    return $s->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $first = capitalizeWords($_POST['first_name'] ?? '');
        $last  = capitalizeWords($_POST['last_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $pass  = $_POST['password'] ?? '';
        $role  = $_POST['role'] ?? '';
        if ($first === '' || $last === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect($back, 'Enter a first name, last name and a valid email.', 'error');
        }
        if (strlen($pass) < 6) redirect($back, 'Password must be at least 6 characters.', 'error');
        if (!canCreateRole($role)) redirect($back, 'You cannot create that role.', 'error');

        $s = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $s->bind_param("s", $email);
        $s->execute();
        if ($s->get_result()->num_rows) redirect($back, 'That email already has an account.', 'error');

        $hash = hashPassword($pass);
        $s = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)");
        $s->bind_param("sssss", $first, $last, $email, $hash, $role);
        $s->execute();
        logActivity('Account Created', "New {$role} account: {$email}");
        redirect($back, 'Account created for ' . $email . '.');
    }

    if ($action === 'reset_password' || $action === 'delete') {
        $u = findUser((int)($_POST['id'] ?? 0));
        if (!canManageUser($u)) redirect($back, 'You cannot change that account.', 'error');

        if ($action === 'reset_password') {
            $pass = $_POST['password'] ?? '';
            if (strlen($pass) < 6) redirect($back, 'Password must be at least 6 characters.', 'error');
            $hash = hashPassword($pass);
            $s = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $s->bind_param("si", $hash, $u['id']);
            $s->execute();
            logActivity('Password Reset', 'Reset password for ' . $u['email']);
            redirect($back, 'Password updated for ' . $u['email'] . '.');
        }

        $s = $conn->prepare("DELETE FROM users WHERE id = ?");
        $s->bind_param("i", $u['id']);
        $s->execute();
        logActivity('Account Deleted', 'Deleted ' . $u['role'] . ' account: ' . $u['email']);
        redirect($back, 'Account ' . $u['email'] . ' deleted.');
    }

    if ($action === 'create_passkey' && hasRole('super_admin')) {
        $role = $_POST['role'] ?? '';
        if (!in_array($role, ['staff', 'admin', 'super_admin'])) redirect($back, 'Invalid role.', 'error');
        $code = strtoupper(str_replace('_', '', $role)) . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $s = $conn->prepare("INSERT INTO passkeys (passkey_code, role, created_by) VALUES (?, ?, ?)");
        $s->bind_param("ssi", $code, $role, $self);
        $s->execute();
        logActivity('Passkey Created', "{$code} ({$role})");
        redirect($back, "Passkey {$code} created. Share it with the new {$role} user so they can register.");
    }

    if ($action === 'delete_passkey' && hasRole('super_admin')) {
        $id = (int)($_POST['id'] ?? 0);
        $s = $conn->prepare("DELETE FROM passkeys WHERE id = ? AND is_used = 0");
        $s->bind_param("i", $id);
        $s->execute();
        redirect($back, 'Passkey removed.');
    }

    redirect($back, 'Unknown action.', 'error');
}

$users = $conn->query("SELECT id, first_name, middle_name, last_name, email, role, last_login, created_at
                       FROM users ORDER BY FIELD(role, 'super_admin', 'admin', 'staff'), first_name")->fetch_all(MYSQLI_ASSOC);
$passkeys = hasRole('super_admin')
    ? $conn->query("SELECT p.*, u.email used_email FROM passkeys p LEFT JOIN users u ON u.id = p.used_by ORDER BY p.is_used, p.id DESC")->fetch_all(MYSQLI_ASSOC)
    : [];
$creatable = array_filter(['staff', 'admin'], 'canCreateRole');

$flash = getFlashMessage();
$page_title = 'Accounts';
$role_label = fn($r) => ucwords(str_replace('_', ' ', $r));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $page_title ?> &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?>"><?= $flash['message'] ?></div>
        <?php endif; ?>

        <div class="panel">
            <h3>Add account</h3>
            <form method="POST" class="toolbar" style="margin:0;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create">
                <input type="text" name="first_name" placeholder="First name" required aria-label="First name">
                <input type="text" name="last_name" placeholder="Last name" required aria-label="Last name">
                <input type="email" name="email" placeholder="Email" required aria-label="Email">
                <input type="password" name="password" placeholder="Password (min 6)" minlength="6" required aria-label="Password" autocomplete="new-password">
                <select name="role" aria-label="Role">
                    <?php foreach ($creatable as $r): ?><option value="<?= $r ?>"><?= $role_label($r) ?></option><?php endforeach; ?>
                </select>
                <button class="btn-sm"><?= icon('plus', ['class' => 'icon-svg icon-sm']) ?> Create</button>
            </form>
        </div>

        <div class="panel">
            <h3>Users (<?= count($users) ?>)</h3>
            <table class="tbl">
                <tr><th>Name</th><th>Email</th><th>Role</th><th>Last login</th><th class="no-print">Actions</th></tr>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars(getFullName($u)) ?><?= (int)$u['id'] === $self ? ' <span class="muted">(you)</span>' : '' ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="role-tag"><?= $role_label($u['role']) ?></span></td>
                        <td class="muted"><?= $u['last_login'] ? date('M j, Y g:i A', strtotime($u['last_login'])) : 'Never' ?></td>
                        <td class="no-print">
                            <?php if (canManageUser($u)): ?>
                                <form method="POST" class="inline-form">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <input type="password" name="password" placeholder="New password" required minlength="6" title="At least 6 characters" aria-label="New password for <?= htmlspecialchars($u['email']) ?>" autocomplete="new-password">
                                    <button class="btn-sm light">Reset</button>
                                </form>
                                <form method="POST" class="inline-form" data-confirm="<?= htmlspecialchars($u['email']) ?> will lose access immediately. This cannot be undone." data-confirm-title="Delete this account?" data-confirm-ok="Delete account" data-confirm-danger>
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button class="btn-sm danger">Delete</button>
                                </form>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <?php if (hasRole('super_admin')): ?>
        <div class="panel">
            <h3>Registration passkeys</h3>
            <p class="muted" style="margin-bottom:12px;">New users sign up on the Register page with a passkey. Each passkey works once and sets the user's role.</p>
            <form method="POST" class="toolbar">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create_passkey">
                <select name="role" aria-label="Passkey role">
                    <option value="staff">Staff</option><option value="admin">Admin</option><option value="super_admin">Super Admin</option>
                </select>
                <button class="btn-sm"><?= icon('key', ['class' => 'icon-svg icon-sm']) ?> Generate passkey</button>
            </form>
            <table class="tbl">
                <tr><th>Passkey</th><th>Role</th><th>Status</th><th>Created</th><th class="no-print"></th></tr>
                <?php foreach ($passkeys as $p): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($p['passkey_code']) ?></code></td>
                        <td><span class="role-tag"><?= $role_label($p['role']) ?></span></td>
                        <td><?= $p['is_used'] ? '<span class="pill-muted">Used by ' . htmlspecialchars($p['used_email'] ?? 'deleted user') . '</span>' : '<span class="pill-ok">Available</span>' ?></td>
                        <td class="muted"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                        <td class="no-print">
                            <?php if (!$p['is_used']): ?>
                                <form method="POST" class="inline-form" data-confirm="It can no longer be used to register a new account." data-confirm-title="Remove this passkey?" data-confirm-ok="Remove" data-confirm-danger>
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_passkey">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button class="btn-sm danger">Remove</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
