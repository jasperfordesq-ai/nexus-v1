-- Migration: Add status and rewards columns to leaderboard_seasons
-- The service expects status (varchar) and rewards (TEXT) columns.
-- Production table only had is_active and is_finalized booleans.

ALTER TABLE leaderboard_seasons
    ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER is_finalized,
    ADD COLUMN IF NOT EXISTS rewards TEXT NULL AFTER status;

-- Backfill status from existing boolean flags
UPDATE leaderboard_seasons
SET status = CASE
    WHEN is_finalized = 1 THEN 'completed'
    WHEN is_active = 0 THEN 'inactive'
    ELSE 'active'
END;

-- Index for the status query used in getCurrentSeason()
ALTER TABLE leaderboard_seasons
    ADD INDEX IF NOT EXISTS idx_tenant_status (tenant_id, status, start_date, end_date);
