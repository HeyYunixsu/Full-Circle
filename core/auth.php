<?php

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function currentUserId() {
    return (int)($_SESSION['user_id'] ?? 0);
}

function currentRole() {
    return $_SESSION['role'] ?? '';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/pages/auth/login.php');
        exit;
    }
}

function requireRole($allowed_roles) {
    requireLogin();
    if (!in_array($_SESSION['role'], (array)$allowed_roles)) {
        header('Location: ' . BASE_URL . '/pages/dashboard/index.php');
        exit;
    }
}

function canManageAccounts() {
    return in_array(currentRole(), ['super_admin', 'admin']);
}

function canCreateRole($role) {
    if (currentRole() === 'super_admin') return in_array($role, ['admin', 'staff']);
    if (currentRole() === 'admin')       return $role === 'staff';
    return false;
}

function canEditAttendee($event_status) {
    if ($event_status === 'archived') return false;
    if (in_array(currentRole(), ['super_admin', 'admin'])) return true;
    if (currentRole() === 'staff') return $event_status === 'ongoing';
    return false;
}

function isEventLocked($event_status) {
    return $event_status === 'archived';
}
