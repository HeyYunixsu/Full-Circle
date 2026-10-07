<?php

require_once __DIR__ . '/core/bootstrap.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/dashboard/index.php');
} else {
    
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
}
exit;
