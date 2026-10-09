USE full_event_db;

ALTER TABLE `feedback`
  ADD COLUMN IF NOT EXISTS `session_id` INT DEFAULT NULL AFTER `event_id`;

ALTER TABLE `feedback`
  ADD INDEX IF NOT EXISTS `idx_session` (`session_id`);

-- Ang lumang unique_feedback ay ginagamit ng FK, kaya hindi direktang ma-drop.
-- Idagdag muna ang bagong composite index na pwedeng gamitin ng FK,
-- para ma-release ang unique_feedback.
ALTER TABLE `feedback`
  ADD INDEX IF NOT EXISTS `idx_event_attendee` (`event_id`, `attendee_id`);

ALTER TABLE `feedback` DROP INDEX IF EXISTS `unique_feedback`;

ALTER TABLE `feedback`
  ADD UNIQUE KEY IF NOT EXISTS `unique_session_feedback` (`session_id`, `attendee_id`);

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'feedback'
  AND CONSTRAINT_NAME = 'feedback_session_fk');
SET @sql := IF(@fk = 0,
  'ALTER TABLE feedback ADD CONSTRAINT feedback_session_fk FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT 'feedback.session_id' AS item, COUNT(*) AS found FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='feedback' AND COLUMN_NAME='session_id';
