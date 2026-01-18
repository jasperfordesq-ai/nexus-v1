-- ============================================================
-- FIX PRODUCTION ERRORS - January 14, 2026
-- Migration to fix database errors from error logs
-- ============================================================

-- ============================================================
-- 1. CREATE NEWS TABLE
-- Required for mobile news views
-- ============================================================

CREATE TABLE IF NOT EXISTS news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NULL,
    excerpt TEXT NULL,
    content LONGTEXT NULL,
    image_url VARCHAR(500) NULL,
    is_published TINYINT(1) DEFAULT 0,
    is_featured TINYINT(1) DEFAULT 0,
    published_at DATETIME NULL,
    views INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant (tenant_id),
    INDEX idx_published (tenant_id, is_published, published_at DESC),
    INDEX idx_featured (tenant_id, is_featured, published_at DESC),
    INDEX idx_user (user_id),
    INDEX idx_slug (tenant_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. FIX BADGES TABLE - ADD MISSING COLUMNS
-- The views query ORDER BY points_required but the table uses sort_order
-- Add points_required as an alias column if it doesn't exist
-- ============================================================

-- First check if points_required column exists and add it if not
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'badges'
    AND COLUMN_NAME = 'points_required'
);

-- Add points_required column if it doesn't exist
-- This will map to sort_order for backwards compatibility
SET @alter_sql = IF(@col_exists = 0,
    'ALTER TABLE badges ADD COLUMN points_required INT DEFAULT 0 AFTER sort_order',
    'SELECT 1'
);

PREPARE stmt FROM @alter_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update points_required to match sort_order for existing records
UPDATE badges SET points_required = sort_order WHERE points_required IS NULL OR points_required = 0;

-- ============================================================
-- 3. ADD DISTANCE_KM TO LISTINGS IF NOT EXISTS
-- This was causing the duplicate column error in getNearby queries
-- ============================================================

SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'listings'
    AND COLUMN_NAME = 'distance_km'
);

-- If distance_km exists on the listings table, rename it to avoid conflict
-- with the calculated column in the getNearby query
SET @alter_sql = IF(@col_exists > 0,
    'ALTER TABLE listings CHANGE COLUMN distance_km cached_distance_km DECIMAL(10,2) NULL',
    'SELECT 1'
);

PREPARE stmt FROM @alter_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- 4. VERIFICATION
-- ============================================================

SELECT 'Migration FIX_PRODUCTION_ERRORS_JAN_14_2026 completed successfully!' AS status;

-- Show table structures
DESCRIBE news;
SHOW COLUMNS FROM badges WHERE Field IN ('points_required', 'sort_order');
