-- Add featured flag to groups table
-- This allows admins to manually mark specific groups as "featured" for display at the top of the groups page

ALTER TABLE `groups` ADD COLUMN `is_featured` TINYINT(1) DEFAULT 0 AFTER `visibility`;

-- Add index for faster featured queries
CREATE INDEX idx_groups_featured ON `groups`(`is_featured`, `type_id`, `tenant_id`);
