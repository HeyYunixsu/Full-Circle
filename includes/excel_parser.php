<?php
require_once __DIR__ . '/sms.php';

function detectCSVDelimiter($filepath) {
    $delimiters = [',', ';', "\t", '|'];
    $counts = array_fill_keys($delimiters, 0);

    $handle = fopen($filepath, 'r');
    if (!$handle) return ',';

    $line = '';
    while (($l = fgets($handle)) !== false) {
        $l = trim($l);
        if ($l !== '') { $line = $l; break; }
    }
    fclose($handle);

    if ($line === '') return ',';

    foreach ($delimiters as $d) {
        $counts[$d] = substr_count($line, $d);
    }

    arsort($counts);
    $top = array_key_first($counts);
    return $counts[$top] > 0 ? $top : ',';
}

function stripBOM($str) {
    if (substr($str, 0, 3) === "\xEF\xBB\xBF") {
        return substr($str, 3);
    }
    return $str;
}

function looksLikeName($str) {
    $str = trim($str);
    if ($str === '' || strlen($str) < 3) return false;
    if (strpos($str, '@') !== false) return false;         
    if (preg_match('/^\d+$/', $str)) return false;         
    if (!preg_match('/[a-zA-Z]/', $str)) return false;     
    return true;
}

function pickBestName($row, $email_index, $header = null) {
    
    if ($header && is_array($header)) {
        $name_cols = ['name', 'full name', 'fullname', 'attendee name', 'attendee', 'pangalan', 'full_name'];
        $first_cols = ['first name', 'firstname', 'given name', 'first', 'first_name'];
        $last_cols = ['last name', 'lastname', 'surname', 'family name', 'last', 'last_name'];

        foreach ($header as $i => $col) {
            if (in_array($col, $name_cols) && isset($row[$i])) {
                $val = trim((string)$row[$i]);
                if (looksLikeName($val)) return $val;
            }
        }

        $first = $last = '';
        foreach ($header as $i => $col) {
            if (in_array($col, $first_cols) && isset($row[$i])) {
                $first = trim((string)$row[$i]);
            } elseif (in_array($col, $last_cols) && isset($row[$i])) {
                $last = trim((string)$row[$i]);
            }
        }
        if ($first !== '' && $last !== '') {
            return trim($first . ' ' . $last);
        }
        if ($first !== '') return $first;
        if ($last !== '') return $last;
    }

    
    $generic_words = ['engineering','sales','marketing','admin','it','hr','finance','operations',
                      'support','manager','staff','dev','development','design','active','inactive',
                      'pre-registered','walk-in','pending'];

    $candidates = [];
    foreach ($row as $i => $val) {
        if ($i === $email_index) continue;
        $val = trim((string)$val);
        if (!looksLikeName($val)) continue;

        $lower = strtolower($val);
        
        if (in_array($lower, $generic_words)) continue;

        $score = strlen($val) + (strpos($val, ' ') !== false ? 100 : 0);
        $candidates[$score] = $val;
    }

    if (!empty($candidates)) {
        krsort($candidates);
        return reset($candidates);
    }

    return '';
}

function pickCompany($row, $name_value, $email_index, $header = null) {
    
    if ($header && is_array($header)) {
        $company_cols = ['company', 'organization', 'org', 'affiliation', 'employer',
                         'workplace', 'kompanya', 'business'];
        foreach ($header as $i => $col) {
            if (in_array($col, $company_cols) && isset($row[$i])) {
                $val = trim((string)$row[$i]);
                if ($val !== '' && strpos($val, '@') === false) return $val;
            }
        }
    }

    foreach ($row as $i => $val) {
        if ($i === $email_index) continue;
        $val = trim((string)$val);
        if ($val === '' || $val === $name_value) continue;
        if (strpos($val, '@') !== false) continue;
        if (preg_match('/^\d+$/', $val)) continue;
        if (preg_match('/^[\d\-\+\(\)\s]+$/', $val)) continue;  
        if (strlen($val) < 2 || strlen($val) > 100) continue;
        return $val;
    }
    return '';
}

function pickByHeader($row, $header, $cols) {
    if (!$header || !is_array($header)) return '';
    foreach ($header as $i => $col) {
        if (in_array($col, $cols) && isset($row[$i])) {
            $val = trim((string)$row[$i]);
            if ($val !== '') return $val;
        }
    }
    return '';
}

// Returns 09XXXXXXXXX (same format as walk-in) or '' if missing/invalid.
function pickMobile($row, $header = null) {
    $mobile_cols = ['mobile', 'mobile number', 'mobile no', 'mobile no.', 'mobile_number', 'phone',
                    'phone number', 'contact', 'contact number', 'contact no', 'contact no.',
                    'cellphone', 'cellphone number', 'cp', 'cp number', 'cell'];
    $candidates = [pickByHeader($row, $header, $mobile_cols)];
    if ($candidates[0] === '') $candidates = $row;  // no mobile column: any cell that looks like a PH mobile
    foreach ($candidates as $val) {
        $n = normalizePHMobile($val);
        if ($n) return '0' . substr($n, 2);
    }
    return '';
}

function pickDesignation($row, $header = null) {
    return pickByHeader($row, $header, ['designation', 'position', 'job title', 'job_title', 'role']);
}

function parseAttendeesCSV($filepath, $default_company = 'Other') {
    $attendees = [];
    $errors = [];
    $skipped_rows = 0;

    if (!file_exists($filepath)) {
        return ['success' => false, 'error' => 'File not found', 'data' => []];
    }

    $delimiter = detectCSVDelimiter($filepath);

    if (($handle = fopen($filepath, "r")) === FALSE) {
        return ['success' => false, 'error' => 'Could not open file', 'data' => []];
    }

    $row_number = 0;
    $seen_emails = []; 
    $header = null;    

    while (($data = fgetcsv($handle, 4096, $delimiter)) !== FALSE) {
        $row_number++;

        $data = array_map(function($v) {
            return trim(stripBOM((string)$v));
        }, $data);

        if (empty(array_filter($data, function($v) { return $v !== ''; }))) continue;

        $email = '';
        $email_index = -1;
        foreach ($data as $i => $cell) {
            if (filter_var($cell, FILTER_VALIDATE_EMAIL)) {
                $email = strtolower($cell);
                $email_index = $i;
                break;
            }
        }

        if ($email === '') {

            $looks_like_header = true;
            $non_empty_count = 0;
            foreach ($data as $cell) {
                if ($cell === '') continue;
                $non_empty_count++;
                if (preg_match('/^\d+$/', $cell)) { $looks_like_header = false; break; }
                if (strlen($cell) > 50) { $looks_like_header = false; break; }
            }
            if ($looks_like_header && $non_empty_count >= 2) {
                $header = array_map('strtolower', $data);
            }
            $skipped_rows++;
            continue;
        }

        if (isset($seen_emails[$email])) {
            $skipped_rows++;
            continue;
        }
        $seen_emails[$email] = true;

        $name = pickBestName($data, $email_index, $header);

        if ($name === '') {
            $errors[] = "Row {$row_number}: Found email '{$email}' but no name detected";
            continue;
        }

        $company = pickCompany($data, $name, $email_index, $header);
        if ($company === '') {
            $company = $default_company;
        }

        $attendees[] = [
            'name' => $name,
            'email' => $email,
            'company' => $company,
            'mobile' => pickMobile($data, $header),
            'designation' => pickDesignation($data, $header),
        ];
    }

    fclose($handle);

    if (count($attendees) === 0 && empty($errors)) {
        $errors[] = "No valid email addresses found in the file. Make sure your file contains rows with at least a name and an email.";
    }

    return [
        'success' => count($attendees) > 0,
        'data' => $attendees,
        'errors' => $errors,
        'total' => count($attendees),
        'skipped' => $skipped_rows,
        'delimiter' => $delimiter,
    ];
}

function parseAttendeesXLSX($filepath, $default_company = 'Other') {
    require_once __DIR__ . '/../vendor/SimpleXLSX/SimpleXLSX.php';

    $attendees = [];
    $errors = [];
    $skipped_rows = 0;

    if (!file_exists($filepath)) {
        return ['success' => false, 'error' => 'File not found', 'data' => []];
    }

    $xlsx = \Shuchkin\SimpleXLSX::parse($filepath);
    if (!$xlsx) {
        return [
            'success' => false,
            'error' => 'Could not read Excel file: ' . \Shuchkin\SimpleXLSX::parseError(),
            'data' => [],
        ];
    }

    $rows = $xlsx->rows(); 
    $row_number = 0;
    $seen_emails = [];
    $header = null;

    foreach ($rows as $data) {
        $row_number++;

        $data = array_map(function($v) {
            return trim(stripBOM((string)$v));
        }, $data);

        if (empty(array_filter($data, function($v) { return $v !== ''; }))) continue;

        $email = '';
        $email_index = -1;
        foreach ($data as $i => $cell) {
            if (filter_var($cell, FILTER_VALIDATE_EMAIL)) {
                $email = strtolower($cell);
                $email_index = $i;
                break;
            }
        }

        if ($email === '') {
            $looks_like_header = true;
            $non_empty_count = 0;
            foreach ($data as $cell) {
                if ($cell === '') continue;
                $non_empty_count++;
                if (preg_match('/^\d+$/', $cell)) { $looks_like_header = false; break; }
                if (strlen($cell) > 50) { $looks_like_header = false; break; }
            }
            if ($looks_like_header && $non_empty_count >= 2) {
                $header = array_map('strtolower', $data);
            }
            $skipped_rows++;
            continue;
        }

        if (isset($seen_emails[$email])) {
            $skipped_rows++;
            continue;
        }
        $seen_emails[$email] = true;

        $name = pickBestName($data, $email_index, $header);

        if ($name === '') {
            $errors[] = "Row {$row_number}: Found email '{$email}' but no name detected";
            continue;
        }

        $company = pickCompany($data, $name, $email_index, $header);
        if ($company === '') {
            $company = $default_company;
        }

        $attendees[] = [
            'name' => $name,
            'email' => $email,
            'company' => $company,
            'mobile' => pickMobile($data, $header),
            'designation' => pickDesignation($data, $header),
        ];
    }

    if (count($attendees) === 0 && empty($errors)) {
        $errors[] = "No valid email addresses found in the file. Make sure your file contains rows with at least a name and an email.";
    }

    return [
        'success' => count($attendees) > 0,
        'data' => $attendees,
        'errors' => $errors,
        'total' => count($attendees),
        'skipped' => $skipped_rows,
        'delimiter' => null, 
    ];
}

function exportAttendeesCSV($event_id, $filename = 'attendees.csv') {
    global $conn;

    $sql = "SELECT attendee_code, full_name, email, company, status, registration_type,
                   check_in_time, badge_printed
            FROM attendees WHERE event_id = ? ORDER BY id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');

    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, ['Code', 'Name', 'Email', 'Company', 'Status', 'Type', 'Check-In Time', 'Badge Printed']);

    while ($row = $result->fetch_assoc()) {
        $row['status'] = ucfirst(str_replace('_', ' ', $row['status']));
        $row['registration_type'] = ucfirst($row['registration_type']);
        $row['badge_printed'] = $row['badge_printed'] ? 'Yes' : 'No';
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}
