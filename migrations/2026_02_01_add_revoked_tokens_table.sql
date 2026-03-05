-- ============================================================================
-- ADD REVOKED_TOKENS TABLE
-- ============================================================================
-- Migration: Create revoked_tokens table for refresh token revocation
-- Purpose: Store revoked token identifiers (jti claims) to support logout-everywhere
-- Date: 2026-02-01
--
-- Related API Endpoints:
--   - POST /api/auth/revoke - Revoke a specific refresh token
--   - POST /api/auth/revoke-all - Revoke all refresh tokens for a user
-- ============================================================================

-- Create revoked_tokens table
CREATE TABLE IF NOT EXISTS `revoked_tokens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(11) NOT NULL,
    `jti` VARCHAR(64) NOT NULL COMMENT 'Token unique identifier (from JWT jti claim)',
    `revoked_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL COMMENT 'When this revocation record can be cleaned up (token expiry time)',

    UNIQUE INDEX `idx_jti` (`jti`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_expires_at` (`expires_at`),

    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cleanup query for expired revocation records (run periodically via cron)
-- DELETE FROM revoked_tokens WHERE expires_at < NOW();

SELECT 'REVOKED_TOKENS TABLE: Created successfully' AS result;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- Summary:
-- ✓ Created revoked_tokens table for token revocation tracking
-- ✓ Added unique index on jti for fast lookup
-- ✓ Added foreign key constraint to users table
--
-- Notes:
-- - Revocation records should be cleaned up after their expires_at time
-- - The jti claim is a unique identifier embedded in each refresh token
-- - When validating a refresh token, check if its jti is in this table
-- ============================================================================
