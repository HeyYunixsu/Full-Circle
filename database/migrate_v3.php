<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: text/plain; charset=utf-8');

function colExists($conn, $table, $col) {
    $sql = "SELECT COUNT(*) c FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $s = $conn->prepare($sql);
    $s->bind_param("ss", $table, $col);
    $s->execute();
    return (int)$s->get_result()->fetch_assoc()['c'] > 0;
}

function tableExists($conn, $table) {
    $sql = "SELECT COUNT(*) c FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
    $s = $conn->prepare($sql);
    $s->bind_param("s", $table);
    $s->execute();
    return (int)$s->get_result()->fetch_assoc()['c'] > 0;
}

function addCol($conn, $table, $col, $definition) {
    if (colExists($conn, $table, $col)) {
        echo "SKIP  {$table}.{$col} — already exists\n";
        return;
    }
    if ($conn->query("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$definition}")) {
        echo "OK    {$table}.{$col} added\n";
    } else {
        echo "FAIL  {$table}.{$col} — " . $conn->error . "\n";
    }
}

echo "FULL_EVENT MIGRATION v3\n";

echo "-- attendees --\n";
addCol($conn, 'attendees', 'mobile_number',  "VARCHAR(20) DEFAULT NULL AFTER `email`");
addCol($conn, 'attendees', 'designation',    "VARCHAR(255) DEFAULT NULL AFTER `company`");
addCol($conn, 'attendees', 'name_edited_by', "INT DEFAULT NULL");
addCol($conn, 'attendees', 'name_edited_at', "DATETIME DEFAULT NULL");
addCol($conn, 'attendees', 'sms_sent',       "TINYINT(1) NOT NULL DEFAULT 0");

echo "\n-- events --\n";
addCol($conn, 'events', 'archived_at', "DATETIME DEFAULT NULL");
addCol($conn, 'events', 'archived_by', "INT DEFAULT NULL");

echo "\n-- sms_queue --\n";
if (tableExists($conn, 'sms_queue')) {
    echo "SKIP  sms_queue — already exists\n";
} else {
    $sql = "CREATE TABLE sms_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        attendee_id INT NOT NULL,
        sms_type ENUM('qr_code','reminder','custom') NOT NULL DEFAULT 'qr_code',
        recipient_number VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('pending','sent','failed') DEFAULT 'pending',
        provider_ref VARCHAR(100) DEFAULT NULL,
        credits_used INT DEFAULT 0,
        sent_at DATETIME DEFAULT NULL,
        error_message TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (attendee_id) REFERENCES attendees(id) ON DELETE CASCADE,
        INDEX idx_status (status)
    ) ENGINE=InnoDB";
    echo $conn->query($sql) ? "OK    sms_queue created\n" : "FAIL  sms_queue — " . $conn->error . "\n";
}

echo "\n-- indexes --\n";
$idx = $conn->query("SHOW INDEX FROM attendees WHERE Key_name = 'idx_company'");
if ($idx && $idx->num_rows > 0) {
    echo "SKIP  attendees.idx_company — already exists\n";
} else {
    echo $conn->query("ALTER TABLE attendees ADD INDEX idx_company (company)")
        ? "OK    attendees.idx_company added\n"
        : "FAIL  idx_company — " . $conn->error . "\n";
}

echo "\nDONE. Safe to run this file more than once.\n";
