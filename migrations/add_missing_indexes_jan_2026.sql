-- Migration: Add Missing Indexes for Performance
-- Date: January 17, 2026
-- Purpose: Add indexes on frequently queried columns that were identified as missing

-- Users table indexes
CREATE INDEX IF NOT EXISTS `idx_users_is_super_admin` ON `users` (`is_super_admin`);
CREATE INDEX IF NOT EXISTS `idx_users_is_god` ON `users` (`is_god`);
CREATE INDEX IF NOT EXISTS `idx_users_role` ON `users` (`role`);

-- Tenants table indexes
CREATE INDEX IF NOT EXISTS `idx_tenants_is_active` ON `tenants` (`is_active`);

-- Transactions table indexes
CREATE INDEX IF NOT EXISTS `idx_transactions_status` ON `transactions` (`status`);

-- Events table indexes
CREATE INDEX IF NOT EXISTS `idx_events_user_id` ON `events` (`user_id`);

-- Notifications table indexes (critical for performance)
CREATE INDEX IF NOT EXISTS `idx_notifications_tenant_id` ON `notifications` (`tenant_id`);
CREATE INDEX IF NOT EXISTS `idx_notifications_is_read` ON `notifications` (`is_read`);
CREATE INDEX IF NOT EXISTS `idx_notifications_created_at` ON `notifications` (`created_at`);

-- Activity log indexes
CREATE INDEX IF NOT EXISTS `idx_activity_log_user_id` ON `activity_log` (`user_id`);
CREATE INDEX IF NOT EXISTS `idx_activity_log_created_at` ON `activity_log` (`created_at`);

-- Group members index
CREATE INDEX IF NOT EXISTS `idx_group_members_role` ON `group_members` (`role`);

-- Composite indexes for common query patterns
CREATE INDEX IF NOT EXISTS `idx_users_tenant_role` ON `users` (`tenant_id`, `role`);
CREATE INDEX IF NOT EXISTS `idx_notifications_user_read` ON `notifications` (`user_id`, `is_read`);
CREATE INDEX IF NOT EXISTS `idx_activity_log_user_created` ON `activity_log` (`user_id`, `created_at`);
