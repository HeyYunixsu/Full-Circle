<?php
// Hourly job: day-before reminders (email + SMS) and post-event feedback links.
// Run from Task Scheduler:  C:\xampp\php\php.exe C:\xampp\htdocs\Full_Event\cron\notify.php
// Safe to run any number of times; nothing is sent twice.

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../includes/automation.php';

$r = sendDueReminders();
$f = sendFeedbackLinks();

$summary = "reminder email {$r['email_sent']} sent/{$r['email_failed']} failed, "
         . "reminder SMS {$r['sms_sent']} sent/{$r['sms_failed']} failed, "
         . "feedback email {$f['email_sent']} sent/{$f['email_failed']} failed";

if (array_sum($r) + array_sum($f) > 0) {
    logActivity('Automated Notifications', $summary);
}
echo date('Y-m-d H:i:s') . "  {$summary}\n";
