-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
-- Fix 5: Rename set_by → restricted_by in user_messaging_restrictions
-- 2026-02-27
--
-- The user_messaging_restrictions table was originally created with a column
-- named `set_by` but the MessagingController references `restricted_by`.
-- This migration safely renames the column only if `set_by` exists and
-- `restricted_by` does not yet exist.

SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'user_messaging_restrictions'
      AND COLUMN_NAME  = 'set_by'
);

SET @target_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'user_messaging_restrictions'
      AND COLUMN_NAME  = 'restricted_by'
);

-- Only rename if set_by exists and restricted_by does not yet exist
SET @sql = IF(
    @col_exists > 0 AND @target_exists = 0,
    'ALTER TABLE user_messaging_restrictions CHANGE set_by restricted_by INT DEFAULT NULL',
    'SELECT 1 -- no-op: column already renamed or does not exist'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
