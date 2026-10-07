-- ================================================================
-- FULL_EVENT DATABASE : clean install (lahat ng migration v3-v7 naka-apply na)
-- Full Circle Events Asia, Inc. : Event Check-in System
-- ================================================================
-- Laman: lahat ng 13 tables + 3 demo accounts, 3 passkeys, 1 badge template.
-- Walang events o attendees.
--
-- IMPORT: phpMyAdmin -> Import tab -> piliin ang file na ito -> Go
--         (gagawin nito ang database 'full_event_db' kung wala pa).
-- Kung may laman na ang full_event_db, hihinto ang import sa error
-- ("table already exists") at walang mabubura. Para sa existing
-- install, gamitin ang migration_v*.sql files sa halip nito.
--
-- Default logins (PALITAN ang password after first login):
--   superadmin@fullcircle.com / admin123
--   admin@fullcircle.com      / admin123
--   staff@fullcircle.com      / admin123
-- Passkeys: STAFF2026, ADMIN2026, SUPER2026
-- ================================================================

CREATE DATABASE IF NOT EXISTS `full_event_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `full_event_db`;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `attendee_code` varchar(50) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(150) NOT NULL,
  `mobile_number` varchar(20) DEFAULT NULL,
  `company` varchar(255) NOT NULL,
  `designation` varchar(255) DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `qr_image_path` varchar(255) DEFAULT NULL,
  `registration_type` enum('pre-registered','walk-in') DEFAULT 'pre-registered',
  `status` enum('not_yet','checked_in','no_show') DEFAULT 'not_yet',
  `check_in_time` datetime DEFAULT NULL,
  `badge_printed` tinyint(1) DEFAULT 0,
  `email_sent` tinyint(1) DEFAULT 0,
  `sms_sent` tinyint(1) NOT NULL DEFAULT 0,
  `reminder_sent` tinyint(1) DEFAULT 0,
  `name_edited_by` int(11) DEFAULT NULL,
  `name_edited_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendee_code` (`attendee_code`),
  UNIQUE KEY `uniq_event_email` (`event_id`,`email`),
  KEY `idx_event` (`event_id`),
  KEY `idx_code` (`attendee_code`),
  KEY `idx_status` (`status`),
  KEY `idx_company` (`company`),
  KEY `idx_mobile` (`mobile_number`),
  CONSTRAINT `attendees_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `badge_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) DEFAULT NULL,
  `template_name` varchar(100) DEFAULT 'Default',
  `layout_json` longtext DEFAULT NULL,
  `background_color` varchar(20) DEFAULT '#FFFFFF',
  `accent_color` varchar(20) DEFAULT '#FF1493',
  `text_color` varchar(20) DEFAULT '#000000',
  `badge_size` enum('standard','large') DEFAULT 'standard',
  `show_company` tinyint(1) DEFAULT 1,
  `show_qr` tinyint(1) DEFAULT 1,
  `logo_path` varchar(255) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `is_favorite` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `idx_favorite` (`is_favorite`),
  CONSTRAINT `badge_templates_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `max_attendees` int(10) unsigned DEFAULT NULL,
  `company_logo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_event` (`event_id`),
  CONSTRAINT `companies_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_archive` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attendee_id` int(11) NOT NULL,
  `recipient_email` varchar(150) NOT NULL,
  `recipient_mobile` varchar(20) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `email_type` enum('qr_code','reminder','feedback','walk-in') NOT NULL,
  `sent_via` enum('email','sms','both') DEFAULT 'email',
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('sent','failed') DEFAULT 'sent',
  PRIMARY KEY (`id`),
  KEY `idx_attendee` (`attendee_id`),
  KEY `idx_email` (`recipient_email`),
  CONSTRAINT `email_archive_ibfk_1` FOREIGN KEY (`attendee_id`) REFERENCES `attendees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attendee_id` int(11) NOT NULL,
  `email_type` enum('qr_code','reminder','feedback') NOT NULL,
  `recipient_email` varchar(150) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `attendee_id` (`attendee_id`),
  CONSTRAINT `email_queue_ibfk_1` FOREIGN KEY (`attendee_id`) REFERENCES `attendees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_name` varchar(255) NOT NULL,
  `event_date` date NOT NULL,
  `event_time` time NOT NULL,
  `location` varchar(255) NOT NULL,
  `event_type` enum('single','multiple') DEFAULT 'single',
  `description` text DEFAULT NULL,
  `event_image` varchar(255) DEFAULT NULL,
  `event_manager` varchar(255) DEFAULT NULL,
  `max_attendees` int(11) DEFAULT 0,
  `registration_qr` varchar(255) DEFAULT NULL,
  `status` enum('upcoming','ongoing','completed','archived') DEFAULT 'upcoming',
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `attendee_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_session_feedback` (`session_id`,`attendee_id`),
  KEY `attendee_id` (`attendee_id`),
  KEY `idx_session` (`session_id`),
  KEY `idx_event_attendee` (`event_id`,`attendee_id`),
  CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`attendee_id`) REFERENCES `attendees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `feedback_session_fk` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `passkeys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `passkey_code` varchar(50) NOT NULL,
  `role` enum('super_admin','admin','staff') NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `used_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `passkey_code` (`passkey_code`),
  KEY `created_by` (`created_by`),
  KEY `used_by` (`used_by`),
  CONSTRAINT `passkeys_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `passkeys_ibfk_2` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `session_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `attendee_id` int(11) NOT NULL,
  `scanned_at` datetime DEFAULT current_timestamp(),
  `scanned_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendance` (`session_id`,`attendee_id`),
  KEY `attendee_id` (`attendee_id`),
  KEY `scanned_by` (`scanned_by`),
  CONSTRAINT `session_attendance_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `session_attendance_ibfk_2` FOREIGN KEY (`attendee_id`) REFERENCES `attendees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `session_attendance_ibfk_3` FOREIGN KEY (`scanned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `session_name` varchar(255) NOT NULL,
  `session_type` varchar(100) DEFAULT NULL,
  `speaker_name` varchar(255) DEFAULT NULL,
  `speaker_role` varchar(255) DEFAULT NULL,
  `speaker_photo` varchar(255) DEFAULT NULL,
  `session_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_event` (`event_id`),
  CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sms_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attendee_id` int(11) NOT NULL,
  `sms_type` enum('qr_code','reminder','custom') NOT NULL DEFAULT 'qr_code',
  `recipient_number` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `provider_ref` varchar(100) DEFAULT NULL,
  `credits_used` int(11) DEFAULT 0,
  `sent_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `attendee_id` (`attendee_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `sms_queue_ibfk_1` FOREIGN KEY (`attendee_id`) REFERENCES `attendees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','staff') NOT NULL DEFAULT 'staff',
  `status` enum('active','offline') DEFAULT 'active',
  `profile_picture` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


-- Seed data

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Super',NULL,'Admin','superadmin@fullcircle.com','$2y$10$8cGTf3j0ccsXlcWqvkUcjumGdTYJxWzAX247XY624ePovIeb3TzvS','super_admin','active',NULL,'2026-10-05 16:56:53','2026-09-16 04:39:03','2026-10-05 08:56:53'),(2,'Admin',NULL,'User','admin@fullcircle.com','$2y$10$8cGTf3j0ccsXlcWqvkUcjumGdTYJxWzAX247XY624ePovIeb3TzvS','admin','offline',NULL,'2026-10-05 16:18:15','2026-09-16 04:39:03','2026-10-05 08:53:21'),(3,'Staff',NULL,'User','staff@fullcircle.com','$2y$10$8cGTf3j0ccsXlcWqvkUcjumGdTYJxWzAX247XY624ePovIeb3TzvS','staff','offline',NULL,'2026-10-05 16:53:25','2026-09-16 04:39:03','2026-10-05 08:53:39');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `passkeys` WRITE;
/*!40000 ALTER TABLE `passkeys` DISABLE KEYS */;
INSERT INTO `passkeys` VALUES (1,'STAFF2026','staff',0,NULL,NULL,'2026-09-16 04:39:03',NULL),(2,'ADMIN2026','admin',0,NULL,NULL,'2026-09-16 04:39:03',NULL),(3,'SUPER2026','super_admin',0,NULL,NULL,'2026-09-16 04:39:03',NULL);
/*!40000 ALTER TABLE `passkeys` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `badge_templates` WRITE;
/*!40000 ALTER TABLE `badge_templates` DISABLE KEYS */;
INSERT INTO `badge_templates` VALUES (1,NULL,'My Badge Template','{\"elements\":[{\"id\":1,\"type\":\"rect\",\"x\":0,\"y\":0,\"w\":360,\"h\":50,\"fill\":\"#7367F0\",\"opacity\":1,\"radius\":0},{\"id\":2,\"type\":\"text\",\"content\":\"FULL CIRCLE EVENTS ASIA\",\"x\":16,\"y\":16,\"fontSize\":11,\"fontFamily\":\"Plus Jakarta Sans\",\"fontWeight\":\"700\",\"color\":\"#ffffff\",\"align\":\"left\",\"_field\":\"\"},{\"id\":3,\"type\":\"text\",\"content\":\"Juan Dela Cruz\",\"x\":18,\"y\":64,\"fontSize\":24,\"fontFamily\":\"Plus Jakarta Sans\",\"fontWeight\":\"800\",\"color\":\"#ffffff\",\"align\":\"left\",\"_field\":\"name\"},{\"id\":4,\"type\":\"text\",\"content\":\"Amazon Web Services\",\"x\":20,\"y\":105,\"fontSize\":13,\"fontFamily\":\"Plus Jakarta Sans\",\"fontWeight\":\"600\",\"color\":\"#9c8fff\",\"align\":\"left\",\"_field\":\"company\"},{\"id\":5,\"type\":\"text\",\"content\":\"EVT0007\",\"x\":20,\"y\":185,\"fontSize\":11,\"fontFamily\":\"Plus Jakarta Sans\",\"fontWeight\":\"400\",\"color\":\"rgba(156,143,255,0.6)\",\"align\":\"left\",\"_field\":\"code\"},{\"id\":6,\"type\":\"qr\",\"x\":258,\"y\":62,\"size\":92,\"content\":\"EVT0007\"}],\"bg\":{\"color\":\"rgb(15, 23, 42)\"},\"size\":{\"w\":360,\"h\":225}}','#FFFFFF','#FF1493','#000000','standard',1,1,NULL,0,0,1,2,'2026-09-16 04:41:44');
/*!40000 ALTER TABLE `badge_templates` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

