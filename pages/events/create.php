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
        $error = 'Please fill in the event name, date, start time and venue.';
    } elseif (!isValidEventDate($event_date)) {
        $error = 'Invalid event date. Year must be between 2000 and 2100.';
    } else {
        $created_by = $_SESSION['user_id'];
        // A repeated send (double-click, slow network retry) opens the event just created instead of copying it
        $dup = $conn->prepare("SELECT id FROM events WHERE event_name = ? AND event_date = ? AND event_time = ? AND location = ?
                               AND created_by = ? AND created_at > NOW() - INTERVAL 2 MINUTE ORDER BY id DESC LIMIT 1");
        $dup->bind_param("ssssi", $event_name, $event_date, $event_time, $location, $created_by);
        $dup->execute();
        if ($row = $dup->get_result()->fetch_assoc()) {
            redirect(BASE_URL . '/pages/events/view.php?id=' . $row['id'], 'Event created successfully!');
        }
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
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/mobile.css?v=<?= ASSET_VER ?>" media="(max-width: 768px)">
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php $back_url = BASE_URL . '/pages/events/index.php'; $back_label = 'Back to Events'; include __DIR__ . '/../../includes/header.php'; ?>
        <div class="narrow">
            <div class="panel">
                <div class="form-head">
                    <div class="kicker">New event</div>
                    <h3>Event details</h3>
                    <p>You can add attendees, companies and sessions after creating it.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error" role="alert"><?= icon('alert', ['class' => 'icon-svg icon-sm']) ?> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-section">
                        <div class="form-group">
                            <label for="event_name">Event name</label>
                            <input type="text" name="event_name" id="event_name" class="form-input" required autofocus value="<?= htmlspecialchars($_POST['event_name'] ?? '') ?>" placeholder="e.g. SAP Tech Conference 2026">
                        </div>
                        <div class="form-group">
                            <label for="description">Description <span class="optional">(optional)</span></label>
                            <textarea name="description" id="description" class="form-input" placeholder="What the event is about, shown on the event page"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title">When and where</div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="event_date">Date</label>
                                <input type="date" name="event_date" id="event_date" class="form-input" required min="2000-01-01" max="2100-12-31" value="<?= htmlspecialchars($_POST['event_date'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="event_time">Start time</label>
                                <input type="time" name="event_time" id="event_time" class="form-input" required value="<?= htmlspecialchars($_POST['event_time'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="location">Venue</label>
                            <input type="text" name="location" id="location" class="form-input" required value="<?= htmlspecialchars($_POST['location'] ?? '') ?>" placeholder="e.g. SMX Convention Center">
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title">Capacity and team</div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="max_attendees">Max attendees <span class="optional">(optional)</span></label>
                                <input type="number" name="max_attendees" id="max_attendees" class="form-input" min="0" value="<?= htmlspecialchars($_POST['max_attendees'] ?? '') ?>" placeholder="No limit" aria-describedby="max-help">
                                <div class="form-help" id="max-help">Leave blank for no limit.</div>
                            </div>
                            <div class="form-group">
                                <label for="event_manager">Event manager <span class="optional">(optional)</span></label>
                                <input type="text" name="event_manager" id="event_manager" class="form-input" value="<?= htmlspecialchars($_POST['event_manager'] ?? '') ?>" placeholder="e.g. Maria Santos">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="companies">Companies <span class="optional">(optional)</span></label>
                            <input type="text" name="companies" id="companies" class="form-input" value="<?= htmlspecialchars($_POST['companies'] ?? '') ?>" placeholder="e.g. SAP, Del Monte, Accenture" aria-describedby="companies-help">
                            <div class="form-help" id="companies-help">Separate with commas. Companies in your attendee list are added automatically, and you can set limits later on the Companies page.</div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="<?= BASE_URL ?>/pages/events/index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create event</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>
</body>
</html>