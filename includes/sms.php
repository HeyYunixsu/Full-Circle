<?php
require_once __DIR__ . '/../core/bootstrap.php';

function normalizePHMobile($raw) {
    $d = preg_replace('/\D+/', '', (string)$raw);
    if ($d === '') return false;
    if (strlen($d) === 10 && $d[0] === '9')               return '63' . $d;
    if (strlen($d) === 11 && substr($d, 0, 2) === '09')   return '63' . substr($d, 1);
    if (strlen($d) === 12 && substr($d, 0, 3) === '639')  return $d;
    if (strlen($d) === 13 && substr($d, 0, 4) === '0639') return substr($d, 1);
    return false;
}

function formatPHMobileDisplay($raw) {
    $n = normalizePHMobile($raw);
    if (!$n) return $raw ?: '—';
    return '0' . substr($n, 2, 3) . ' ' . substr($n, 5, 3) . ' ' . substr($n, 8);
}

function smsIsMock() {
    return defined('SMS_MOCK') && SMS_MOCK;
}

function smsIsConfigured() {
    if (!defined('SMS_ENABLED') || !SMS_ENABLED) return false;
    if (smsIsMock()) return true;
    return defined('SEMAPHORE_API_KEY') && SEMAPHORE_API_KEY !== '';
}

function semaphoreBase() {
    return defined('SEMAPHORE_API_BASE') && SEMAPHORE_API_BASE !== ''
        ? rtrim(SEMAPHORE_API_BASE, '/')
        : 'https://api.semaphore.co/api/v4';
}

function sendSMS($numbers, $message) {
    if (!defined('SMS_ENABLED') || !SMS_ENABLED) {
        return ['success' => false, 'message' => 'SMS is disabled. Set SMS_ENABLED = true.', 'refs' => [], 'credits' => 0, 'mock' => false];
    }

    $recipient  = is_array($numbers) ? implode(',', $numbers) : $numbers;
    $recipients = is_array($numbers) ? count($numbers) : count(explode(',', $recipient));
    $credits    = $recipients * smsCreditCost($message);

    // Semaphore silently drops messages that start with "TEST" (no error, no SMS).
    if (stripos(ltrim($message), 'test') === 0) {
        return ['success' => false, 'message' => 'Message cannot start with "TEST" (Semaphore ignores it).', 'refs' => [], 'credits' => 0, 'mock' => false];
    }

    if (smsIsMock()) {
        return [
            'success' => true,
            'message' => 'MOCK — not actually sent.',
            'refs'    => ['MOCK-' . substr(md5($recipient . $message . microtime()), 0, 12)],
            'credits' => $credits,
            'mock'    => true,
        ];
    }

    if (!defined('SEMAPHORE_API_KEY') || SEMAPHORE_API_KEY === '') {
        return ['success' => false, 'message' => 'SEMAPHORE_API_KEY is empty.', 'refs' => [], 'credits' => 0, 'mock' => false];
    }

    $fields = ['apikey' => SEMAPHORE_API_KEY, 'number' => $recipient, 'message' => $message];
    if (defined('SEMAPHORE_SENDER_NAME') && SEMAPHORE_SENDER_NAME !== '') {
        $fields['sendername'] = SEMAPHORE_SENDER_NAME;
    }

    $ch = curl_init(semaphoreBase() . '/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_TIMEOUT        => 30,
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['success' => false, 'message' => 'Network error: ' . $err, 'refs' => [], 'credits' => 0, 'mock' => false];
    }

    // Success = a list of message objects with message_id. Errors come back as
    // {"field": ["reason"]} — often with HTTP 200 — so check the shape, not the code.
    $data = json_decode($raw, true);
    $refs = is_array($data) && array_is_list($data) ? array_filter(array_column($data, 'message_id')) : [];
    if (!$refs) {
        return ['success' => false, 'message' => smsError($data, $code), 'refs' => [], 'credits' => 0, 'mock' => false];
    }

    return [
        'success' => true,
        'message' => 'Sent via Semaphore.',
        'refs'    => array_map('strval', array_values($refs)),
        'credits' => $credits,
        'mock'    => false,
    ];
}

function smsError($data, $code) {
    if (is_array($data)) {
        if (!empty($data['message']) && is_string($data['message'])) return $data['message'];
        $flat = [];
        array_walk_recursive($data, function ($v) use (&$flat) {
            if (is_scalar($v) && $v !== '') $flat[] = $v;
        });
        if ($flat) return implode('; ', array_slice($flat, 0, 3));
    }
    return 'Semaphore error (HTTP ' . $code . ').';
}

function getSMSBalance() {
    if (!smsIsConfigured() || smsIsMock()) return null;

    $ch = curl_init(semaphoreBase() . '/account?' . http_build_query(['apikey' => SEMAPHORE_API_KEY]));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $data = json_decode((string)curl_exec($ch), true);
    curl_close($ch);
    return isset($data['credit_balance']) ? (float)$data['credit_balance'] : null;
}

function getQRSMSTemplate($attendee, $event) {
    $when = date('M j, g:i A', strtotime($event['event_date'] . ' ' . $event['event_time']));
    $link = rtrim(BASE_URL, '/') . '/pages/qr/view.php?code=' . $attendee['attendee_code'];
    $body = SITE_SHORT . ": Hi " . trim($attendee['full_name']) . "! "
          . "You're registered for " . $event['event_name'] . " on " . $when
          . " at " . $event['location'] . ". "
          . "Show your QR at the entrance: " . $link;
    return mb_substr($body, 0, 459);
}

function getReminderSMSTemplate($attendee, $event) {
    $when = date('M j, g:i A', strtotime($event['event_date'] . ' ' . $event['event_time']));
    $link = rtrim(BASE_URL, '/') . '/pages/qr/view.php?code=' . $attendee['attendee_code'];
    $body = SITE_SHORT . ": Reminder — " . $event['event_name'] . " is on " . $when
          . " at " . $event['location'] . ". Your QR: " . $link;
    return mb_substr($body, 0, 459);
}

function smsCreditCost($message) {
    return max(1, (int)ceil(mb_strlen($message) / 160));
}
