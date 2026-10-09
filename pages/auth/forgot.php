<?php

require_once __DIR__ . '/../../core/bootstrap.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');

    if (empty($email)) {
        $message = 'Please enter your email.';
        $message_type = 'error';
    } else {
        $sql = "SELECT id, first_name FROM users WHERE email = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $message = 'If this email is registered, a password reset link will be sent. (Email feature coming in next step!)';
            $message_type = 'success';
        } else {

            $message = 'If this email is registered, a password reset link will be sent.';
            $message_type = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/mobile.css?v=<?= ASSET_VER ?>" media="(max-width: 768px)">
</head>
<body class="auth-body">

<div class="auth-card">
    <div class="auth-mark"><span class="ring">FC</span> <?= SITE_NAME ?></div>
    <div class="auth-logo">
        <h1>Forgot Password</h1>
    </div>
    <p class="auth-subtitle">Enter your email and we will send a reset link.</p>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>" role="alert">
            <?= icon($message_type === 'success' ? 'check' : 'alert', ['class' => 'icon-svg icon-sm']) ?> <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" class="form-input"
                   placeholder="email@example.com" required autocomplete="email">
        </div>

        <button type="submit" class="btn btn-primary full">Send Reset Link</button>

        <div class="auth-links" style="justify-content: center;">
            <a href="<?= BASE_URL ?>/pages/auth/login.php">Back to Login</a>
        </div>
    </form>
</div>

</body>
</html>
