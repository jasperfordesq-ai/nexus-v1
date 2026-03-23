-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
-- Enhance mentions table to support posts and messages (not just comments)

-- Add entity_type and entity_id columns for polymorphic mentions
ALTER TABLE `mentions`
    ADD COLUMN IF NOT EXISTS `entity_type` VARCHAR(30) DEFAULT 'comment' AFTER `tenant_id`,
    ADD COLUMN IF NOT EXISTS `entity_id` INT(11) DEFAULT NULL AFTER `entity_type`;

-- Backfill entity_type/entity_id from comment_id for existing rows
UPDATE `mentions`
SET `entity_type` = 'comment', `entity_id` = `comment_id`
WHERE `entity_id` IS NULL AND `comment_id` IS NOT NULL;

-- Add indexes for efficient querying
ALTER TABLE `mentions`
    ADD INDEX IF NOT EXISTS `idx_tenant_mentioned` (`tenant_id`, `mentioned_user_id`),
    ADD INDEX IF NOT EXISTS `idx_tenant_entity` (`tenant_id`, `entity_type`, `entity_id`);

-- Make comment_id nullable so mentions can exist for posts/messages too
ALTER TABLE `mentions`
    MODIFY COLUMN `comment_id` INT(11) NULL DEFAULT NULL;
