-- ============================================================================
-- FIX GOALS STATUS ENUM
-- ============================================================================
-- Migration: Update goals.status enum to include 'completed'
-- Date: 2026-02-01
--
-- Issue: Code uses 'completed' but enum only has 'achieved'
-- Solution: Add 'completed' to the enum
-- ============================================================================

-- Update status column to include 'completed'
ALTER TABLE `goals`
MODIFY COLUMN `status` ENUM('active', 'completed', 'achieved', 'abandoned')
DEFAULT 'active';

-- Convert any existing 'achieved' to 'completed' for consistency
UPDATE `goals` SET status = 'completed' WHERE status = 'achieved';

SELECT 'GOALS TABLE: Fixed status enum to include completed' AS result;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
