-- ============================================================================
-- DATABASE CLEANUP MIGRATION
-- ============================================================================
-- Purpose: Remove empty duplicate tables, typos, and migration artifacts
-- Generated: 2026-02-01
-- WARNING: Run on LOCAL first, then PRODUCTION after verification
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- 1. TYPO TABLES (empty, wrong names)
-- ============================================================================
DROP TABLE IF EXISTS `ai_settingss`;
DROP TABLE IF EXISTS `categorys`;
DROP TABLE IF EXISTS `newsletter_analyticss`;
DROP TABLE IF EXISTS `seo_metadatas`;
DROP TABLE IF EXISTS `vol_opportunitys`;
-- Count: 5

-- ============================================================================
-- 2. EMPTY SINGULAR TABLES (plural version exists)
-- ============================================================================
DROP TABLE IF EXISTS `ai_conversation`;
DROP TABLE IF EXISTS `ai_message`;
DROP TABLE IF EXISTS `ai_user_limit`;
DROP TABLE IF EXISTS `attribute`;
DROP TABLE IF EXISTS `category`;
DROP TABLE IF EXISTS `connection`;
DROP TABLE IF EXISTS `cron_log`;
DROP TABLE IF EXISTS `deliverable`;
DROP TABLE IF EXISTS `deliverable_comment`;
DROP TABLE IF EXISTS `deliverable_milestone`;
DROP TABLE IF EXISTS `error404_log`;
DROP TABLE IF EXISTS `event`;
DROP TABLE IF EXISTS `event_rsvp`;
DROP TABLE IF EXISTS `feed_post`;
DROP TABLE IF EXISTS `gamification`;
DROP TABLE IF EXISTS `goal`;
DROP TABLE IF EXISTS `group`;
DROP TABLE IF EXISTS `group_discussion`;
DROP TABLE IF EXISTS `group_discussion_subscriber`;
DROP TABLE IF EXISTS `group_feedback`;
DROP TABLE IF EXISTS `group_post`;
DROP TABLE IF EXISTS `group_type`;
DROP TABLE IF EXISTS `help_article`;
DROP TABLE IF EXISTS `listing`;
DROP TABLE IF EXISTS `menu`;
DROP TABLE IF EXISTS `menu_item`;
DROP TABLE IF EXISTS `message`;
DROP TABLE IF EXISTS `newsletter`;
DROP TABLE IF EXISTS `newsletter_analytics`;
DROP TABLE IF EXISTS `newsletter_bounce`;
DROP TABLE IF EXISTS `newsletter_segment`;
DROP TABLE IF EXISTS `newsletter_subscriber`;
DROP TABLE IF EXISTS `newsletter_template`;
DROP TABLE IF EXISTS `notification`;
DROP TABLE IF EXISTS `org_member`;
DROP TABLE IF EXISTS `org_transaction`;
DROP TABLE IF EXISTS `org_transfer_request`;
DROP TABLE IF EXISTS `org_wallet`;
DROP TABLE IF EXISTS `page`;
DROP TABLE IF EXISTS `pay_plan`;
DROP TABLE IF EXISTS `poll`;
DROP TABLE IF EXISTS `post`;
DROP TABLE IF EXISTS `report`;
DROP TABLE IF EXISTS `resource_item`;
DROP TABLE IF EXISTS `review`;
DROP TABLE IF EXISTS `role`;
DROP TABLE IF EXISTS `seo_redirect`;
DROP TABLE IF EXISTS `tenant`;
DROP TABLE IF EXISTS `transaction`;
DROP TABLE IF EXISTS `user`;
DROP TABLE IF EXISTS `user_badge`;
DROP TABLE IF EXISTS `vol_application`;
DROP TABLE IF EXISTS `vol_log`;
DROP TABLE IF EXISTS `vol_opportunity`;
DROP TABLE IF EXISTS `vol_organization`;
DROP TABLE IF EXISTS `vol_review`;
DROP TABLE IF EXISTS `vol_shift`;
-- Count: 57

-- ============================================================================
-- 3. EMPTY STUB TABLES (migration artifacts)
-- ============================================================================
DROP TABLE IF EXISTS `ancestors`;
DROP TABLE IF EXISTS `balance`;
DROP TABLE IF EXISTS `created_at`;
DROP TABLE IF EXISTS `descendants`;
DROP TABLE IF EXISTS `destination_url`;
DROP TABLE IF EXISTS `event_participants`;
DROP TABLE IF EXISTS `exchanges`;
DROP TABLE IF EXISTS `feedback`;
DROP TABLE IF EXISTS `final_rank`;
DROP TABLE IF EXISTS `frequency`;
DROP TABLE IF EXISTS `group_member_permissions`;
DROP TABLE IF EXISTS `group_user_permissions`;
DROP TABLE IF EXISTS `group_views`;
DROP TABLE IF EXISTS `information_schema`;
DROP TABLE IF EXISTS `latitude`;
DROP TABLE IF EXISTS `setting_value`;
DROP TABLE IF EXISTS `tenant_id`;
DROP TABLE IF EXISTS `time_transactions`;
DROP TABLE IF EXISTS `time_wallets`;
DROP TABLE IF EXISTS `unlockable_key`;
DROP TABLE IF EXISTS `user_achievements`;
DROP TABLE IF EXISTS `user_skills`;
DROP TABLE IF EXISTS `volunteer_applications`;
DROP TABLE IF EXISTS `volunteer_hours`;
DROP TABLE IF EXISTS `volunteering_signups`;
-- Count: 25

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- TOTAL TABLES DROPPED: 87
-- ============================================================================
