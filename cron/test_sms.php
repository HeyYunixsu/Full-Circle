<?php
// Send ONE real SMS through Semaphore to check the setup (uses 1 credit):
//   C:\xampp\php\php.exe C:\xampp\htdocs\Full_Event\cron\test_sms.php 09171234567

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../includes/sms.php';

$number = normalizePHMobile($argv[1] ?? '');
if (!$number) exit("Usage: php test_sms.php 09XXXXXXXXX\n");
if (!smsIsConfigured()) exit("SMS not set up: set SMS_ENABLED = true and SEMAPHORE_API_KEY in config.php.\n");
if (smsIsMock()) exit("SMS_MOCK is true, so nothing would really be sent. Set SMS_MOCK = false in config.php first.\n");

$balance = getSMSBalance();
echo 'Credit balance: ' . ($balance === null ? 'unknown (check the API key)' : $balance) . "\n";
echo 'Sender name:    ' . (SEMAPHORE_SENDER_NAME !== '' ? SEMAPHORE_SENDER_NAME : '(account default)') . "\n";

$res = sendSMS($number, SITE_SHORT . ': SMS setup check from Full_Event, sent ' . date('M j, g:i A') . '. No reply needed.');
echo ($res['success'] ? 'Accepted by Semaphore' : 'FAILED') . ": {$res['message']}\n";
if ($res['success']) {
    echo "message_id:     {$res['refs'][0]}  (status: Semaphore dashboard -> Messages)\n";
}
