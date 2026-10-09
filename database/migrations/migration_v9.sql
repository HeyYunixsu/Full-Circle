-- v9: lock an account for 15 minutes after 5 wrong passwords in a row (safe to run twice)
ALTER TABLE users ADD COLUMN IF NOT EXISTS failed_logins INT(11) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS locked_until DATETIME DEFAULT NULL;
