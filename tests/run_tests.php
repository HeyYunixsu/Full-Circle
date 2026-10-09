<?php
// Automated tests (Objective 4): unit, integration at system level.
// Run:  C:\xampp\php\php.exe C:\xampp\htdocs\Full_Event\tests\run_tests.php
// Kailangan naka-on ang Apache at MySQL sa XAMPP.
// Gumagawa lang ng ZZTEST records at binubura lahat pagkatapos. Walang email o SMS na pinapadala.

if (php_sapi_name() !== 'cli') { http_response_code(403); exit('CLI only'); }

require_once __DIR__ . '/../includes/automation.php';   // bootstrap + mailer + sms
require_once __DIR__ . '/../includes/excel_parser.php';
require_once __DIR__ . '/../modules/SessionManager.php';

$results = [];
function t($id, $desc, callable $fn) {
    global $results;
    try { $ok = $fn() === true; $note = $ok ? '' : 'check failed'; }
    catch (Throwable $e) { $ok = false; $note = $e->getMessage(); }
    $results[] = $ok;
    printf("%s  %-4s %s%s\n", $ok ? 'PASS' : 'FAIL', $id, $desc, $note ? "  ($note)" : '');
}

function http($path, $post = null, $jar = null) {
    $ch = curl_init(BASE_URL . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20]);
    if ($post !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    if ($jar) { curl_setopt($ch, CURLOPT_COOKIEJAR, $jar); curl_setopt($ch, CURLOPT_COOKIEFILE, $jar); }
    $body = (string)curl_exec($ch);
    $r = ['code' => curl_getinfo($ch, CURLINFO_HTTP_CODE), 'to' => (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL), 'body' => $body];
    curl_close($ch);
    return $r;
}

function login($email, $password) {
    $jar = tempnam(sys_get_temp_dir(), 'zzt');
    http('/pages/auth/login.php', ['email' => $email, 'password' => $password], $jar);
    return $jar;
}

function one($sql) { global $conn; return $conn->query($sql)->fetch_row()[0] ?? null; }

function addAttendee($event_id, $name, $email, $status = 'not_yet') {
    global $conn;
    $code = generateAttendeeCode();
    $qr   = generateQRData($code);
    $s = $conn->prepare("INSERT INTO attendees (event_id, attendee_code, full_name, email, company, qr_code, status, check_in_time)
                         VALUES (?, ?, ?, ?, 'ZZTEST Co', ?, ?, IF(? = 'checked_in', NOW(), NULL))");
    $s->bind_param("issssss", $event_id, $code, $name, $email, $qr, $status, $status);
    $s->execute();
    return ['id' => $conn->insert_id, 'code' => $code, 'qr' => $qr];
}

// Eligible for an automated message? Same condition sendDueReminders() / sendFeedbackLinks() use.
function eligible($attendee_id, $table, $col, $type) {
    return (int)one("SELECT COUNT(*) FROM attendees a WHERE a.id = $attendee_id AND " . notYetSent($table, $col, $type));
}

function cleanup() {
    global $conn, $log_start;
    $ev    = "SELECT id FROM events WHERE event_name LIKE 'ZZTEST%'";
    $att   = "SELECT id FROM attendees WHERE event_id IN ($ev)";
    $users = "SELECT id FROM users WHERE email LIKE 'zztest.%@example.test'";
    foreach ([
        "DELETE FROM session_attendance WHERE attendee_id IN ($att)",
        "DELETE FROM feedback WHERE event_id IN ($ev)",
        "DELETE FROM email_queue WHERE attendee_id IN ($att)",
        "DELETE FROM sms_queue WHERE attendee_id IN ($att)",
        "DELETE FROM email_archive WHERE attendee_id IN ($att)",
        "DELETE FROM attendees WHERE event_id IN ($ev)",
        "DELETE FROM sessions WHERE event_id IN ($ev)",
        "DELETE FROM companies WHERE event_id IN ($ev)",
        "DELETE FROM badge_templates WHERE event_id IN ($ev)",
        "DELETE FROM events WHERE event_name LIKE 'ZZTEST%'",
        "DELETE FROM activity_logs WHERE id > " . (int)$log_start . " OR user_id IN ($users)",
        "DELETE FROM users WHERE email LIKE 'zztest.%@example.test'",
    ] as $sql) $conn->query($sql);
    // AUTO_INCREMENT = 1 resets each counter to MAX(id) + 1
    foreach (['session_attendance', 'feedback', 'email_queue', 'sms_queue', 'attendees', 'sessions',
              'companies', 'events', 'activity_logs', 'users'] as $tbl) $conn->query("ALTER TABLE $tbl AUTO_INCREMENT = 1");
}

$log_start = (int)one("SELECT COALESCE(MAX(id), 0) FROM activity_logs");
cleanup();   // leftovers from an interrupted run

// ------------------------------------------------------------------ test data
$pw = 'Zztest#2026';
$hash = hashPassword($pw);
foreach (['staff', 'admin'] as $role) {
    $s = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES ('ZZTEST', ?, ?, ?, ?)");
    $email = "zztest.$role@example.test";
    $s->bind_param("ssss", $role, $email, $hash, $role);
    $s->execute();
}
$staff_id = (int)one("SELECT id FROM users WHERE email = 'zztest.staff@example.test'");

$conn->query("INSERT INTO events (event_name, event_date, event_time, location, status)
              VALUES ('ZZTEST Event A', CURDATE(), '09:00', 'ZZTEST Hall', 'ongoing')");
$eA = $conn->insert_id;
$conn->query("INSERT INTO events (event_name, event_date, event_time, location, status)
              VALUES ('ZZTEST Event B', CURDATE() + INTERVAL 1 DAY, '09:00', 'ZZTEST Hall', 'upcoming')");
$eB = $conn->insert_id;
$conn->query("INSERT INTO companies (event_id, company_name, max_attendees) VALUES ($eA, 'ZZTEST Co', 2)");
$conn->query("INSERT INTO sessions (event_id, session_name) VALUES ($eA, 'ZZTEST Session')");
$sess = $conn->insert_id;

$conn->query("INSERT INTO events (event_name, event_date, event_time, location, status)
              VALUES ('ZZTEST Event C', CURDATE(), '09:00', 'ZZTEST Hall', 'upcoming')");
$eC = $conn->insert_id;

$a1 = addAttendee($eA, 'ZZTEST Juan Cruz', 'zztest.a1@example.test');
$a2 = addAttendee($eA, 'ZZTEST Maria Santos', 'zztest.a2@example.test', 'checked_in');
$b1 = addAttendee($eB, 'ZZTEST Pedro Reyes', 'zztest.b1@example.test');
$c1 = addAttendee($eC, 'ZZTEST Lea Ramos', 'zztest.c1@example.test');

try {
// ------------------------------------------------------------------ UNIT
echo "\n== UNIT TESTS ==\n";
t('U01', 'normalizePHMobile: 09171234567 -> 639171234567', fn() => normalizePHMobile('09171234567') === '639171234567');
t('U02', 'normalizePHMobile: +63 917 123 4567 -> 639171234567', fn() => normalizePHMobile('+63 917 123 4567') === '639171234567');
t('U03', 'normalizePHMobile: 9171234567 (walang 0) -> 639171234567', fn() => normalizePHMobile('9171234567') === '639171234567');
t('U04', 'normalizePHMobile: maling numero (12345, blank) -> false', fn() => normalizePHMobile('12345') === false && normalizePHMobile('') === false);
t('U05', 'formatPHMobileDisplay: 639171234567 -> 0917 123 4567', fn() => formatPHMobileDisplay('639171234567') === '0917 123 4567');
t('U06', 'smsCreditCost: 160 chars = 1 credit, 161 chars = 2', fn() => smsCreditCost(str_repeat('a', 160)) === 1 && smsCreditCost(str_repeat('a', 161)) === 2);
t('U07', 'isValidEventDate: 2026 valid; 1999, 2101 at "abc" invalid', fn() =>
    isValidEventDate('2026-12-01') && !isValidEventDate('1999-01-01') && !isValidEventDate('2101-01-01') && !isValidEventDate('abc'));
t('U08', 'generateQRData: format FC-<id>-<code>, iba-iba bawat tawag', fn() =>
    (bool)preg_match('/^FC-[0-9A-F]+-123456789$/', generateQRData('123456789')) && generateQRData('1') !== generateQRData('1'));
t('U09', 'hashPassword/verifyPassword: tamang password lang ang tinatanggap', function () {
    $h = hashPassword('secret123');
    return $h !== 'secret123' && verifyPassword('secret123', $h) && !verifyPassword('wrong', $h);
});
t('U10', 'capacityBadge: Full, Over by N, Almost full; walang badge kung may space o walang limit', fn() =>
    str_contains(capacityBadge(100, 100), 'Full') && str_contains(capacityBadge(105, 100), 'Over by 5')
    && str_contains(capacityBadge(90, 100), 'Almost full') && capacityBadge(50, 100) === '' && capacityBadge(500, null) === '');
t('U11', 'canCreateRole: super admin -> admin/staff; admin -> staff lang; staff -> wala', function () {
    $_SESSION['role'] = 'super_admin'; $sa = canCreateRole('admin') && canCreateRole('staff') && !canCreateRole('super_admin');
    $_SESSION['role'] = 'admin';       $ad = canCreateRole('staff') && !canCreateRole('admin');
    $_SESSION['role'] = 'staff';       $st = !canCreateRole('staff');
    return $sa && $ad && $st;
});
t('U12', 'canEditAttendee: archived locked sa lahat; staff ongoing lang', function () {
    $_SESSION['role'] = 'admin'; $ad = canEditAttendee('upcoming') && !canEditAttendee('archived');
    $_SESSION['role'] = 'staff'; $st = canEditAttendee('ongoing') && !canEditAttendee('upcoming') && !canEditAttendee('archived');
    unset($_SESSION['role']);
    return $ad && $st;
});
t('U13', 'parseAttendeesCSV: header, semicolon delimiter, duplicate email tinatanggal', function () {
    $f = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($f, "name;email;company;mobile\nJuan Cruz;juan@sap.com;SAP;09171234567\n"
                        . "Maria Santos;maria@delmonte.com;Delmonte;\nJuan Ulit;juan@sap.com;SAP;\n");
    $r = parseAttendeesCSV($f);
    unlink($f);
    return $r['delimiter'] === ';' && $r['total'] === 2 && $r['data'][0]['company'] === 'SAP'
        && $r['data'][1]['email'] === 'maria@delmonte.com';
});
t('U14', 'parseAttendeesCSV: file na walang email -> may error message', function () {
    $f = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($f, "name,company\nJuan Cruz,SAP\n");
    $r = parseAttendeesCSV($f);
    unlink($f);
    return $r['success'] === false && count($r['errors']) > 0;
});
t('U15', 'feedbackLink: BASE_URL + naka-encode na QR value', fn() =>
    feedbackLink('FC-A B') === rtrim(BASE_URL, '/') . '/pages/feedback/form.php?c=FC-A+B');
t('U16', 'capitalizeWords: "juan dela cruz" -> "Juan Dela Cruz"; "SAP" at "IT" hindi binabago', fn() =>
    capitalizeWords(' juan dela cruz ') === 'Juan Dela Cruz' && capitalizeWords('SAP philippines') === 'SAP Philippines'
    && capitalizeWords('IT') === 'IT' && capitalizeWords('mary-ann') === 'Mary-Ann');
t('U17', 'checkinClosedReason: bukas kapag ongoing o event day; sarado bago ang araw at kapag tapos na', function () {
    $d = fn($days) => date('Y-m-d', strtotime("$days days"));
    return checkinClosedReason(['status' => 'ongoing',   'event_date' => $d(+5)]) === null
        && checkinClosedReason(['status' => 'upcoming',  'event_date' => $d(0)]) === null
        && str_starts_with((string)checkinClosedReason(['status' => 'upcoming', 'event_date' => $d(+3)]), 'Check-in opens on')
        && str_starts_with((string)checkinClosedReason(['status' => 'upcoming', 'event_date' => $d(-2)]), 'The event date has passed')
        && str_contains((string)checkinClosedReason(['status' => 'completed', 'event_date' => $d(0)]), 'closed')
        && str_contains((string)checkinClosedReason(['status' => 'archived',  'event_date' => $d(0)]), 'closed');
});

// ------------------------------------------------------------------ INTEGRATION (PHP + database)
echo "\n== INTEGRATION TESTS ==\n";
t('I01', 'generateAttendeeCode: 9 digits at wala pang kaparehas sa database', function () {
    $c = generateAttendeeCode();
    return (bool)preg_match('/^\d{9}$/', $c) && one("SELECT COUNT(*) FROM attendees WHERE attendee_code = '$c'") == 0;
});
t('I02', 'Bawal ang parehong email sa iisang event (uniq_event_email)', function () use ($eA) {
    try { addAttendee($eA, 'ZZTEST Dup', 'zztest.a1@example.test'); return false; }
    catch (mysqli_sql_exception $e) { return $e->getCode() === 1062; }
});
t('I03', 'Parehong email pwede sa ibang event', function () use ($eB) {
    return addAttendee($eB, 'ZZTEST Juan Cruz', 'zztest.a1@example.test')['id'] > 0;
});
t('I04', 'getEventStats: total 2, checked in 1, not yet 1, 50%', function () use ($eA) {
    $s = getEventStats($eA);
    return $s['total'] === 2 && $s['checked_in'] === 1 && $s['not_yet'] === 1 && $s['percentage'] == 50;
});
t('I10', 'getEventStats([A, B]): pinagsamang bilang ng dalawang event', function () use ($eA, $eB) {
    $both = getEventStats([$eA, $eB]);
    $a = getEventStats($eA); $b = getEventStats($eB);
    return $both['total'] === $a['total'] + $b['total'] && $both['checked_in'] === $a['checked_in'] + $b['checked_in'];
});
t('I05', 'companyCapacity: ZZTEST Co = 2 registered / limit 2, badge "Full"', function () use ($eA) {
    $row = array_values(array_filter(companyCapacity($eA), fn($r) => $r['name'] === 'ZZTEST Co'))[0];
    return (int)$row['total'] === 2 && (int)$row['checked_in'] === 1 && (int)$row['cap'] === 2
        && str_contains(capacityBadge($row['total'], $row['cap']), 'Full');
});
t('I06', 'Session scan: unang scan nare-record, pangalawang scan hindi na nadodoble', function () use ($sess, $a2, $staff_id) {
    global $conn;
    $m = new SessionManager($conn);
    return $m->recordScan($sess, $a2['id'], $staff_id) === true
        && $m->recordScan($sess, $a2['id'], $staff_id) === false && count($m->attendees($sess)) === 1;
});
t('I07', 'Automation: feedback link hindi pinapadala ulit kapag "sent" na', function () use ($a2) {
    global $conn;
    $before = eligible($a2['id'], 'email_queue', 'email_type', 'feedback');
    $conn->query("INSERT INTO email_queue (attendee_id, email_type, recipient_email, subject, body, status)
                  VALUES ({$a2['id']}, 'feedback', 'zztest.a2@example.test', 'ZZTEST', 'ZZTEST', 'sent')");
    return $before === 1 && eligible($a2['id'], 'email_queue', 'email_type', 'feedback') === 0;
});
t('I08', 'Automation: failed reminder inuulit hanggang 3 beses lang', function () use ($a1) {
    global $conn;
    $fail = "INSERT INTO sms_queue (attendee_id, sms_type, recipient_number, message, status)
             VALUES ({$a1['id']}, 'reminder', '639171234567', 'ZZTEST', 'failed')";
    $conn->query($fail); $conn->query($fail);
    $after2 = eligible($a1['id'], 'sms_queue', 'sms_type', 'reminder');
    $conn->query($fail);
    return $after2 === 1 && eligible($a1['id'], 'sms_queue', 'sms_type', 'reminder') === 0;
});
t('I09', 'logActivity: may bagong row sa activity_logs', function () {
    logActivity('ZZTEST Action', 'ZZTEST details');
    return one("SELECT COUNT(*) FROM activity_logs WHERE action = 'ZZTEST Action'") == 1;
});

// ------------------------------------------------------------------ SYSTEM (through the web server)
echo "\n== SYSTEM TESTS (HTTP) ==\n";
$staff = login('zztest.staff@example.test', $pw);
$admin = login('zztest.admin@example.test', $pw);
$scan  = fn($ev, $code, $confirm, $jar) => json_decode(http('/api/checkin/checkin.php',
            ['event_id' => $ev, 'code' => $code] + ($confirm ? ['confirmed' => '1'] : []), $jar)['body'], true);

t('S01', 'Hindi naka-login -> dashboard nire-redirect sa login', fn() =>
    str_contains(http('/pages/dashboard/index.php')['to'], '/pages/auth/login.php'));
t('S02', 'Login na mali ang password -> "Invalid email or password."', fn() =>
    str_contains(http('/pages/auth/login.php', ['email' => 'zztest.staff@example.test', 'password' => 'wrong'])['body'], 'Invalid email or password.'));
t('S03', 'Login na tama -> pasok sa dashboard', fn() =>
    http('/pages/dashboard/index.php', null, $staff)['code'] === 200);
t('S04', 'Staff: Reports, Companies, Feedback, Accounts, Create Event, Upload -> bawal (redirect)', function () use ($staff) {
    foreach (['/pages/reports/index.php', '/pages/companies/index.php', '/pages/feedback/index.php',
              '/pages/accounts/index.php', '/pages/events/create.php', '/pages/attendees/upload.php?event_id=1'] as $p) {
        $r = http($p, null, $staff);
        if ($r['code'] !== 302 || !str_contains($r['to'], '/pages/dashboard/')) return false;
    }
    return true;
});
t('S05', 'Admin: Reports at Companies -> bukas (200)', fn() =>
    http('/pages/reports/index.php', null, $admin)['code'] === 200 && http('/pages/companies/index.php', null, $admin)['code'] === 200);
t('S06', 'QR scan (preview): nakita ang attendee, hindi pa naka-check in', function () use ($scan, $eA, $a1, $staff) {
    $r = $scan($eA, $a1['qr'], false, $staff);
    return $r['success'] && $r['mode'] === 'preview' && !$r['already_checked_in']
        && one("SELECT status FROM attendees WHERE id = {$a1['id']}") === 'not_yet';
});
t('S07', 'QR scan (confirm): naka-check in na, may oras', function () use ($scan, $eA, $a1, $staff) {
    $r = $scan($eA, $a1['qr'], true, $staff);
    return $r['success'] && $r['mode'] === 'confirmed'
        && one("SELECT status FROM attendees WHERE id = {$a1['id']} AND check_in_time IS NOT NULL") === 'checked_in';
});
t('S08', 'QR scan ulit: "Already checked in", walang dobleng check-in', function () use ($scan, $eA, $a1, $staff) {
    $r = $scan($eA, $a1['qr'], true, $staff);
    return $r['success'] === false && $r['already_checked_in'] === true;
});
t('S09', 'Attendee code (manual entry) gumagana rin sa scan', function () use ($scan, $eA, $a2, $staff) {
    $r = $scan($eA, $a2['code'], false, $staff);
    return $r['success'] && $r['already_checked_in'] === true;
});
t('S10', 'QR ng ibang event -> "not recognized"', function () use ($scan, $eA, $b1, $staff) {
    $r = $scan($eA, $b1['qr'], true, $staff);
    return $r['success'] === false && str_contains($r['message'], 'not recognized');
});
t('S11', 'Real-time poll: lumalabas ang bagong check-in at tamang count', function () use ($eA, $a1, $staff) {
    $since = one("SELECT NOW() - INTERVAL 5 MINUTE");
    $r = json_decode(http('/api/attendees/poll.php?event_id=' . $eA . '&since=' . urlencode($since), null, $staff)['body'], true);
    return in_array($a1['id'], array_column($r['attendees'], 'id')) && $r['stats']['checked_in'] === 2;
});
t('S12', 'Check-in API na walang login -> redirect, walang nabago', function () use ($eA, $b1) {
    $r = http('/api/checkin/checkin.php', ['event_id' => $eA, 'code' => $b1['qr'], 'confirmed' => '1']);
    return $r['code'] === 302 && one("SELECT status FROM attendees WHERE id = {$b1['id']}") === 'not_yet';
});
t('S13', 'cron/notify.php sa browser -> 403 (CLI lang)', fn() => http('/cron/notify.php')['code'] === 403);
t('S14', 'QR page ng attendee: tamang pangalan; maling code -> "QR Not Found"', fn() =>
    str_contains(http('/pages/qr/view.php?code=' . $a1['code'])['body'], 'ZZTEST Juan Cruz')
    && str_contains(http('/pages/qr/view.php?code=000')['body'], 'QR Not Found'));
t('S15', 'Feedback form: rating 0 -> error; 5 -> saved; ulit (4) -> update, hindi doble', function () use ($a1) {
    $url = '/pages/feedback/form.php?c=' . urlencode($a1['qr']);
    $bad = str_contains(http($url, ['rating' => 0])['body'], 'rating from 1 to 5');
    http($url, ['rating' => 5, 'comment' => 'ZZTEST']);
    http($url, ['rating' => 4, 'comment' => 'ZZTEST']);
    $rows = one("SELECT CONCAT(COUNT(*), ':', MAX(rating)) FROM feedback WHERE attendee_id = {$a1['id']} AND session_id IS NULL");
    return $bad && $rows === '1:4';
});
t('S16', 'Check-in rule: event bukas pa (upcoming) -> sarado, hindi na-check in', function () use ($scan, $eB, $b1, $staff) {
    $r = $scan($eB, $b1['qr'], true, $staff);
    return $r['success'] === false && !empty($r['closed']) && str_starts_with($r['message'], 'Check-in opens on')
        && one("SELECT status FROM attendees WHERE id = {$b1['id']}") === 'not_yet';
});
t('S17', 'Check-in rule: event day (upcoming) -> bukas; unang check-in ginagawang Ongoing ang event', function () use ($scan, $eC, $c1, $staff) {
    $r = $scan($eC, $c1['qr'], true, $staff);
    return $r['success'] && one("SELECT status FROM events WHERE id = $eC") === 'ongoing';
});
t('S18', 'Staff hindi makapagpalit ng event status (admin lang)', function () use ($eB, $staff) {
    $r = http('/api/events/update_status.php?id=' . $eB . '&status=completed', null, $staff);
    return $r['code'] === 302 && str_contains($r['to'], '/pages/dashboard/') && one("SELECT status FROM events WHERE id = $eB") === 'upcoming';
});
t('S19', 'Session scan: naka-check in na sa event -> nare-record sa session; ulit -> "Already recorded"', function () use ($eA, $a1, $sess, $staff) {
    $post = fn() => json_decode(http('/api/checkin/scan.php', ['event_id' => $eA, 'code' => $a1['qr'], 'session_id' => $sess], $staff)['body'], true);
    $first = $post(); $second = $post();
    return $first['success'] && $first['already_checked_in'] === false && str_starts_with($first['message'], 'Recorded for')
        && $second['success'] && $second['already_checked_in'] === true
        && one("SELECT COUNT(*) FROM session_attendance WHERE session_id = $sess AND attendee_id = {$a1['id']}") == 1;
});
t('S20', 'Session scan: session ng ibang event -> tinatanggihan', function () use ($eB, $b1, $sess, $staff) {
    global $conn;
    $conn->query("UPDATE events SET status = 'ongoing' WHERE id = $eB");   // open check-in so only the session check is tested
    $r = json_decode(http('/api/checkin/scan.php', ['event_id' => $eB, 'code' => $b1['qr'], 'session_id' => $sess], $staff)['body'], true);
    $conn->query("UPDATE events SET status = 'upcoming' WHERE id = $eB");
    return $r['success'] === false && str_contains($r['message'], 'does not belong')
        && one("SELECT status FROM attendees WHERE id = {$b1['id']}") === 'not_yet';
});
t('S21', 'Dashboard: kapag 2+ events ang live, kabuuan ng lahat ang nasa tiles at may "Live now" label', function () use ($staff) {
    $ids = array_map('intval', array_column(overviewEvents()[0], 'id'));
    $want = getEventStats($ids)['total'];
    $html = http('/pages/dashboard/index.php', null, $staff)['body'];
    return count($ids) >= 1 && str_contains($html, 'Live now') && str_contains($html, 'Registered <em>' . $want . '</em>');
});
t('S22', 'Open check-in: 2+ live events -> "Which event" page na may link sa bawat isa', function () use ($staff) {
    $live = (int)one("SELECT COUNT(*) FROM events WHERE status = 'ongoing'");
    $r = http('/pages/checkin/choose.php', null, $staff);
    return $live < 2 ? $r['code'] === 302 : ($r['code'] === 200 && substr_count($r['body'], 'Open check-in</a>') === $live);
});
t('S23', 'Create Event na naipadala nang 2 beses (double-click) -> isang event lang', function () use ($admin) {
    $form = ['event_name' => 'ZZTEST Double Click', 'event_date' => date('Y-m-d', strtotime('+30 days')), 'event_time' => '09:00', 'location' => 'ZZTEST Hall'];
    $first = http('/pages/events/create.php', $form, $admin);
    $second = http('/pages/events/create.php', $form, $admin);
    return $first['code'] === 302 && $second['to'] === $first['to']
        && one("SELECT COUNT(*) FROM events WHERE event_name = 'ZZTEST Double Click'") == 1;
});
t('S24', 'Dashboard switcher: ?ov=<event> -> bilang ng event na iyon lang; walang ?ov -> kabuuan', function () use ($staff, $eA) {
    $one = getEventStats($eA)['total'];
    $html = http('/pages/dashboard/index.php?ov=' . $eA, null, $staff)['body'];
    $live = count(overviewEvents()[0]);
    return $live < 2 || (str_contains($html, 'Registered <em>' . $one . '</em>') && str_contains($html, 'class="ov-switch"'));
});
t('S25', 'Create Event na may badge design -> naka-save sa event; badge page bukas sa design na iyon', function () use ($admin) {
    $tpl = (int)one("SELECT id FROM badge_templates WHERE layout_json IS NOT NULL ORDER BY id DESC LIMIT 1");
    $form = ['event_name' => 'ZZTEST Badge Pick', 'event_date' => date('Y-m-d', strtotime('+30 days')), 'event_time' => '09:00', 'location' => 'ZZTEST Hall', 'badge_template_id' => $tpl];
    http('/pages/events/create.php', $form, $admin);
    $ev = (int)one("SELECT id FROM events WHERE event_name = 'ZZTEST Badge Pick'");
    $a = addAttendee($ev, 'ZZTEST Badge Person', 'zztest.badge@example.test');
    $html = http('/pages/checkin/badge.php?attendee_id=' . $a['id'], null, $admin)['body'];
    return $tpl && (int)one("SELECT badge_template_id FROM events WHERE id = $ev") === $tpl
        && str_contains($html, 'data-template="' . $tpl . '"');
});
t('S26', 'Badge design ng event: staff bawal magpalit; admin puwede', function () use ($admin, $staff) {
    $ev = (int)one("SELECT id FROM events WHERE event_name = 'ZZTEST Badge Pick'");
    $before = (int)one("SELECT badge_template_id FROM events WHERE id = $ev");
    $other = (int)one("SELECT id FROM badge_templates WHERE layout_json IS NOT NULL AND id <> $before ORDER BY id LIMIT 1");
    http('/api/events/set_badge.php', ['event_id' => $ev, 'badge_template_id' => $other], $staff);
    $after_staff = (int)one("SELECT badge_template_id FROM events WHERE id = $ev");
    http('/api/events/set_badge.php', ['event_id' => $ev, 'badge_template_id' => $other], $admin);
    return $after_staff === $before && (int)one("SELECT badge_template_id FROM events WHERE id = $ev") === $other;
});
t('S27', 'Pag-edit ng pangalan (badge page): staff bawal kung hindi pa ongoing; ongoing -> nase-save', function () use ($eB, $b1, $staff) {
    global $conn;
    $post = fn() => json_decode(http('/api/attendees/update.php', ['id' => $b1['id'], 'full_name' => 'ZZTEST Fixed Name'], $staff)['body'], true);
    $refused = $post();                                                       // eB is upcoming
    $conn->query("UPDATE events SET status = 'ongoing' WHERE id = $eB");
    $saved = $post();
    $conn->query("UPDATE events SET status = 'upcoming' WHERE id = $eB");
    return $refused['success'] === false && $saved['success'] === true
        && one("SELECT full_name FROM attendees WHERE id = {$b1['id']}") === 'ZZTEST Fixed Name';
});
t('S28', 'Undo check-in: staff puwede sa loob ng 5 min; lampas 5 min admin lang', function () use ($a2, $staff, $admin) {
    global $conn;
    $undo = fn($jar) => json_decode(http('/api/checkin/undo.php', ['attendee_id' => $a2['id'], 'reason' => 'ZZTEST', 'ajax' => 1], $jar)['body'], true);
    $conn->query("UPDATE attendees SET status = 'checked_in', check_in_time = NOW() WHERE id = {$a2['id']}");
    $fresh = $undo($staff);
    $conn->query("UPDATE attendees SET status = 'checked_in', check_in_time = NOW() - INTERVAL 10 MINUTE WHERE id = {$a2['id']}");
    $old_staff = $undo($staff);
    $old_admin = $undo($admin);
    $after = one("SELECT status FROM attendees WHERE id = {$a2['id']}");
    $conn->query("UPDATE attendees SET status = 'checked_in', check_in_time = NOW() WHERE id = {$a2['id']}");
    return $fresh['success'] && !$old_staff['success'] && $old_admin['success'] && $after === 'not_yet';
});
t('S29', 'Walk-in na kapangalan ng nasa listahan -> "Is this the same person?"; "Yes" -> check in, walang bagong record', function () use ($eA, $staff) {
    $p = addAttendee($eA, 'ZZTEST Ana Lim', 'zztest.ana@example.test');
    $url = '/pages/checkin/walkin.php?event_id=' . $eA;
    $form = ['full_name' => 'zztest  ana lim', 'email' => 'zztest.ana2@example.test', 'mobile_number' => '09171234567', 'company' => 'ZZTEST Co'];
    $warn = http($url, $form, $staff);
    $yes = http($url, $form + ['use_existing' => $p['id']], $staff);
    return str_contains($warn['body'], 'Is this the same person?') && $yes['code'] === 302
        && one("SELECT COUNT(*) FROM attendees WHERE event_id = $eA AND full_name LIKE 'ZZTEST Ana Lim'") == 1
        && one("SELECT status FROM attendees WHERE id = {$p['id']}") === 'checked_in';
});
t('S30', 'Login: 5 maling password -> naka-lock 15 min; tamang password habang naka-lock -> bawal pa rin', function () use ($hash, $pw) {
    global $conn;
    $conn->query("INSERT INTO users (first_name, last_name, email, password, role) VALUES ('ZZTEST', 'Lock', 'zztest.lock@example.test', '$hash', 'staff')");
    for ($i = 0; $i < 5; $i++) $last = http('/pages/auth/login.php', ['email' => 'zztest.lock@example.test', 'password' => 'wrong']);
    $right = http('/pages/auth/login.php', ['email' => 'zztest.lock@example.test', 'password' => $pw]);
    return str_contains($last['body'], 'locked for 15 minutes') && $right['code'] === 200 && str_contains($right['body'], 'locked for')
        && one("SELECT locked_until > NOW() FROM users WHERE email = 'zztest.lock@example.test'") == 1;
});
t('S31', 'Walk-in: "Send a copy" naka-off by default -> walang email o SMS na naipapadala', function () use ($eA, $staff) {
    $form = ['full_name' => 'ZZTEST Walk Copy', 'email' => 'zztest.copy@example.test', 'mobile_number' => '09171234567', 'company' => 'ZZTEST Co'];
    $r = http('/pages/checkin/walkin.php?event_id=' . $eA, $form, $staff);
    $id = (int)one("SELECT id FROM attendees WHERE email = 'zztest.copy@example.test'");
    return $r['code'] === 302 && $id
        && one("SELECT COUNT(*) FROM email_queue WHERE attendee_id = $id") == 0
        && one("SELECT COUNT(*) FROM sms_queue WHERE attendee_id = $id") == 0;
});
t('S32', 'Walk-in desk: badge page -> "Next Walk-in"; pag-print babalik sa walk-in form', function () use ($eA, $staff) {
    $form = ['full_name' => 'ZZTEST Booth Two', 'email' => 'zztest.booth@example.test', 'mobile_number' => '09171234567', 'company' => 'ZZTEST Co'];
    $reg = http('/pages/checkin/walkin.php?event_id=' . $eA, $form, $staff);
    $id = (int)one("SELECT id FROM attendees WHERE email = 'zztest.booth@example.test'");
    $badge = http('/pages/checkin/badge.php?attendee_id=' . $id . '&from=walkin', null, $staff);
    $printed = http('/pages/checkin/badge.php?attendee_id=' . $id . '&from=walkin&print=1', null, $staff);
    return str_contains($reg['to'], 'from=walkin') && str_contains($badge['body'], 'Next Walk-in')
        && str_contains($printed['to'], '/pages/checkin/walkin.php?event_id=' . $eA)
        && one("SELECT badge_printed FROM attendees WHERE id = $id") == 1;
});

foreach ([$staff, $admin] as $jar) @unlink($jar);
} finally {
    cleanup();
}

$pass = count(array_filter($results));
printf("\n%d/%d passed. Test data removed.\n", $pass, count($results));
exit($pass === count($results) ? 0 : 1);
