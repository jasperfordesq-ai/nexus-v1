-- ============================================================================
-- FIX GAMIFICATION SCHEMA — Clean Up Junk Columns & Fix Types
-- ============================================================================
-- Several gamification tables have junk columns (migration artifacts) and one
-- incorrect column type. This migration removes them and adds missing indexes.
--
-- All statements use IF EXISTS / IF NOT EXISTS guards for idempotency.
-- Safe to run multiple times.
--
-- Created: 2026-03-27
-- Database: MariaDB 10.11
-- ============================================================================


-- ============================================================================
-- 1. FIX custom_badges.category COLUMN TYPE
-- ============================================================================
-- The `category` column was incorrectly typed as TIMESTAMP.
-- It should be VARCHAR(100) to hold badge category names.

ALTER TABLE custom_badges MODIFY COLUMN category VARCHAR(100) NULL DEFAULT NULL;


-- ============================================================================
-- 2. REMOVE JUNK COLUMNS FROM user_badges
-- ============================================================================
-- Columns `25`, `badge`, `last_login`, `level` are migration artifacts that
-- do not belong in this table.

ALTER TABLE user_badges DROP COLUMN IF EXISTS `25`;
ALTER TABLE user_badges DROP COLUMN IF EXISTS `badge`;
ALTER TABLE user_badges DROP COLUMN IF EXISTS `last_login`;
ALTER TABLE user_badges DROP COLUMN IF EXISTS `level`;


-- ============================================================================
-- 3. REMOVE JUNK last_login FROM achievement_campaigns
-- ============================================================================
-- `last_login` is a migration artifact — campaigns don't track logins.

ALTER TABLE achievement_campaigns DROP COLUMN IF EXISTS `last_login`;


-- ============================================================================
-- 4. REMOVE JUNK award COLUMNS
-- ============================================================================
-- `award` does not belong on streaks or XP log entries.

ALTER TABLE user_streaks DROP COLUMN IF EXISTS `award`;
ALTER TABLE user_xp_log DROP COLUMN IF EXISTS `award`;


-- ============================================================================
-- 5. REMOVE JUNK claimed_at COLUMNS
-- ============================================================================
-- Shop items, challenges, and custom badge definitions don't get "claimed" —
-- only user_challenge_progress and user_xp_shop_purchases track claiming.

ALTER TABLE xp_shop_items DROP COLUMN IF EXISTS `claimed_at`;
ALTER TABLE challenges DROP COLUMN IF EXISTS `claimed_at`;
ALTER TABLE custom_badges DROP COLUMN IF EXISTS `claimed_at`;


-- ============================================================================
-- 6. ADD MISSING INDEXES
-- ============================================================================
-- Composite index for efficient tenant+user lookups on user_badges.

ALTER TABLE user_badges ADD INDEX IF NOT EXISTS idx_user_badges_tenant_user (tenant_id, user_id);

-- Composite index for filtering XP log entries by action within a tenant.

ALTER TABLE user_xp_log ADD INDEX IF NOT EXISTS idx_user_xp_log_action (tenant_id, action);


-- ============================================================================
-- VERIFICATION
-- ============================================================================

SELECT 'Gamification schema cleanup applied successfully!' AS status;
