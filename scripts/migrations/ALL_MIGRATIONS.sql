-- ============================================================================
-- PROJECT NEXUS - COMPLETE DATABASE MIGRATIONS
-- ============================================================================
-- Run this file in phpMyAdmin to apply all pending migrations.
-- Each section is wrapped in error-tolerant statements where possible.
-- Date: 2026-01-05
-- ============================================================================

-- ============================================================================
-- 1. SEO REDIRECTS TABLE (for 301 redirect manager)
-- ============================================================================
CREATE TABLE IF NOT EXISTS seo_redirects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    source_url VARCHAR(500) NOT NULL,
    destination_url VARCHAR(500) NOT NULL,
    hits INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_redirect (tenant_id, source_url(191)),
    INDEX idx_source (source_url(191)),
    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. TENANT ORGANIZATION SCHEMA COLUMNS (for SEO organization settings)
-- ============================================================================
-- Using procedure to safely add columns only if they don't exist

DROP PROCEDURE IF EXISTS add_tenant_columns;
DELIMITER //
CREATE PROCEDURE add_tenant_columns()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'tenants' AND COLUMN_NAME = 'contact_email') THEN
        ALTER TABLE tenants ADD COLUMN contact_email VARCHAR(255) DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'tenants' AND COLUMN_NAME = 'contact_phone') THEN
        ALTER TABLE tenants ADD COLUMN contact_phone VARCHAR(50) DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'tenants' AND COLUMN_NAME = 'address') THEN
        ALTER TABLE tenants ADD COLUMN address VARCHAR(500) DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'tenants' AND COLUMN_NAME = 'social_facebook') THEN
        ALTER TABLE tenants ADD COLUMN social_facebook VARCHAR(255) DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'tenants' AND COLUMN_NAME = 'social_twitter') THEN
        ALTER TABLE tenants ADD COLUMN social_twitter VARCHAR(255) DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'tenants' AND COLUMN_NAME = 'social_instagram') THEN
        ALTER TABLE tenants ADD COLUMN social_instagram VARCHAR(255) DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'tenants' AND COLUMN_NAME = 'social_linkedin') THEN
        ALTER TABLE tenants ADD COLUMN social_linkedin VARCHAR(255) DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'tenants' AND COLUMN_NAME = 'social_youtube') THEN
        ALTER TABLE tenants ADD COLUMN social_youtube VARCHAR(255) DEFAULT NULL;
    END IF;
END //
DELIMITER ;
CALL add_tenant_columns();
DROP PROCEDURE IF EXISTS add_tenant_columns;

-- ============================================================================
-- 3. USER STATUS & ACTIVITY TRACKING
-- ============================================================================
-- Status column
ALTER TABLE users ADD COLUMN status ENUM('active', 'inactive', 'suspended', 'banned') DEFAULT 'active';

-- Login/activity tracking
ALTER TABLE users ADD COLUMN last_login_at TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN last_active_at TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN last_activity DATETIME NULL;

-- Set existing users to active
UPDATE users SET status = 'active' WHERE is_approved = 1 AND status IS NULL;
UPDATE users SET status = 'inactive' WHERE is_approved = 0 AND status IS NULL;

-- Backfill last_activity from created_at for users without it
UPDATE users SET last_activity = created_at WHERE last_activity IS NULL;

-- ============================================================================
-- 4. GROUPS LOCATION FIELDS (for Mapbox location picker)
-- ============================================================================
-- Note: Table is named `groups` (with backticks because it's a reserved word)
ALTER TABLE `groups` ADD COLUMN location VARCHAR(255) DEFAULT NULL;
ALTER TABLE `groups` ADD COLUMN latitude DECIMAL(10, 8) DEFAULT NULL;
ALTER TABLE `groups` ADD COLUMN longitude DECIMAL(11, 8) DEFAULT NULL;

-- ============================================================================
-- 5. AI INTEGRATION TABLES
-- ============================================================================
CREATE TABLE IF NOT EXISTS ai_conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(255),
    provider VARCHAR(50),
    model VARCHAR(100),
    context_type VARCHAR(50) DEFAULT 'general',
    context_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ai_conv_user (tenant_id, user_id),
    INDEX idx_ai_conv_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conversation_id INT NOT NULL,
    role ENUM('user', 'assistant', 'system') NOT NULL,
    content TEXT NOT NULL,
    tokens_used INT DEFAULT 0,
    model VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ai_msg_conv (conversation_id),
    FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_usage (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    provider VARCHAR(50) NOT NULL,
    feature VARCHAR(50) NOT NULL,
    tokens_input INT DEFAULT 0,
    tokens_output INT DEFAULT 0,
    cost_usd DECIMAL(10,6) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ai_usage_tenant (tenant_id, created_at),
    INDEX idx_ai_usage_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_content_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    cache_key VARCHAR(255) NOT NULL,
    content TEXT,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_ai_cache_key (tenant_id, cache_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    is_encrypted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_ai_settings_key (tenant_id, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_user_limits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    daily_limit INT DEFAULT 50,
    monthly_limit INT DEFAULT 1000,
    daily_used INT DEFAULT 0,
    monthly_used INT DEFAULT 0,
    last_reset_daily DATE NULL,
    last_reset_monthly DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_ai_limits_user (tenant_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. SEO METADATA TABLE (if not exists)
-- ============================================================================
CREATE TABLE IF NOT EXISTS seo_metadata (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NULL,
    meta_title VARCHAR(255) NULL,
    meta_description TEXT NULL,
    meta_keywords VARCHAR(500) NULL,
    canonical_url VARCHAR(500) NULL,
    og_image_url VARCHAR(500) NULL,
    noindex TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_entity (tenant_id, entity_type, entity_id),
    INDEX idx_entity_lookup (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. PERFORMANCE INDEXES
-- ============================================================================
-- Newsletter engagement indexes (ignore errors if already exist)
CREATE INDEX idx_newsletter_queue_user_status ON newsletter_queue(user_id, status);
CREATE INDEX idx_newsletter_queue_email_newsletter ON newsletter_queue(email, newsletter_id);
CREATE INDEX idx_newsletter_queue_sent_at ON newsletter_queue(sent_at);
CREATE INDEX idx_newsletter_opens_email_newsletter ON newsletter_opens(email, newsletter_id);
CREATE INDEX idx_newsletter_clicks_email_newsletter ON newsletter_clicks(email, newsletter_id);

-- Transaction indexes
CREATE INDEX idx_transactions_sender ON transactions(sender_id);
CREATE INDEX idx_transactions_receiver ON transactions(receiver_id);

-- Listings index
CREATE INDEX idx_listings_user_status ON listings(user_id, status);

-- User indexes
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_users_last_login ON users(last_login_at);
CREATE INDEX idx_users_last_active ON users(last_active_at);
CREATE INDEX idx_users_last_activity ON users(last_activity);
CREATE INDEX idx_users_last_login_tenant ON users(tenant_id, is_approved, last_login_at);
CREATE INDEX idx_users_created_tenant ON users(tenant_id, is_approved, created_at);

-- Groups location index
CREATE INDEX idx_groups_location ON `groups`(latitude, longitude);

-- ============================================================================
-- MIGRATION COMPLETE!
-- ============================================================================
-- To verify, run: SHOW TABLES;
-- To check columns: DESCRIBE users; DESCRIBE tenants; DESCRIBE seo_redirects;
-- ============================================================================
