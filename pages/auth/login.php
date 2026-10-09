<?php

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../includes/qrcode.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/dashboard/index.php');
    exit;
}

$error = '';
$success = '';

$flash = getFlashMessage();
if ($flash) {
    if ($flash['type'] === 'success') $success = $flash['message'];
    else $error = $flash['message'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        // lock_secs > 0 while the account is locked (worked out by the database clock, like locked_until)
        $sql = "SELECT *, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until)) AS lock_secs FROM users WHERE email = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if ($user['lock_secs'] > 0) {
                $mins = (int)ceil($user['lock_secs'] / 60);
                $error = "Too many wrong passwords. This account is locked for $mins more minute" . ($mins === 1 ? '' : 's') . '.';
            } elseif (verifyPassword($password, $user['password'])) {
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role'] = $user['role'];

                $update_sql = "UPDATE users SET last_login = NOW(), status = 'active', failed_logins = 0, locked_until = NULL WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();

                logActivity('User Login', 'User logged in: ' . $email);

                redirect(BASE_URL . '/pages/dashboard/index.php', 'Welcome back, ' . $user['first_name'] . '!');
            } else {
                // 5 wrong passwords in a row lock the account for 15 minutes (stops password guessing on the public link)
                $fails = (int)$user['failed_logins'] + 1;
                if ($fails >= 5) {
                    $lock = $conn->prepare("UPDATE users SET failed_logins = 0, locked_until = NOW() + INTERVAL 15 MINUTE WHERE id = ?");
                    $lock->bind_param("i", $user['id']);
                    $lock->execute();
                    logActivity('Account Locked', '5 wrong passwords for ' . $user['email'] . ' from ' . ($_SERVER['REMOTE_ADDR'] ?? ''));
                    $error = 'Too many wrong passwords. This account is locked for 15 minutes.';
                } else {
                    $f = $conn->prepare("UPDATE users SET failed_logins = ? WHERE id = ?");
                    $f->bind_param("ii", $fails, $user['id']);
                    $f->execute();
                    $left = 5 - $fails;
                    $error = 'Invalid email or password.' . ($left <= 2 ? " $left more wrong " . ($left === 1 ? 'try' : 'tries') . ' will lock this account for 15 minutes.' : '');
                }
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<style>
    :root { --ticket: #fbf5ec; }
    body { background: var(--white); }
    .login { min-height: 100vh; display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(400px, 1fr); }

    /* ---- Brand side: dark wine, dotted grid, "full circle" rings, admission ticket ---- */
    .login-brand { position: relative; overflow: hidden; isolation: isolate; background: var(--wine-darkest); color: var(--white);
                   padding: 44px 56px; display: flex; flex-direction: column; justify-content: space-between; gap: 36px; }
    .login-brand::before { content: ''; position: absolute; inset: 0; z-index: -2;
                           background-image: radial-gradient(rgba(255,255,255,.07) 1px, transparent 1px); background-size: 22px 22px; }
    .rings { position: absolute; z-index: -1; width: 760px; aspect-ratio: 1; right: -260px; top: 50%; translate: 0 -50%; }
    .rings circle { fill: none; stroke: var(--pink-bright); }
    .brand-mark { display: flex; align-items: center; gap: 12px; font-weight: 600; font-size: 15px; letter-spacing: .2px; }
    .brand-circle { width: 40px; height: 40px; border-radius: 50%; border: 2px solid var(--pink-bright); display: grid; place-items: center;
                    font-size: 13px; font-weight: 800; letter-spacing: .5px; }
    .brand-line { font-size: clamp(30px, 3.3vw, 46px); line-height: 1.1; font-weight: 700; letter-spacing: -1px; max-width: 12ch; }
    .brand-line em { font-style: normal; color: var(--pink-bright); }

    .ticket { display: flex; width: min(470px, 100%); background: var(--ticket); color: var(--wine-darkest); border-radius: 16px;
              box-shadow: 0 30px 60px rgba(0,0,0,.4); rotate: -3deg; }
    .ticket-main { flex: 1; padding: 22px 24px; border-right: 2px dashed rgba(92,34,73,.28); position: relative; }
    .ticket-main::before, .ticket-main::after { content: ''; position: absolute; right: -12px; width: 22px; height: 22px; border-radius: 50%; background: var(--wine-darkest); }
    .ticket-main::before { top: -11px; }
    .ticket-main::after  { bottom: -11px; }
    .ticket-kicker { font-size: 11px; letter-spacing: 2.2px; text-transform: uppercase; color: var(--magenta); font-weight: 700; }
    .ticket-title { font-size: 23px; font-weight: 800; letter-spacing: -.4px; margin: 4px 0 16px; }
    .ticket-meta { display: flex; gap: 22px; }
    .ticket-meta small { display: block; font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; color: var(--gray-mid); }
    .ticket-meta b { font-size: 14px; }
    .ticket-meta .gold { color: #b9780d; }
    .ticket-stub { width: 136px; display: grid; place-items: center; padding: 18px; }
    .ticket-qr { position: relative; width: 96px; height: 96px; overflow: hidden; }
    .ticket-qr svg { width: 100%; height: 100%; fill: var(--wine-darkest); display: block; }
    .scanline { position: absolute; left: -4px; right: -4px; top: 0; height: 2px; background: var(--pink-bright);
                box-shadow: 0 0 10px 2px rgba(232,87,159,.75); animation: scan 2.4s ease-in-out infinite alternate; }
    @keyframes scan { to { top: calc(100% - 2px); } }
    .brand-foot { font-size: 12px; color: rgba(255,255,255,.45); }

    /* ---- Form side ---- */
    .login-panel { display: flex; align-items: center; justify-content: center; padding: 48px 32px; }
    .login-form { width: 100%; max-width: 380px; }
    .login-form h1 { font-size: 30px; line-height: 1.2; color: var(--wine-darkest); letter-spacing: -.6px; }
    .login-form .sub { color: var(--gray-mid); font-size: 14px; margin: 6px 0 28px; }
    .field { margin-bottom: 18px; }
    .field-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 6px; }
    .field-head label { font-size: 13px; font-weight: 600; color: var(--wine-mid); }
    .field-head a { font-size: 12px; font-weight: 600; color: var(--magenta); }
    .field-head a:hover { text-decoration: underline; }
    .input-wrap { position: relative; }
    .input-wrap > .icon-svg { position: absolute; left: 16px; top: 50%; translate: 0 -50%; color: var(--gray-mid); pointer-events: none; }
    .input-wrap .form-input { padding-left: 46px; }
    .input-wrap .form-input.has-toggle { padding-right: 72px; }
    .pw-toggle { position: absolute; right: 8px; top: 50%; translate: 0 -50%; background: none; color: var(--wine-mid);
                 font-size: 12px; font-weight: 700; padding: 8px 10px; border-radius: 8px; }
    .pw-toggle:hover { background: var(--pink-soft); }
    .login-submit { width: 100%; margin-top: 8px; padding: 15px; border-radius: var(--radius-md); background: var(--wine-mid); color: var(--white);
                    font-size: 15px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px;
                    transition: background .15s ease, transform .1s ease; }
    .login-submit:hover { background: var(--magenta); }
    .login-submit:active { transform: translateY(1px); }
    .login-submit:disabled { opacity: .75; cursor: progress; }
    .pw-toggle:focus-visible, .login-submit:focus-visible, .login-form a:focus-visible { outline: 3px solid var(--pink-bright); outline-offset: 2px; }
    .login-alt { margin-top: 26px; padding-top: 20px; border-top: 1px solid var(--gray-light); font-size: 14px; color: var(--gray-dark); text-align: center; }
    .login-alt a { color: var(--magenta); font-weight: 600; }
    .login-alt a:hover { text-decoration: underline; }

    @media (max-width: 900px) {
        .login { grid-template-columns: 1fr; grid-template-rows: auto 1fr; }
        .login-brand { padding: 24px; gap: 14px; }
        .ticket, .brand-foot, .rings { display: none; }
        .brand-line { font-size: 24px; max-width: none; }
        .login-panel { align-items: flex-start; padding: 32px 20px 40px; }
    }
    @media (prefers-reduced-motion: reduce) { .scanline { animation: none; top: 48%; } }
</style>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/mobile.css?v=<?= ASSET_VER ?>" media="(max-width: 768px)">
</head>
<body>
<main class="login">
    <section class="login-brand">
        <svg class="rings" viewBox="0 0 200 200" aria-hidden="true">
            <circle cx="100" cy="100" r="98" stroke-width=".4" opacity=".35"/>
            <circle cx="100" cy="100" r="74" stroke-width=".4" opacity=".25"/>
            <circle cx="100" cy="100" r="50" stroke-width=".4" opacity=".18"/>
        </svg>

        <div class="brand-mark"><span class="brand-circle">FC</span> <?= SITE_NAME ?></div>

        <h2 class="brand-line">Every guest checked in, <em>in&nbsp;one&nbsp;scan.</em></h2>

        <div class="ticket" aria-hidden="true">
            <div class="ticket-main">
                <div class="ticket-kicker">Admit one &middot; Staff</div>
                <div class="ticket-title">Event Check-In</div>
                <div class="ticket-meta">
                    <span><small>Gate</small><b>Main</b></span>
                    <span><small>Pass</small><b class="gold">All access</b></span>
                    <span><small>No.</small><b>FC-<?= date('Y') ?></b></span>
                </div>
            </div>
            <div class="ticket-stub">
                <div class="ticket-qr"><?= qrSvg(BASE_URL) ?><i class="scanline"></i></div>
            </div>
        </div>

        <p class="brand-foot">&copy; <?= date('Y') ?> Full Circle Events Asia, Inc.</p>
    </section>

    <section class="login-panel">
        <div class="login-form">
            <h1>Sign in</h1>
            <p class="sub">Use your staff account to run events and check-ins.</p>

            <?php if ($error): ?>
                <div class="alert alert-error" role="alert"><?= icon('alert', ['class' => 'icon-svg icon-sm']) ?> <?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success" role="status"><?= icon('check', ['class' => 'icon-svg icon-sm']) ?> <?= $success ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <div class="field">
                    <div class="field-head"><label for="email">Email</label></div>
                    <div class="input-wrap">
                        <?= icon('mail', ['class' => 'icon-svg']) ?>
                        <input type="email" id="email" name="email" class="form-input" placeholder="you@fullcircle.com"
                               autocomplete="username" required autofocus
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="field">
                    <div class="field-head">
                        <label for="password">Password</label>
                        <a href="<?= BASE_URL ?>/pages/auth/forgot.php">Forgot password?</a>
                    </div>
                    <div class="input-wrap">
                        <?= icon('key', ['class' => 'icon-svg']) ?>
                        <input type="password" id="password" name="password" class="form-input has-toggle"
                               placeholder="Your password" autocomplete="current-password" required>
                        <button type="button" class="pw-toggle" id="pwToggle" aria-controls="password" aria-pressed="false">Show</button>
                    </div>
                </div>

                <button type="submit" class="login-submit" id="loginBtn">Sign in</button>
            </form>

            <p class="login-alt">New staff member? <a href="<?= BASE_URL ?>/pages/auth/register.php">Register with a passkey</a></p>
        </div>
    </section>
</main>

<script>
document.getElementById('pwToggle').addEventListener('click', function () {
    const pw = document.getElementById('password');
    const show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    this.textContent = show ? 'Hide' : 'Show';
    this.setAttribute('aria-pressed', show);
    pw.focus();
});
document.getElementById('loginForm').addEventListener('submit', function () {
    const btn = document.getElementById('loginBtn');
    btn.disabled = true;
    btn.textContent = 'Signing in…';
});
</script>
</body>
</html>
