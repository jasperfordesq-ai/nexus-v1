-- Safeguarding audit fixes — 2026-03-28
-- Adds soft-delete support to vetting_records and review_notes to broker_message_copies

-- 1. Add deleted_at column for soft-delete on vetting_records
ALTER TABLE vetting_records
    ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;

-- 2. Add review_notes column to broker_message_copies
ALTER TABLE broker_message_copies
    ADD COLUMN IF NOT EXISTS review_notes TEXT NULL DEFAULT NULL AFTER reviewed_at;

-- 3. Add no_home_visits column to users (for SafeguardingTriggerService)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS no_home_visits TINYINT(1) NOT NULL DEFAULT 0 AFTER works_with_vulnerable_adults;

-- 4. Index for efficient soft-delete queries
CREATE INDEX IF NOT EXISTS idx_vetting_records_deleted_at ON vetting_records(deleted_at);
