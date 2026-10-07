<?php

function getActiveSMTPAccountIndex() {
    $index = 0;
    if (file_exists(SMTP_STATE_FILE)) {
        $stored = trim(@file_get_contents(SMTP_STATE_FILE));
        if ($stored !== '' && ctype_digit($stored)) {
            $index = (int)$stored;
        }
    }
    $max = count(SMTP_ACCOUNTS) - 1;
    if ($index > $max) $index = $max;
    if ($index < 0) $index = 0;
    return $index;
}

function getActiveSMTPAccount() {
    $accounts = SMTP_ACCOUNTS;
    $index = getActiveSMTPAccountIndex();
    return $accounts[$index];
}

function advanceSMTPAccount() {
    $accounts = SMTP_ACCOUNTS;
    $current = getActiveSMTPAccountIndex();
    $next = $current + 1;

    if (!isset($accounts[$next])) {
        return false; 
    }

    @file_put_contents(SMTP_STATE_FILE, (string)$next);
    logActivity('SMTP Fallback', "Switched from account #{$current} to #{$next} (quota exceeded)");
    return $accounts[$next];
}

function resetSMTPAccount() {
    @file_put_contents(SMTP_STATE_FILE, '0');
}

function isQuotaExceededError($message) {
    if (!$message) return false;
    $needles = [
        'daily user sending limit exceeded',
        'daily sending quota exceeded',
        '5.4.5',
    ];
    $lower = strtolower($message);
    foreach ($needles as $needle) {
        if (strpos($lower, $needle) !== false) {
            return true;
        }
    }
    return false;
}
