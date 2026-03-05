-- Copyright (c) 2024-2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
-- Author: Jasper Ford
-- See NOTICE file for attribution and acknowledgements.

-- =========================================================================
-- Migration: Recurring volunteering schema compatibility hardening
-- Date: 2026-03-08
-- Purpose:
--   1) Align recurring_shift_patterns columns used by services
--   2) Ensure vol_shifts.recurring_pattern_id exists for recurring linkage
-- =========================================================================

ALTER TABLE recurring_shift_patterns
    ADD COLUMN IF NOT EXISTS title VARCHAR(255) DEFAULT NULL AFTER created_by,
    ADD COLUMN IF NOT EXISTS spots_per_shift INT UNSIGNED NOT NULL DEFAULT 1 AFTER end_time,
    ADD COLUMN IF NOT EXISTS capacity INT UNSIGNED NOT NULL DEFAULT 1 AFTER spots_per_shift,
    ADD COLUMN IF NOT EXISTS start_date DATE DEFAULT NULL AFTER capacity,
    ADD COLUMN IF NOT EXISTS end_date DATE DEFAULT NULL AFTER start_date,
    ADD COLUMN IF NOT EXISTS max_occurrences INT UNSIGNED DEFAULT NULL AFTER end_date,
    ADD COLUMN IF NOT EXISTS occurrences_generated INT UNSIGNED NOT NULL DEFAULT 0 AFTER max_occurrences;

-- Keep both capacity aliases in sync for compatibility.
UPDATE recurring_shift_patterns
SET capacity = GREATEST(1, COALESCE(capacity, spots_per_shift, 1)),
    spots_per_shift = GREATEST(1, COALESCE(spots_per_shift, capacity, 1));

-- Fill start_date when absent.
UPDATE recurring_shift_patterns
SET start_date = COALESCE(start_date, DATE(created_at), CURDATE())
WHERE start_date IS NULL;

-- If legacy generate_until exists, copy into end_date where end_date is missing.
SET @has_generate_until = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'recurring_shift_patterns'
      AND COLUMN_NAME = 'generate_until'
);
SET @copy_end_sql = IF(
    @has_generate_until > 0,
    'UPDATE recurring_shift_patterns SET end_date = COALESCE(end_date, generate_until) WHERE end_date IS NULL',
    'SELECT 1'
);
PREPARE stmt_copy_end FROM @copy_end_sql;
EXECUTE stmt_copy_end;
DEALLOCATE PREPARE stmt_copy_end;

ALTER TABLE vol_shifts
    ADD COLUMN IF NOT EXISTS recurring_pattern_id INT UNSIGNED DEFAULT NULL AFTER opportunity_id,
    ADD INDEX IF NOT EXISTS idx_vol_shift_recurring_pattern (tenant_id, recurring_pattern_id);

SELECT 'Recurring volunteering compatibility migration applied' AS result;
