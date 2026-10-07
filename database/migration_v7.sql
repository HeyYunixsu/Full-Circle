USE full_event_db;

-- Per-company attendee limit for an event. NULL = no limit.
-- Shown as "registered / limit" on the event page and the Companies page.
ALTER TABLE `companies`
  ADD COLUMN IF NOT EXISTS `max_attendees` INT UNSIGNED NULL DEFAULT NULL AFTER `company_name`;
