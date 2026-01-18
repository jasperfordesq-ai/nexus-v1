-- Federation Reviews System - Extend Existing Reviews Table
-- Created: 17 January 2026
-- Purpose: Add federation support to existing reviews table

-- Add federation columns to existing reviews table
-- These columns enable cross-tenant review visibility and tracking

-- 1. Add federation_transaction_id column if not exists
SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reviews'
    AND COLUMN_NAME = 'federation_transaction_id'
);
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE reviews ADD COLUMN federation_transaction_id INT UNSIGNED NULL AFTER transaction_id',
    'SELECT "federation_transaction_id already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Add reviewer_tenant_id column if not exists
SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reviews'
    AND COLUMN_NAME = 'reviewer_tenant_id'
);
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE reviews ADD COLUMN reviewer_tenant_id INT UNSIGNED NULL AFTER reviewer_id',
    'SELECT "reviewer_tenant_id already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Add receiver_tenant_id column if not exists
SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reviews'
    AND COLUMN_NAME = 'receiver_tenant_id'
);
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE reviews ADD COLUMN receiver_tenant_id INT UNSIGNED NULL AFTER receiver_id',
    'SELECT "receiver_tenant_id already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Add review_type column if not exists
SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reviews'
    AND COLUMN_NAME = 'review_type'
);
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE reviews ADD COLUMN review_type ENUM(''local'', ''federated'') NOT NULL DEFAULT ''local'' AFTER comment',
    'SELECT "review_type already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Add status column if not exists
SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reviews'
    AND COLUMN_NAME = 'status'
);
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE reviews ADD COLUMN status ENUM(''pending'', ''approved'', ''rejected'', ''hidden'') NOT NULL DEFAULT ''approved'' AFTER review_type',
    'SELECT "status already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Add is_anonymous column if not exists
SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reviews'
    AND COLUMN_NAME = 'is_anonymous'
);
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE reviews ADD COLUMN is_anonymous TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
    'SELECT "is_anonymous already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 7. Add show_cross_tenant column if not exists
SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reviews'
    AND COLUMN_NAME = 'show_cross_tenant'
);
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE reviews ADD COLUMN show_cross_tenant TINYINT(1) NOT NULL DEFAULT 1 AFTER is_anonymous',
    'SELECT "show_cross_tenant already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 8. Add index for federation transaction if not exists
SET @index_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reviews'
    AND INDEX_NAME = 'idx_federation_transaction'
);
SET @sql = IF(@index_exists = 0,
    'ALTER TABLE reviews ADD INDEX idx_federation_transaction (federation_transaction_id)',
    'SELECT "idx_federation_transaction already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 9. Add index for tenant receiver if not exists
SET @index_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reviews'
    AND INDEX_NAME = 'idx_tenant_receiver'
);
SET @sql = IF(@index_exists = 0,
    'ALTER TABLE reviews ADD INDEX idx_tenant_receiver (receiver_tenant_id, receiver_id)',
    'SELECT "idx_tenant_receiver already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Populate tenant_id columns for existing reviews
UPDATE reviews r
JOIN users reviewer ON reviewer.id = r.reviewer_id
SET r.reviewer_tenant_id = reviewer.tenant_id
WHERE r.reviewer_tenant_id IS NULL;

UPDATE reviews r
JOIN users receiver ON receiver.id = r.receiver_id
SET r.receiver_tenant_id = receiver.tenant_id
WHERE r.receiver_tenant_id IS NULL;

-- Review responses table (optional - allows reviewed person to respond)
-- Note: review_id type must match reviews.id (INT, not INT UNSIGNED)
CREATE TABLE IF NOT EXISTS review_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    responder_id INT NOT NULL,
    response TEXT NOT NULL,
    status ENUM('visible', 'hidden') NOT NULL DEFAULT 'visible',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_review (review_id),
    FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Review helpfulness votes (optional - "was this review helpful?")
-- Note: review_id type must match reviews.id (INT, not INT UNSIGNED)
CREATE TABLE IF NOT EXISTS review_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    user_id INT NOT NULL,
    vote TINYINT NOT NULL DEFAULT 1, -- 1 = helpful, -1 = not helpful
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_vote (review_id, user_id),
    FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add review-related settings to federation_user_settings if not exists
SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'federation_user_settings'
    AND COLUMN_NAME = 'show_reviews_federated'
);
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE federation_user_settings ADD COLUMN show_reviews_federated TINYINT(1) NOT NULL DEFAULT 1',
    'SELECT "Column show_reviews_federated already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add flag to track if federation transaction has been reviewed
SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'federation_transactions'
    AND COLUMN_NAME = 'sender_reviewed'
);
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE federation_transactions ADD COLUMN sender_reviewed TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN receiver_reviewed TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT "Review columns already exist in federation_transactions"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
