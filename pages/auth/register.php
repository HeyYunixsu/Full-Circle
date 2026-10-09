<?php

require_once __DIR__ . '/../../core/bootstrap.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/dashboard/index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = capitalizeWords($_POST['first_name'] ?? '');
    $middle_name = capitalizeWords($_POST['middle_name'] ?? '');
    $last_name = capitalizeWords($_POST['last_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $passkey = sanitize($_POST['passkey'] ?? '');

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($passkey)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {

        $check_sql = "SELECT id FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error = 'This email is already registered.';
        } else {

            $key_sql = "SELECT * FROM passkeys WHERE passkey_code = ? AND is_used = FALSE LIMIT 1";
            $key_stmt = $conn->prepare($key_sql);
            $key_stmt->bind_param("s", $passkey);
            $key_stmt->execute();
            $key_result = $key_stmt->get_result();

            if ($key_result->num_rows === 0) {
                $error = 'Invalid or already used passkey. Please contact your Project Manager.';
            } else {
                $passkey_data = $key_result->fetch_assoc();
                $role = $passkey_data['role'];

                $hashed_password = hashPassword($password);

                $insert_sql = "INSERT INTO users (first_name, middle_name, last_name, email, password, role)
                              VALUES (?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("ssssss", $first_name, $middle_name, $last_name, $email, $hashed_password, $role);

                if ($insert_stmt->execute()) {
                    $user_id = $conn->insert_id;

                    $update_key_sql = "UPDATE passkeys SET is_used = TRUE, used_by = ?, used_at = NOW() WHERE id = ?";
                    $update_key_stmt = $conn->prepare($update_key_sql);
                    $update_key_stmt->bind_param("ii", $user_id, $passkey_data['id']);
                    $update_key_stmt->execute();

                    logActivity('Account Created', 'New ' . $role . ' account: ' . $email);

                    redirect(BASE_URL . '/pages/auth/login.php',
                            'Account created successfully! Please login.', 'success');
                } else {
                    $error = 'Failed to create account. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<style>
    .auth-card { max-width: 500px; }
    .form-grid { gap: 0 12px; }
</style>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/mobile.css?v=<?= ASSET_VER ?>" media="(max-width: 768px)">
</head>
<body class="auth-body">

<div class="auth-card">
    <div class="auth-mark"><span class="ring">FC</span> <?= SITE_NAME ?></div>
    <div class="auth-logo">
        <h1>Create Account</h1>
    </div>
    <p class="auth-subtitle">Staff and admin accounts need a passkey from the Project Manager.</p>

    <?php if ($error): ?>
        <div class="alert alert-error" role="alert"><?= icon('alert', ['class' => 'icon-svg icon-sm']) ?> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-grid">
            <div class="form-group">
                <label for="first_name">First name</label>
                <input type="text" id="first_name" name="first_name" class="form-input"
                       placeholder="Juan" required autocomplete="given-name"
                       value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="last_name">Last name</label>
                <input type="text" id="last_name" name="last_name" class="form-input"
                       placeholder="Dela Cruz" required autocomplete="family-name"
                       value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="middle_name">Middle name <span class="optional">(optional)</span></label>
            <input type="text" id="middle_name" name="middle_name" class="form-input"
                   autocomplete="additional-name"
                   value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" class="form-input"
                   placeholder="email@example.com" required autocomplete="email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-input"
                       placeholder="Min. 6 characters" required minlength="6" autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-input"
                       placeholder="Re-enter password" required minlength="6" autocomplete="new-password">
            </div>
        </div>

        <div class="form-group">
            <label for="passkey">Passkey</label>
            <input type="text" id="passkey" name="passkey" class="form-input"
                   placeholder="e.g. STAFF-1A2B3C" required autocomplete="off" aria-describedby="passkey-hint"
                   value="<?= htmlspecialchars($_POST['passkey'] ?? '') ?>">
            <p class="form-hint" id="passkey-hint">Each passkey works once and sets your role.</p>
        </div>

        <button type="submit" class="btn btn-primary full">Create Account</button>

        <div class="auth-links" style="justify-content: center;">
            <a href="<?= BASE_URL ?>/pages/auth/login.php">Back to Login</a>
        </div>
    </form>
</div>

</body>
</html>
