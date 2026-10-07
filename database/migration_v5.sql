USE full_event_db;

ALTER TABLE `badge_templates`
  ADD COLUMN IF NOT EXISTS `layout_json` LONGTEXT DEFAULT NULL AFTER `template_name`,
  ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_default`,
  ADD COLUMN IF NOT EXISTS `is_favorite` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`,
  ADD COLUMN IF NOT EXISTS `created_by` INT DEFAULT NULL AFTER `is_favorite`;

ALTER TABLE `badge_templates` MODIFY `event_id` INT DEFAULT NULL;

ALTER TABLE `badge_templates`
  ADD INDEX IF NOT EXISTS `idx_favorite` (`is_favorite`);

SELECT 'badge_templates.is_favorite' AS item, COUNT(*) AS found FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='badge_templates' AND COLUMN_NAME='is_favorite';
