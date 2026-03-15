-- Hotfix: Upgrade post_shares table from old schema to new schema
-- Old schema had: id, user_id, post_id, created_at
-- New schema needs: tenant_id, original_post_id, original_type, shared_post_id, comment
-- Also ensure feed_posts has share_count, original_post_id, is_repost columns

-- Step 1: Add missing columns to post_shares (IF NOT EXISTS handles idempotency)
ALTER TABLE `post_shares` ADD COLUMN IF NOT EXISTS `tenant_id` int(11) NOT NULL DEFAULT 1;
ALTER TABLE `post_shares` ADD COLUMN IF NOT EXISTS `original_post_id` int(11) NOT NULL DEFAULT 0 COMMENT 'Original feed_posts.id';
ALTER TABLE `post_shares` ADD COLUMN IF NOT EXISTS `original_type` varchar(50) NOT NULL DEFAULT 'post' COMMENT 'post, listing, event';
ALTER TABLE `post_shares` ADD COLUMN IF NOT EXISTS `shared_post_id` int(11) DEFAULT NULL COMMENT 'The new feed_posts.id created by sharing';
ALTER TABLE `post_shares` ADD COLUMN IF NOT EXISTS `comment` text DEFAULT NULL COMMENT 'Optional comment when sharing';

-- Step 2: Migrate data from old post_id column to original_post_id (if post_id exists)
UPDATE `post_shares` SET `original_post_id` = `post_id` WHERE `original_post_id` = 0 AND `post_id` IS NOT NULL AND `post_id` > 0;

-- Step 3: Add indexes for the new columns
CREATE INDEX IF NOT EXISTS `idx_original` ON `post_shares` (`original_post_id`, `tenant_id`);
CREATE INDEX IF NOT EXISTS `idx_user_tenant` ON `post_shares` (`user_id`, `tenant_id`);
CREATE INDEX IF NOT EXISTS `idx_created_tenant` ON `post_shares` (`tenant_id`, `created_at`);

-- Step 4: Ensure feed_posts has the repost columns
ALTER TABLE `feed_posts` ADD COLUMN IF NOT EXISTS `share_count` int(11) NOT NULL DEFAULT 0;
ALTER TABLE `feed_posts` ADD COLUMN IF NOT EXISTS `original_post_id` int(11) DEFAULT NULL COMMENT 'For reposts: ID of original post';
ALTER TABLE `feed_posts` ADD COLUMN IF NOT EXISTS `is_repost` tinyint(1) NOT NULL DEFAULT 0;
