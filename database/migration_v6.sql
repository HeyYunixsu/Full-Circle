USE full_event_db;

-- One email per event. PHP already checks this on upload, walk-in and edit;
-- this makes the database enforce it too.
ALTER TABLE `attendees`
  ADD UNIQUE KEY IF NOT EXISTS `uniq_event_email` (`event_id`, `email`);
