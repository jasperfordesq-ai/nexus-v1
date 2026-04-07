<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Core\ApiErrorCodes;
use App\Core\TenantContext;
use App\Core\RateLimiter;
use App\Core\Mailer;
use App\Core\EmailTemplate;
use App\Services\RateLimitService;

/**
 * EmailVerificationController -- Email verification endpoints.
 *
 * Converted from delegation to direct service calls.
 * Legacy: src/Controllers/Api/EmailVerificationApiController.php
 */
class EmailVerificationController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly RateLimitService $rateLimitService,
    ) {}

    /** Token expiry in seconds (24 hours) */
    private const TOKEN_EXPIRY_SECONDS = 86400;

    /** Minimum seconds between resend requests */
    private const RESEND_COOLDOWN_SECONDS = 60;

    /** POST /api/auth/verify-email */
    public function verifyEmail(): JsonResponse
    {
        // Rate limit by IP - 20 attempts per 15 minutes
        $this->rateLimit('verify_email', 20, 900);

        $token = $this->input('token');
        $tenantId = TenantContext::getId();

        if (empty($token)) {
            return $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                __('api.verification_token_required'),
                'token',
                400
            );
        }

        // Find and validate the verification token (tenant-scoped)
        $verificationRecord = $this->findValidVerificationToken($token, $tenantId);

        if (!$verificationRecord) {
            return $this->respondWithError(
                ApiErrorCodes::AUTH_TOKEN_INVALID,
                __('api.invalid_verification_token'),
                'token',
                400
            );
        }

        $userId = $verificationRecord['user_id'];

        // Check if already verified (tenant-scoped)
        $userRow = DB::selectOne(
            "SELECT id, email_verified_at, is_verified FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );
        $user = $userRow ? (array)$userRow : null;

        if (!$user) {
            return $this->respondWithError(
                ApiErrorCodes::RESOURCE_NOT_FOUND,
                __('api.user_not_found'),
                null,
                404
            );
        }

        if (!empty($user['email_verified_at'])) {
            // Already verified - delete any remaining tokens and return success
            $this->cleanupVerificationTokens($userId, $tenantId);

            return $this->respondWithData([
                'verified' => true,
                'message' => __('api_controllers_1.email_verification.already_verified')
            ]);
        }

        // Mark user as verified and activate if pending (tenant-scoped)
        DB::update(
            "UPDATE users SET email_verified_at = NOW(), is_verified = 1, status = CASE WHEN status = 'pending' THEN 'active' ELSE status END WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );

        // Delete all verification tokens for this user in this tenant
        $this->cleanupVerificationTokens($userId, $tenantId);

        // Log the verification
        try {
            \App\Models\ActivityLog::log(
                $userId,
                'email_verified',
                'Email address verified via API'
            );
        } catch (\Throwable $e) {
            error_log("Failed to log email verification: " . $e->getMessage());
        }

        // Award gamification points if available
        try {
            if (class_exists('\App\Models\Gamification')) {
                \App\Models\Gamification::awardPoints($userId, 'email_verified', 10, 'Verified email address');
            }
        } catch (\Throwable $e) {
            // Gamification is optional
        }

        return $this->respondWithData([
            'verified' => true,
            'message' => __('api_controllers_1.email_verification.verified_successfully')
        ]);
    }

    /** POST /api/auth/resend-verification */
    public function resendVerification(): JsonResponse
    {
        $userId = $this->requireAuth();

        // Rate limit by user - 1 request per minute
        $userKey = "resend_verification:user:{$userId}";
        if (!RateLimiter::attempt($userKey, 1, self::RESEND_COOLDOWN_SECONDS)) {
            return $this->respondWithError(
                ApiErrorCodes::RATE_LIMIT_EXCEEDED,
                __('api.verification_resend_cooldown'),
                null,
                429
            );
        }

        // Get user details (tenant-scoped)
        $tenantId = TenantContext::getId();
        $userRow = DB::selectOne(
            "SELECT id, email, first_name, email_verified_at, tenant_id FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );
        $user = $userRow ? (array)$userRow : null;

        if (!$user) {
            return $this->respondWithError(
                ApiErrorCodes::RESOURCE_NOT_FOUND,
                __('api.user_not_found'),
                null,
                404
            );
        }

        // Check if already verified
        if (!empty($user['email_verified_at'])) {
            return $this->respondWithData([
                'message' => __('api_controllers_1.email_verification.already_verified'),
                'already_verified' => true
            ]);
        }

        // Generate and send new verification email
        $this->sendVerificationEmail($user);

        return $this->respondWithData([
            'message' => __('api_controllers_1.email_verification.verification_sent')
        ]);
    }

    /** POST /api/auth/resend-verification-by-email */
    public function resendVerificationByEmail(): JsonResponse
    {
        // Rate limit by IP — 3 per 5 minutes (aggressive since unauthenticated)
        $ip = \App\Core\ClientIp::get();
        if ($this->rateLimitService->check("resend_verify:$ip", 3, 300)) {
            return $this->respondWithError(
                ApiErrorCodes::RATE_LIMIT_EXCEEDED,
                __('api.rate_limit_exceeded'),
                null,
                429
            );
        }
        $this->rateLimitService->increment("resend_verify:$ip", 300);

        $email = strtolower(trim($this->input('email', '')));
        $tenantId = TenantContext::getId();

        // Always return the same success message (prevents user enumeration)
        $genericResponse = ['message' => __('api_controllers_1.email_verification.generic_resend')];

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->respondWithData($genericResponse);
        }

        // Look up user (tenant-scoped)
        $userRow = DB::selectOne(
            "SELECT id, email, first_name, email_verified_at, tenant_id FROM users WHERE email = ? AND tenant_id = ?",
            [$email, $tenantId]
        );
        $user = $userRow ? (array)$userRow : null;

        // Only send if user exists AND is not yet verified
        if ($user && empty($user['email_verified_at'])) {
            $this->sendVerificationEmail($user);
        }

        return $this->respondWithData($genericResponse);
    }

    /**
     * Send verification email to user
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
        DB::insert(
            "INSERT INTO email_verification_tokens (user_id, tenant_id, token, expires_at) VALUES (?, ?, ?, ?)",
            [$user['id'], $tenantId, $hashedToken, $expiresAt]
        );

        // Build verification URL — include tenant base path for correct routing
        $appUrl = TenantContext::getFrontendUrl();
        $basePath = TenantContext::getSlugPrefix();
        $verifyUrl = $appUrl . $basePath . "/verify-email?token=" . $token;

        // Send verification email
        try {
            $mailer = Mailer::forCurrentTenant();

            // Get tenant name
            $tenantName = 'Project NEXUS';
            if ($tenantId) {
                try {
                    $tenantRow = DB::selectOne(
                        "SELECT name FROM tenants WHERE id = ?",
                        [$tenantId]
                    );
                    if ($tenantRow) {
                        $tenantName = $tenantRow->name;
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
     */
    private function findValidVerificationToken(string $token, int $tenantId): ?array
    {
        if (!$this->tokenTableExists()) {
            return null;
        }

        $records = DB::select(
            "SELECT * FROM email_verification_tokens WHERE tenant_id = ? AND expires_at > NOW()",
            [$tenantId]
        );

        foreach ($records as $record) {
            $recordArr = (array)$record;
            if (password_verify($token, $recordArr['token'])) {
                return $recordArr;
            }
        }

        return null;
    }

    /**
     * Delete all verification tokens for a user in a specific tenant
     */
    private function cleanupVerificationTokens(int $userId, ?int $tenantId = null): void
    {
        if (!$this->tokenTableExists()) {
            return;
        }

        $tenantId = $tenantId ?? TenantContext::getId();

        DB::delete(
            "DELETE FROM email_verification_tokens WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );
    }

    /**
     * Check if the token table exists
     */
    private function tokenTableExists(): bool
    {
        static $exists = null;

        if ($exists === null) {
            try {
                $result = DB::select(
                    "SHOW TABLES LIKE 'email_verification_tokens'"
                );
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
            DB::statement("
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
