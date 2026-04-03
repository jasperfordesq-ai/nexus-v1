-- Migration: Remaining group features — view-only role, scheduled posts,
--           collections, notification prefs, branding, auto-assign rules
-- Date: 2026-04-03
-- Idempotent: uses IF NOT EXISTS / IF EXISTS

-- ============================================================================
-- 1. SCHEDULED POSTS
-- ============================================================================

CREATE TABLE IF NOT EXISTS group_scheduled_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    post_type ENUM('discussion', 'announcement', 'post') NOT NULL DEFAULT 'discussion',
    title VARCHAR(500) NULL,
    content TEXT NOT NULL,
    is_recurring TINYINT(1) NOT NULL DEFAULT 0,
    recurrence_pattern ENUM('daily', 'weekly', 'monthly') NULL,
    scheduled_at DATETIME NOT NULL,
    published_at DATETIME NULL,
    status ENUM('scheduled', 'published', 'cancelled') NOT NULL DEFAULT 'scheduled',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_gsp_group (group_id, status),
    INDEX idx_gsp_scheduled (status, scheduled_at),
    INDEX idx_gsp_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. GROUP COLLECTIONS / BUNDLES
-- ============================================================================

CREATE TABLE IF NOT EXISTS group_collections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    image_url VARCHAR(500) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_gc_tenant (tenant_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS group_collection_items (
    collection_id INT NOT NULL,
    group_id INT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (collection_id, group_id),
    INDEX idx_gci_group (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. PER-GROUP NOTIFICATION PREFERENCES
-- ============================================================================

CREATE TABLE IF NOT EXISTS group_notification_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    group_id INT NOT NULL,
    frequency ENUM('instant', 'digest', 'muted') NOT NULL DEFAULT 'instant',
    email_enabled TINYINT(1) NOT NULL DEFAULT 1,
    push_enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_gnp (user_id, group_id),
    INDEX idx_gnp_group (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. GROUP BRANDING (custom colors per group)
-- ============================================================================

ALTER TABLE `groups` ADD COLUMN IF NOT EXISTS primary_color VARCHAR(7) NULL AFTER cover_image_url;
ALTER TABLE `groups` ADD COLUMN IF NOT EXISTS accent_color VARCHAR(7) NULL AFTER primary_color;

-- ============================================================================
-- 5. AUTO-ASSIGN RULES
-- ============================================================================

CREATE TABLE IF NOT EXISTS group_auto_assign_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    group_id INT NOT NULL,
    rule_type ENUM('location', 'interest', 'role', 'attribute') NOT NULL,
    rule_value VARCHAR(255) NOT NULL COMMENT 'e.g. location name, interest tag, user role',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gaar_tenant (tenant_id, is_active),
    INDEX idx_gaar_group (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
