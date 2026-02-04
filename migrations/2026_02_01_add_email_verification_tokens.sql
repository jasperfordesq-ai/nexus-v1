-- ============================================================================
-- ADD email_verification_tokens TABLE
-- ============================================================================
-- Migration: Create email_verification_tokens table for API email verification
-- Purpose: Store hashed verification tokens for stateless email verification flow
-- Date: 2026-02-01
--
-- Related API Endpoints:
--   - POST /api/auth/verify-email - Verify email with token
--   - POST /api/auth/resend-verification - Request new verification email
-- ============================================================================

-- Create email_verification_tokens table
CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(11) NOT NULL,
    `token` VARCHAR(255) NOT NULL COMMENT 'Hashed verification token',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL DEFAULT (DATE_ADD(NOW(), INTERVAL 24 HOUR)) COMMENT 'Token expiry time',

    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_expires_at` (`expires_at`),

    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clean up expired tokens (can be run periodically)
-- DELETE FROM email_verification_tokens WHERE expires_at < NOW();

SELECT 'EMAIL VERIFICATION TOKENS TABLE: Created successfully' AS result;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- Summary:
-- ✓ Created email_verification_tokens table
-- ✓ Added indexes for performance
-- ✓ Added foreign key constraint to users table
--
-- Next Steps:
-- 1. Run this migration on the database
-- 2. The EmailVerificationApiController will handle token generation and verification
-- ============================================================================
