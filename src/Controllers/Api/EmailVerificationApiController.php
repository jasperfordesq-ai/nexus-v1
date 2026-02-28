<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\ApiErrorCodes;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\RateLimiter;
use Nexus\Core\Mailer;
use Nexus\Core\EmailTemplate;

/**
 * EmailVerificationApiController - Stateless email verification API endpoints
 *
 * Provides API endpoints for email verification that work with Bearer token
 * authentication and follow v2 API response conventions.
 *
 * Endpoints:
 * - POST /api/auth/verify-email - Verify email with token (no auth required)
 * - POST /api/auth/resend-verification - Request new verification email (auth required)
 *
 * Security measures:
 * - Rate limiting on both endpoints
 * - Token hashing (never store raw tokens)
 * - Token expiry (24 hours)
 * - Resend endpoint requires authentication
 * - 1 resend per minute rate limit
 *
 * @package Nexus\Controllers\Api
 */
class EmailVerificationApiController extends BaseApiController
{
    /** Token expiry in seconds (24 hours) */
    private const TOKEN_EXPIRY_SECONDS = 86400;

    /** Minimum seconds between resend requests */
    private const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * POST /api/auth/verify-email
     *
     * Verify a user's email address using the token from the verification email.
     * This endpoint does not require authentication.
     *
     * Request: { "token": "abc123..." }
     * Response: { "data": { "verified": true } }
     */
    public function verifyEmail(): void
    {
        // Rate limit by IP - 20 attempts per 15 minutes
        $this->rateLimit('verify_email', 20, 900);

        $token = $this->input('token');
        $tenantId = TenantContext::getId();

        if (empty($token)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'Verification token is required',
                'token',
                400
            );
        }

        // Find and validate the verification token (tenant-scoped)
        $verificationRecord = $this->findValidVerificationToken($token, $tenantId);

        if (!$verificationRecord) {
            $this->respondWithError(
                ApiErrorCodes::AUTH_TOKEN_INVALID,
                'Invalid or expired verification token. Please request a new verification email.',
                'token',
                400
            );
        }

        $userId = $verificationRecord['user_id'];

        // Check if already verified (tenant-scoped)
        $user = Database::query(
            "SELECT id, email_verified_at, is_verified FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetch();

        if (!$user) {
            $this->respondWithError(
                ApiErrorCodes::RESOURCE_NOT_FOUND,
                'User not found',
                null,
                404
            );
        }

        if (!empty($user['email_verified_at'])) {
            // Already verified - delete any remaining tokens and return success
            $this->cleanupVerificationTokens($userId, $tenantId);

            $this->respondWithData([
                'verified' => true,
                'message' => 'Email address is already verified'
            ]);
        }

        // Mark user as verified (tenant-scoped)
        Database::query(
            "UPDATE users SET email_verified_at = NOW(), is_verified = 1 WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );

        // Delete all verification tokens for this user in this tenant
        $this->cleanupVerificationTokens($userId, $tenantId);

        // Log the verification
        try {
            \Nexus\Models\ActivityLog::log(
                $userId,
                'email_verified',
                'Email address verified via API'
            );
        } catch (\Throwable $e) {
            error_log("Failed to log email verification: " . $e->getMessage());
        }

        // Award gamification points if available
        try {
            if (class_exists('\Nexus\Models\Gamification')) {
                \Nexus\Models\Gamification::awardPoints($userId, 10, 'Verified email address');
            }
        } catch (\Throwable $e) {
            // Gamification is optional
        }

        $this->respondWithData([
            'verified' => true,
            'message' => 'Email address verified successfully'
        ]);
    }

    /**
     * POST /api/auth/resend-verification
     *
     * Request a new verification email. Requires Bearer token authentication.
     * Rate limited to 1 request per minute.
     *
     * Request: {} (empty body, uses authenticated user)
     * Response: { "data": { "message": "Verification email sent" } }
     */
    public function resendVerification(): void
    {
        // Require authentication
        $userId = $this->requireAuth();

        // Rate limit by user - 1 request per minute
        $userKey = "resend_verification:user:{$userId}";
        if (!RateLimiter::attempt($userKey, 1, self::RESEND_COOLDOWN_SECONDS)) {
            $this->respondWithError(
                ApiErrorCodes::RATE_LIMIT_EXCEEDED,
                'Please wait at least 1 minute before requesting another verification email',
                null,
                429
            );
        }

        // Get user details (tenant-scoped)
        $tenantId = TenantContext::getId();
        $user = Database::query(
            "SELECT id, email, first_name, email_verified_at, tenant_id FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetch();

        if (!$user) {
            $this->respondWithError(
                ApiErrorCodes::RESOURCE_NOT_FOUND,
                'User not found',
                null,
                404
            );
        }

        // Check if already verified
        if (!empty($user['email_verified_at'])) {
            $this->respondWithData([
                'message' => 'Email address is already verified',
                'already_verified' => true
            ]);
        }

        // Generate and send new verification email
        $this->sendVerificationEmail($user);

        $this->respondWithData([
            'message' => 'Verification email sent'
        ]);
    }

    /**
     * POST /api/auth/resend-verification-by-email
     *
     * Public endpoint (no auth required) to resend a verification email.
     * Used on the login page when a user is blocked by the email verification gate.
     * Rate limited aggressively to prevent abuse.
     *
     * Request: { "email": "user@example.com" }
     * Response: { "data": { "message": "If an account exists..." } }
     *
     * SECURITY: Always returns the same response regardless of whether the email
     * exists or the user is already verified — prevents user enumeration.
     */
    public function resendVerificationByEmail(): void
    {
        // Rate limit by IP — 3 per 5 minutes (aggressive since unauthenticated)
        $ip = \Nexus\Core\ClientIp::get();
        if (\Nexus\Services\RateLimitService::check("resend_verify:$ip", 3, 300)) {
            header('Retry-After: 300');
            $this->respondWithError(
                ApiErrorCodes::RATE_LIMIT_EXCEEDED,
                'Too many requests. Please try again later.',
                null,
                429
            );
        }
        \Nexus\Services\RateLimitService::increment("resend_verify:$ip", 300);

        $email = strtolower(trim($this->input('email', '')));
        $tenantId = TenantContext::getId();

        // Always return the same success message (prevents user enumeration)
        $genericResponse = ['message' => 'If an account with that email exists and is unverified, a new verification email has been sent.'];

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respondWithData($genericResponse);
            return;
        }

        // Look up user (tenant-scoped)
        $user = Database::query(
            "SELECT id, email, first_name, email_verified_at, tenant_id FROM users WHERE email = ? AND tenant_id = ?",
            [$email, $tenantId]
        )->fetch();

        // Only send if user exists AND is not yet verified
        if ($user && empty($user['email_verified_at'])) {
            $this->sendVerificationEmail($user);
        }

        $this->respondWithData($genericResponse);
    }

    /**
     * Generate a verification token for a user (called during registration)
     *
     * This is a public static method that can be called from other controllers
     * (e.g., AuthController during registration).
     *
     * @param int $userId
     * @param string $email
     * @param string $firstName
     * @param int|null $tenantId
     * @return bool Whether the email was sent successfully
     */
    public static function sendVerificationEmailForUser(int $userId, string $email, string $firstName, ?int $tenantId = null): bool
    {
        $instance = new self();
        return $instance->sendVerificationEmail([
            'id' => $userId,
            'email' => $email,
            'first_name' => $firstName,
            'tenant_id' => $tenantId ?? TenantContext::getId()
        ]);
    }

    /**
     * Send verification email to user
     *
     * @param array $user User data with id, email, first_name, tenant_id
     * @return bool Whether the email was sent successfully
     */
    private function sendVerificationEmail(array $user): bool
    {
        $tenantId = $user['tenant_id'] ?? TenantContext::getId();

        // Generate a secure random token
        $token = bin2hex(random_bytes(32));

        // Hash the token before storing (bcrypt — constant-time verification)
        $hashedToken = password_hash($token, PASSWORD_DEFAULT);

        // Calculate expiry time
        $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_EXPIRY_SECONDS);

        // Delete any existing tokens for this user in this tenant
        $this->cleanupVerificationTokens($user['id'], $tenantId);

        // Ensure the table exists (create if not)
        $this->ensureTokenTableExists();

        // Store the hashed token with tenant_id
        Database::query(
            "INSERT INTO email_verification_tokens (user_id, tenant_id, token, expires_at) VALUES (?, ?, ?, ?)",
            [$user['id'], $tenantId, $hashedToken, $expiresAt]
        );

        // Build verification URL — include tenant base path for correct routing
        $appUrl = TenantContext::getFrontendUrl();
        $basePath = TenantContext::getSlugPrefix();
        $verifyUrl = $appUrl . $basePath . "/verify-email?token=" . $token;

        // Send verification email
        try {
            $mailer = new Mailer();

            // Get tenant name
            $tenantName = 'Project NEXUS';
            if ($tenantId) {
                try {
                    $tenant = Database::query(
                        "SELECT name FROM tenants WHERE id = ?",
                        [$tenantId]
                    )->fetch();
                    if ($tenant) {
                        $tenantName = $tenant['name'];
                    }
                } catch (\Throwable $e) {
                    // Use default tenant name
                }
            }

            $firstName = $user['first_name'] ?? 'there';

            $html = EmailTemplate::render(
                "Verify Your Email Address",
                "Hi {$firstName}, welcome to {$tenantName}!",
                "Please verify your email address by clicking the button below. This link will expire in 24 hours.<br><br>If you did not create an account, please ignore this email.",
                "Verify Email Address",
                $verifyUrl,
                $tenantName
            );

            return $mailer->send($user['email'], "Verify Your Email - " . $tenantName, $html);
        } catch (\Throwable $e) {
            error_log("Verification email failed for user {$user['id']}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Find a valid (non-expired) verification token, scoped to tenant
     *
     * Uses SHA-256 prefix matching to avoid O(n) password_verify scan across
     * all tokens. Stores a token_prefix column for fast lookup, then verifies
     * with password_verify for security.
     *
     * Both RegistrationApiController and this controller now use SHA-256 hashing.
     * The bcrypt fallback exists only for tokens created before this change
     * and can be removed after the TOKEN_EXPIRY_SECONDS window passes.
     *
     * @param string $token The unhashed token from the user
     * @param int $tenantId The tenant to scope the lookup to
     * @return array|null The verification record if valid, null otherwise
     */
    private function findValidVerificationToken(string $token, int $tenantId): ?array
    {
        // Ensure the table exists
        if (!$this->tokenTableExists()) {
            return null;
        }

        // Find non-expired tokens for this tenant only (prevents cross-tenant hijacking)
        // All tokens now use bcrypt (password_hash) — SHA-256 fallback removed since
        // RegistrationApiController was fixed to use password_hash consistently.
        $records = Database::query(
            "SELECT * FROM email_verification_tokens WHERE tenant_id = ? AND expires_at > NOW()",
            [$tenantId]
        )->fetchAll();

        foreach ($records as $record) {
            if (password_verify($token, $record['token'])) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Delete all verification tokens for a user in a specific tenant
     *
     * @param int $userId
     * @param int|null $tenantId If null, uses current TenantContext
     */
    private function cleanupVerificationTokens(int $userId, ?int $tenantId = null): void
    {
        if (!$this->tokenTableExists()) {
            return;
        }

        $tenantId = $tenantId ?? TenantContext::getId();

        Database::query(
            "DELETE FROM email_verification_tokens WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );
    }

    /**
     * Check if the token table exists
     *
     * @return bool
     */
    private function tokenTableExists(): bool
    {
        static $exists = null;

        if ($exists === null) {
            try {
                $result = Database::query(
                    "SHOW TABLES LIKE 'email_verification_tokens'"
                )->fetch();
                $exists = !empty($result);
            } catch (\Throwable $e) {
                $exists = false;
            }
        }

        return $exists;
    }

    /**
     * Create the token table if it doesn't exist
     */
    private function ensureTokenTableExists(): void
    {
        if ($this->tokenTableExists()) {
            return;
        }

        try {
            Database::query("
                CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT UNSIGNED NOT NULL,
                    `tenant_id` INT(11) NOT NULL,
                    `token` VARCHAR(255) NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `expires_at` TIMESTAMP NOT NULL,
                    INDEX `idx_user_id` (`user_id`),
                    INDEX `idx_tenant_id` (`tenant_id`),
                    INDEX `idx_tenant_user` (`tenant_id`, `user_id`),
                    INDEX `idx_expires_at` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Throwable $e) {
            error_log("Failed to create email_verification_tokens table: " . $e->getMessage());
        }
    }
}
