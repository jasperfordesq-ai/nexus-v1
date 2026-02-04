-- ============================================================================
-- FIX GOALS AND POLLS SCHEMA - Add missing columns
-- ============================================================================
-- Migration: Add missing columns for GoalService and PollService
-- Date: 2026-02-01
--
-- Issues Found:
-- 1. goals table missing: target_value, current_value
-- 2. poll_options table missing: votes
-- ============================================================================

-- ============================================================================
-- 1. FIX GOALS TABLE
-- ============================================================================
-- Add target_value column (for progress tracking)
ALTER TABLE `goals`
ADD COLUMN IF NOT EXISTS `target_value` DECIMAL(10,2) NOT NULL DEFAULT 0
COMMENT 'Target value for goal completion (e.g., 100 hours)';

-- Add current_value column (tracks progress toward target)
ALTER TABLE `goals`
ADD COLUMN IF NOT EXISTS `current_value` DECIMAL(10,2) NOT NULL DEFAULT 0
COMMENT 'Current progress value';

SELECT 'GOALS TABLE: Added target_value and current_value columns' AS result;

-- ============================================================================
-- 2. FIX POLL_OPTIONS TABLE
-- ============================================================================
-- Add votes column (denormalized vote count for performance)
ALTER TABLE `poll_options`
ADD COLUMN IF NOT EXISTS `votes` INT NOT NULL DEFAULT 0
COMMENT 'Cached vote count for this option';

-- Sync existing vote counts from poll_votes table
UPDATE `poll_options` po
SET po.votes = (
    SELECT COUNT(*)
    FROM poll_votes pv
    WHERE pv.option_id = po.id
);

SELECT 'POLL_OPTIONS TABLE: Added votes column and synced counts' AS result;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- Summary:
-- ✓ Added goals.target_value (DECIMAL 10,2)
-- ✓ Added goals.current_value (DECIMAL 10,2)
-- ✓ Added poll_options.votes (INT)
-- ✓ Synced existing vote counts
-- ============================================================================
