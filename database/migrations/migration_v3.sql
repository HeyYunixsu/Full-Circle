-- ================================================================
-- FULL_EVENT — MIGRATION v3
-- ================================================================
-- Para sa EXISTING mong database (full_event_db).
-- Ginawa base sa dump mo: Sep 10, 2026, MariaDB 10.4.32.
--
-- HINDI ito nagde-delete ng data. Additive lang.
--
-- PAANO:
--   1. phpMyAdmin → piliin ang full_event_db
--   2. SQL tab → paste ito → Go
--
-- Kung ayaw mong mag-paste, may PHP version:
--   http://localhost/Full_Event/database/migrate_v3.php
--   (safe patakbuhin nang paulit-ulit)
-- ================================================================

USE full_event_db;

-- ================================================================
-- 1. ATTENDEES — SMS tracking
-- ================================================================
-- May mobile_number, designation, name_edited_by, name_edited_at ka na.
-- Ito lang ang kulang:

ALTER TABLE `attendees`
  ADD COLUMN IF NOT EXISTS `sms_sent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `email_sent`;

-- Pampabilis ng search sa company (dati walang index dito)
ALTER TABLE `attendees`
  ADD INDEX IF NOT EXISTS `idx_company` (`company`);

ALTER TABLE `attendees`
  ADD INDEX IF NOT EXISTS `idx_mobile` (`mobile_number`);


-- ================================================================
-- 2. EVENTS — permanent archive
-- ================================================================
ALTER TABLE `events`
  ADD COLUMN IF NOT EXISTS `archived_at` DATETIME DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `archived_by` INT(11) DEFAULT NULL AFTER `archived_at`;

-- Punan ang archived_at ng mga naka-archive na (kung meron)
UPDATE `events`
   SET `archived_at` = `updated_at`
 WHERE `status` = 'archived' AND `archived_at` IS NULL;


-- ================================================================
-- 3. SMS_QUEUE — bagong table
-- ================================================================
-- NOTE: may `email_archive` table ka na na may recipient_mobile at
-- sent_via('email','sms','both') — pero walang code na gumagamit nito.
-- Tanungin mo teammates mo. Kung sila ang may plano diyan, pwedeng
-- i-drop ang sms_queue at gamitin na lang ang email_archive.
-- Hiwalay ito kasi may retry/pending semantics ang queue na wala sa archive.

CREATE TABLE IF NOT EXISTS `sms_queue` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `attendee_id` INT(11) NOT NULL,
  `sms_type` ENUM('qr_code','reminder','custom') NOT NULL DEFAULT 'qr_code',
  `recipient_number` VARCHAR(20) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('pending','sent','failed') DEFAULT 'pending',
  `provider_ref` VARCHAR(100) DEFAULT NULL,
  `credits_used` INT(11) DEFAULT 0,
  `sent_at` DATETIME DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_attendee` (`attendee_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `sms_queue_ibfk_1` FOREIGN KEY (`attendee_id`)
    REFERENCES `attendees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 4. DATA CLEANUP — opsyonal pero rekomendado
-- ================================================================

-- Yung Windows absolute paths sa qr_image_path ay masisira pag lumipat ka
-- ng PC o nag-deploy. Ginagawa itong relative:
--   C:\xampp\htdocs\Full_Event/assets/qrcodes/x.png  →  assets/qrcodes/x.png
--
-- I-uncomment kung handa ka na. Mag-backup muna.
--
-- UPDATE `attendees`
--    SET `qr_image_path` = SUBSTRING(`qr_image_path`,
--        LOCATE('assets/qrcodes/', `qr_image_path`))
--  WHERE `qr_image_path` LIKE '%assets/qrcodes/%'
--    AND `qr_image_path` LIKE 'C:%';
--
-- TANDAAN: kailangan mo ring baguhin ang getQRCodeImageUrl() sa
-- includes/qrcode.php para tumanggap ng relative path. Wag mo pang
-- patakbuhin ito hangga't hindi pa nababago ang code.


-- Mga event na taong 1212 at 0012 (galing sa walang date validation):
-- SELECT id, event_name, event_date FROM events WHERE YEAR(event_date) < 2000;


-- ================================================================
-- 5. VERIFY
-- ================================================================
SELECT 'attendees.sms_sent'    AS item,
       COUNT(*) AS found FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = 'full_event_db' AND TABLE_NAME = 'attendees'
   AND COLUMN_NAME = 'sms_sent'
UNION ALL
SELECT 'events.archived_at', COUNT(*) FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = 'full_event_db' AND TABLE_NAME = 'events'
   AND COLUMN_NAME = 'archived_at'
UNION ALL
SELECT 'sms_queue table', COUNT(*) FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = 'full_event_db' AND TABLE_NAME = 'sms_queue';

-- Dapat found = 1 lahat.
