<?php
// TEMPLATE. Copy this file to config/config.php and fill in your own values there.
// config/config.php is ignored by git, so passwords and API keys never reach GitHub.
//   SMTP_USERNAME / SMTP_FROM_EMAIL : the Gmail address that sends QR codes and reminders
//   SMTP_PASSWORD                   : a Gmail App Password (Google Account > Security > App passwords)
//   SEMAPHORE_API_KEY               : from semaphore.co (leave blank and keep SMS_MOCK true to test without texting)
if (session_status() === PHP_SESSION_NONE) {
    // SameSite=Strict: the browser never sends the login cookie on requests that
    // start from another site, which blocks CSRF on every form and action link.
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
    session_start();
}

define('SITE_NAME', 'Full Circle Events Asia');
define('SITE_SHORT', 'Full Circle');
define('BASE_URL', 'http://localhost/Full_Event');
define('BASE_PATH', dirname(__DIR__));

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'full_event_db');

define('UPLOADS_PATH', BASE_PATH . '/assets/uploads');
define('QRCODES_PATH', BASE_PATH . '/assets/qrcodes');
define('UPLOADS_URL', BASE_URL . '/assets/uploads');
define('QRCODES_URL', BASE_URL . '/assets/qrcodes');

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
//password for the email account, or an app password if 2FA is enabled
define('SMTP_FROM_NAME', 'Full Circle Events Asia');
define('SMTP_FROM_EMAIL', '');

define('SMS_ENABLED', true);
define('SMS_MOCK', true);
// Semaphore (semaphore.co): API key from your Semaphore dashboard.
// Sender name: blank = your account's default; must be a name approved in Semaphore.
define('SEMAPHORE_API_KEY', '');
define('SEMAPHORE_SENDER_NAME', '');
define('SEMAPHORE_API_BASE', 'https://api.semaphore.co/api/v4');

define('TIMEZONE', 'Asia/Manila');
date_default_timezone_set(TIMEZONE);

// true = show PHP errors on the page (development only). Either way errors are
// logged to C:\xampp\apache\logs\error.log. Deprecation notices are skipped
// (vendor/phpqrcode floods them on PHP 8.2).
define('DEBUG_MODE', false);
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', DEBUG_MODE ? '1' : '0');
ini_set('log_errors', '1');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
