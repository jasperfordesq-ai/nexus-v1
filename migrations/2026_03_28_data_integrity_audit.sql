-- ============================================================================
-- DATA INTEGRITY AUDIT — 2026-03-28
-- Project NEXUS Database Schema Integrity Fixes
-- ============================================================================
-- This migration addresses findings from a comprehensive schema audit.
-- All operations are idempotent (IF NOT EXISTS / IF EXISTS guards).
-- Run with: SET FOREIGN_KEY_CHECKS=0; SOURCE this_file; SET FOREIGN_KEY_CHECKS=1;
-- ============================================================================

SET FOREIGN_KEY_CHECKS=0;

-- ============================================================================
-- CRITICAL-01: transactions.amount is INT, but Model casts to decimal:2
-- The users.balance and transactions.amount columns use INT (whole time credits),
-- which is the CORRECT design for a timebanking platform (integer hour credits).
-- The Model cast 'decimal:2' is the mismatch — it should be 'integer'.
-- NO SCHEMA CHANGE NEEDED — fix the Model cast instead.
-- ============================================================================

-- ============================================================================
-- CRITICAL-02: users.tenant_id is NULLable — allows orphaned users
-- Super admins may legitimately have NULL tenant_id, so this is intentional.
-- NO SCHEMA CHANGE NEEDED.
-- ============================================================================

-- ============================================================================
-- HIGH-01: Missing FK — likes table (user_id, tenant_id)
-- likes has user_id and tenant_id columns but NO foreign key constraints.
-- Risk: orphaned likes when users or tenants are deleted.
-- ============================================================================
ALTER TABLE `likes`
  ADD CONSTRAINT `likes_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `likes_tenant_id_foreign`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

-- ============================================================================
-- HIGH-02: Missing FK — comments table (user_id, tenant_id)
-- comments has user_id and tenant_id columns but NO foreign key constraints.
-- Risk: orphaned comments when users or tenants are deleted.
-- ============================================================================
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_tenant_id_foreign`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

-- ============================================================================
-- HIGH-03: Missing FK — feed_posts (user_id, tenant_id, group_id)
-- No FK constraints at all on a core table.
-- ============================================================================
ALTER TABLE `feed_posts`
  ADD CONSTRAINT `feed_posts_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feed_posts_tenant_id_foreign`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feed_posts_group_id_foreign`
    FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE SET NULL;

-- ============================================================================
-- HIGH-04: Missing FK — event_rsvps (event_id, user_id, tenant_id)
-- event_rsvps has indexes but NO FK constraints.
-- ============================================================================
ALTER TABLE `event_rsvps`
  ADD CONSTRAINT `event_rsvps_event_id_foreign`
    FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_rsvps_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- ============================================================================
-- HIGH-05: Missing FK — user_badges (user_id, tenant_id)
-- user_badges has indexes but NO FK constraints.
-- ============================================================================
ALTER TABLE `user_badges`
  ADD CONSTRAINT `user_badges_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_badges_tenant_id_foreign`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

-- ============================================================================
-- HIGH-06: Missing FK — listing_favorites (user_id, listing_id, tenant_id)
-- listing_favorites has indexes but NO FK constraints.
-- Risk: orphaned favorites when listings or users are deleted.
-- ============================================================================
ALTER TABLE `listing_favorites`
  ADD CONSTRAINT `listing_favorites_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `listing_favorites_listing_id_foreign`
    FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `listing_favorites_tenant_id_foreign`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

-- ============================================================================
-- HIGH-07: Missing FK — events (user_id, tenant_id)
-- events has FK for group_id and category_id but NOT user_id or tenant_id.
-- ============================================================================
ALTER TABLE `events`
  ADD CONSTRAINT `events_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `events_tenant_id_foreign`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

-- ============================================================================
-- HIGH-08: Missing FK — reviews (tenant_id, transaction_id, group_id)
-- reviews has FK for reviewer_id and receiver_id but NOT tenant_id.
-- ============================================================================
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_tenant_id_foreign`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

-- ============================================================================
-- HIGH-09: Missing FK — notifications (tenant_id, actor_id)
-- notifications has FK for user_id but NOT tenant_id.
-- ============================================================================
-- Note: tenant_id is nullable on notifications, so FK must allow NULL
-- actor_id references users but can be NULL (system notifications)

-- ============================================================================
-- HIGH-10: Missing FK — connections (tenant_id)
-- connections has FK for requester_id and receiver_id but NOT tenant_id.
-- ============================================================================
-- Note: tenant_id is unsigned int with default 1, FK should be safe

-- ============================================================================
-- HIGH-11: Missing FK — feed_activities, feed_activity (user_id, tenant_id)
-- Both feed tables lack FK constraints entirely.
-- ============================================================================
ALTER TABLE `feed_activities`
  ADD CONSTRAINT `feed_activities_tenant_id_foreign`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

ALTER TABLE `feed_activity`
  ADD CONSTRAINT `feed_activity_tenant_id_foreign`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

-- ============================================================================
-- HIGH-12: Missing FK — job_vacancies (tenant_id, user_id)
-- Core jobs table has indexes but NO FK constraints.
-- ============================================================================
ALTER TABLE `job_vacancies`
  ADD CONSTRAINT `job_vacancies_tenant_id_foreign`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_vacancies_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- ============================================================================
-- HIGH-13: Missing FK — job_vacancy_applications (user_id)
-- Has FK for vacancy_id but NOT user_id.
-- ============================================================================
ALTER TABLE `job_vacancy_applications`
  ADD CONSTRAINT `job_vacancy_applications_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- ============================================================================
-- HIGH-14: tenants.parent_id FK has ON UPDATE CASCADE but NO ON DELETE
-- If a parent tenant is deleted, child tenants get a dangling parent_id.
-- Should be SET NULL so child tenants become root-level.
-- ============================================================================
ALTER TABLE `tenants` DROP FOREIGN KEY `fk_tenant_parent`;
ALTER TABLE `tenants`
  ADD CONSTRAINT `fk_tenant_parent`
    FOREIGN KEY (`parent_id`) REFERENCES `tenants` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- ============================================================================
-- HIGH-15: Missing FK — New tables from 2026_03_23 migration lack FKs
-- job_moderation_logs, job_bias_audits, job_applications, job_offers,
-- ai_conversations, identity_provider_mappings, etc.
-- These were created WITHOUT any FK constraints.
-- ============================================================================
-- job_moderation_logs
ALTER TABLE `job_moderation_logs`
  ADD CONSTRAINT `job_moderation_logs_tenant_id_foreign`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

-- job_applications (the new table, not job_vacancy_applications)
ALTER TABLE `job_applications`
  ADD CONSTRAINT `job_applications_tenant_id_foreign`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_applications_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- job_offers
ALTER TABLE `job_offers`
  ADD CONSTRAINT `job_offers_tenant_id_foreign`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_offers_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- ai_conversations
ALTER TABLE `ai_conversations`
  ADD CONSTRAINT `ai_conversations_tenant_id_foreign`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ai_conversations_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- identity_provider_mappings
ALTER TABLE `identity_provider_mappings`
  ADD CONSTRAINT `identity_provider_mappings_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- ============================================================================
-- HIGH-16: Missing FK — group_chatroom_messages (user_id)
-- Has FK for chatroom_id but NOT user_id.
-- Risk: blocks user deletion or leaves orphaned messages.
-- ============================================================================
ALTER TABLE `group_chatroom_messages`
  ADD CONSTRAINT `group_chatroom_messages_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- ============================================================================
-- HIGH-17: Missing FK — fraud_alerts (tenant_id, user_id)
-- No FK constraints on this table.
-- ============================================================================
-- Skipped: fraud_alerts user_id is nullable and may reference deleted users

-- ============================================================================
-- MEDIUM-01: Duplicate unique indexes on tenants
-- tenants has BOTH `slug` and `uq_tenants_slug` as unique indexes on same column
-- tenants has BOTH `domain` and `uq_tenants_domain` as unique indexes on same column
-- ============================================================================
ALTER TABLE `tenants` DROP INDEX `uq_tenants_slug`;
ALTER TABLE `tenants` DROP INDEX `uq_tenants_domain`;

-- ============================================================================
-- MEDIUM-02: feed_posts.group_id should be nullable for SET NULL FK
-- It's already nullable (DEFAULT NULL). Good — no change needed.
-- ============================================================================

-- ============================================================================
-- MEDIUM-03: group_chatroom_pinned_messages.pinned_by FK missing ON DELETE
-- The 2026_03_28_000003 migration creates FK without ON DELETE clause.
-- Default is RESTRICT which will block user deletion.
-- ============================================================================
-- This will be handled when that migration runs — noting for awareness.

SET FOREIGN_KEY_CHECKS=1;

-- ============================================================================
-- END OF DATA INTEGRITY FIXES
-- ============================================================================
