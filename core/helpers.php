<?php

// Input cleanup only. Do NOT escape here: SQL safety comes from prepared
// statements, HTML safety from htmlspecialchars() when printing.
function sanitize($data) {
    return trim((string)$data);
}

// First letter of every word in capitals: "juan dela cruz" -> "Juan Dela Cruz". Never lowercases,
// so acronyms typed as "SAP" or "IT" stay. For names, companies and titles; not emails or free text.
function capitalizeWords($text) {
    return preg_replace_callback('/(^|[\s\-(])(\p{Ll})/u', fn($m) => $m[1] . mb_strtoupper($m[2]), trim((string)$text));
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function generateAttendeeCode($event_id = null) {
    global $conn;

    $attempts = 0;
    do {
        $code = (string) random_int(100000000, 999999999);
        $check = $conn->prepare("SELECT id FROM attendees WHERE attendee_code = ? LIMIT 1");
        $check->bind_param("s", $code);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $attempts++;
    } while ($exists && $attempts < 15);

    return $code;
}

function generateQRData($attendee_code) {
    return 'FC-' . strtoupper(uniqid()) . '-' . $attendee_code;
}

function formatDate($date, $format = 'F j, Y') {
    return date($format, strtotime($date));
}

function formatTime($time, $format = 'g:i A') {
    return date($format, strtotime($time));
}

function getFullName($user) {
    $name = $user['first_name'];
    if (!empty($user['middle_name'])) {
        $name .= ' ' . $user['middle_name'];
    }
    $name .= ' ' . $user['last_name'];
    return $name;
}

function csrfField() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}

function checkCsrf() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Invalid form token. Go back, reload the page and try again.');
    }
}

// Stored qr_image_path -> file on this machine. Uses only the filename, so
// old absolute paths saved on another PC still resolve.
function qrFilePath($stored) {
    if (!$stored) return null;
    $file = QRCODES_PATH . '/' . basename(str_replace('\\', '/', $stored));
    return file_exists($file) ? $file : null;
}

function isValidEventDate($date) {
    $ts = strtotime($date);
    if ($ts === false) return false;
    $year = (int)date('Y', $ts);
    return $year >= 2000 && $year <= 2100;
}
