-- Migration: Group tags/topics system for discovery
-- Date: 2026-04-03
-- Idempotent: uses IF NOT EXISTS

CREATE TABLE IF NOT EXISTS group_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    color VARCHAR(7) NULL COMMENT 'Hex color code',
    usage_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_group_tags_slug (tenant_id, slug),
    INDEX idx_group_tags_tenant (tenant_id),
    INDEX idx_group_tags_usage (tenant_id, usage_count DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS group_tag_assignments (
    group_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (group_id, tag_id),
    INDEX idx_gta_tag (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add slug column to groups table for URL customization
ALTER TABLE `groups` ADD COLUMN IF NOT EXISTS slug VARCHAR(255) NULL AFTER name;
ALTER TABLE `groups` ADD UNIQUE INDEX IF NOT EXISTS uk_groups_slug (tenant_id, slug);
