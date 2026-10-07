<?php

require_once __DIR__ . '/../../core/bootstrap.php';
requireLogin();

if (!in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Access Denied: Only Admin and Super Admin can create events.'];
    header("Location: " . BASE_URL . "/pages/dashboard/");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_name = capitalizeWords($_POST['event_name'] ?? '');
    $event_date = sanitize($_POST['event_date'] ?? '');
    $event_time = sanitize($_POST['event_time'] ?? '');
    $location = capitalizeWords($_POST['location'] ?? '');
    $event_type = sanitize($_POST['event_type'] ?? 'single');
    $description = sanitize($_POST['description'] ?? '');
    $event_manager = capitalizeWords($_POST['event_manager'] ?? '');
    $max_attendees = (int)($_POST['max_attendees'] ?? 0);

    if (empty($event_name) || empty($event_date) || empty($event_time) || empty($location)) {
        $error = 'Please fill in all required fields.';
    } elseif (!isValidEventDate($event_date)) {
        $error = 'Invalid event date. Year must be between 2000 and 2100.';
    } else {
        $created_by = $_SESSION['user_id'];
        $sql = "INSERT INTO events (event_name, event_date, event_time, location, event_type, description, event_manager, max_attendees, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssii", $event_name, $event_date, $event_time, $location, $event_type, $description, $event_manager, $max_attendees, $created_by);

        if ($stmt->execute()) {
            $event_id = $conn->insert_id;
            
            if (!empty($_POST['companies'])) {
                $companies = explode(',', $_POST['companies']);
                $company_stmt = $conn->prepare("INSERT INTO companies (event_id, company_name) VALUES (?, ?)");
                foreach ($companies as $company) {
                    $company = capitalizeWords($company);
                    if (!empty($company)) {
                        $company_stmt->bind_param("is", $event_id, $company);
                        $company_stmt->execute();
                    }
                }
            }
            
            $tpl_stmt = $conn->prepare("INSERT INTO badge_templates (event_id, template_name, is_default) VALUES (?, 'Default Template', TRUE)");
            $tpl_stmt->bind_param("i", $event_id);
            $tpl_stmt->execute();

            logActivity('Event Created', 'Created event: ' . $event_name);
            redirect(BASE_URL . '/pages/events/view.php?id=' . $event_id, 'Event created successfully!');
        } else {
            $error = 'Failed to create event: ' . $conn->error;
        }
    }
}
$page_title = 'Create Event';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $page_title ?> &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<style>
    .form-card { background: white; padding: 30px; border-radius: 16px; box-shadow: var(--shadow-sm); max-width: 700px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
    .form-row .form-group { margin-bottom: 0; }
    textarea.form-input { resize: vertical; min-height: 80px; }
    .form-actions { display: flex; gap: 12px; margin-top: 24px; }
    .form-actions .btn { flex: 1; }
    .back-link { color: var(--purple-mid); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 20px; }
    .back-link .icon-svg { width: 16px; height: 16px; }
</style>
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php $back_url = BASE_URL . '/pages/events/index.php'; $back_label = 'Back to Events'; include __DIR__ . '/../../includes/header.php'; ?>
        <h2 style="color: var(--purple-mid); margin-bottom: 24px;">Create New Event</h2>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= icon('alert', ['class' => 'icon-svg icon-sm']) ?> <?= $error ?></div>
        <?php endif; ?>
        <div class="form-card">
            <form method="POST" action="">
                <div class="form-group">
                    <label>Event Name *</label>
                    <input type="text" name="event_name" class="form-input" required placeholder="e.g. SAP Tech Conference 2026">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Event Date *</label>
                        <input type="date" name="event_date" class="form-input" required min="2000-01-01" max="2100-12-31">
                    </div>
                    <div class="form-group">
                        <label>Event Time *</label>
                        <input type="time" name="event_time" class="form-input" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Location *</label>
                    <input type="text" name="location" class="form-input" required placeholder="e.g. SMX Convention Center">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Event Type</label>
                        <select name="event_type" class="form-input">
                            <option value="single">Single Session</option>
                            <option value="multiple">Multiple Sessions</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Max Attendees</label>
                        <input type="number" name="max_attendees" class="form-input" placeholder="500" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label>Event Manager</label>
                    <input type="text" name="event_manager" class="form-input" placeholder="e.g. Maria Santos">
                </div>
                <div class="form-group">
                    <label>Companies (comma-separated)</label>
                    <input type="text" name="companies" class="form-input" placeholder="e.g. SAP, Delmonte, Amazon">
                    <p class="form-hint">List the companies attending this event, separated by commas.</p>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-input" placeholder="Brief description of the event..."></textarea>
                </div>
                <div class="form-actions">
                    <a href="<?= BASE_URL ?>/pages/events/index.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Event</button>
                </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>