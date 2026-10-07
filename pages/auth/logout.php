<?php

require_once __DIR__ . '/../../core/bootstrap.php';

if (isLoggedIn()) {
    
    $user_id = $_SESSION['user_id'];
    $sql = "UPDATE users SET status = 'offline' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    logActivity('User Logout');
}

session_unset();
session_destroy();

header('Location: ' . BASE_URL . '/pages/auth/login.php');
exit;
