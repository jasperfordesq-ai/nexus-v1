-- Migration: Fix event_rsvps table — two bugs
--
-- Bug 1: Missing updated_at column
-- The RSVP upsert SQL in EventService::rsvp references updated_at in ON DUPLICATE KEY UPDATE,
-- but the column was never created. This causes a 422 error on all RSVP attempts.
--
-- Bug 2: Status enum mismatch
-- The code validates against ['going','interested','not_going','declined'] but the DB enum
-- only had ['going','maybe','declined']. Inserting 'interested' or 'not_going' silently fails
-- (MariaDB truncates invalid enum values in non-strict mode) — the API returns 200 but data
-- is never persisted.

-- Fix 1: Add updated_at column if missing
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_rsvps' AND COLUMN_NAME = 'updated_at');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE event_rsvps ADD COLUMN updated_at DATETIME NULL DEFAULT NULL AFTER created_at',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Fix 2: Expand enum to match all statuses used by EventService
ALTER TABLE event_rsvps
    MODIFY COLUMN status ENUM('going','interested','maybe','not_going','declined','invited','attended','cancelled','waitlisted')
    NOT NULL DEFAULT 'going';
