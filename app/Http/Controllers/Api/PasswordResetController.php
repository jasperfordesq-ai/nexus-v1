<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Core\ApiErrorCodes;
use App\Core\Env;
use App\Core\TenantContext;
use App\Core\RateLimiter;
use App\Core\Mailer;
use App\Core\EmailTemplate;
use App\Models\Notification;
use App\Models\User;

/**
 * PasswordResetController -- Password reset flow.
 *
 * Converted from delegation to direct service calls.
 * Legacy: src/Controllers/Api/PasswordResetApiController.php
 */
class PasswordResetController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly TokenService $tokenService,
    ) {}

    /** Token expiry in seconds (1 hour) */
    private const TOKEN_EXPIRY_SECONDS = 3600;

    /** Minimum password length */
    private const MIN_PASSWORD_LENGTH = 12;

    /** POST auth/forgot-password */
    public function forgotPassword(): JsonResponse
    {
        // Rate limit by IP - 5 requests per 15 minutes
        $this->rateLimit('forgot_password', 5, 900);

        $email = $this->input('email');

        // Validate email format
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->respondWithError(
                ApiErrorCodes::VALIDATION_INVALID_FORMAT,
                __('api.valid_email_required'),
                'email',
                400
            );
        }

        // Additional rate limit by email - 3 requests per hour
        $emailKey = 'forgot_password_email:' . strtolower($email);
        if (!RateLimiter::attempt($emailKey, 3, 3600)) {
            // Still return success to prevent enumeration
            return $this->respondWithData([
                'message' => __('api_controllers_2.password_reset.link_sent')
            ]);
        }

        // Process reset request (don't reveal if user exists)
        $this->processPasswordResetRequest($email);

        // Always return the same response
        return $this->respondWithData([
            'message' => __('api_controllers_2.password_reset.link_sent')
        ]);
    }

    /** POST auth/reset-password */
    public function resetPassword(): JsonResponse
    {
        // Rate limit by IP - 10 attempts per 15 minutes
        $this->rateLimit('reset_password', 10, 900);

        $token = $this->input('token');
        $password = $this->input('password');
        $passwordConfirmation = $this->input('password_confirmation');

        // Validate required fields
        $errors = [];

        if (empty($token)) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'message' => __('api.reset_token_required'),
                'field' => 'token'
            ];
        }

        if (empty($password)) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'message' => __('api.password_required'),
                'field' => 'password'
            ];
        }

        if (empty($passwordConfirmation)) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'message' => __('api.password_confirmation_required'),
                'field' => 'password_confirmation'
            ];
        }

        if (!empty($errors)) {
            return $this->respondWithErrors($errors, 400);
        }

        // Check passwords match
        if ($password !== $passwordConfirmation) {
            return $this->respondWithError(
                ApiErrorCodes::VALIDATION_ERROR,
                __('api.passwords_do_not_match'),
                'password_confirmation',
                400
            );
        }

        // Validate password strength
        $passwordErrors = $this->validatePasswordStrength($password);
        if (!empty($passwordErrors)) {
            return $this->respondWithErrors($passwordErrors, 400);
        }

        // Find and validate the reset token
        $resetRecord = $this->findValidResetToken($token);

        if (!$resetRecord) {
            return $this->respondWithError(
                ApiErrorCodes::AUTH_TOKEN_INVALID,
                __('api.invalid_reset_token'),
                'token',
                400
            );
        }

        // Update the password — scope by user ID to prevent cross-tenant updates
        $email = $resetRecord['email'];
        $hashedPassword = password_hash($password, PASSWORD_ARGON2ID);

        // Find the user globally — the reset token validates identity, and the user
        // may be resetting from a different tenant context than where they belong
        $user = User::findGlobalByEmail($email);

        if (!$user) {
            return $this->respondWithError(
                ApiErrorCodes::AUTH_TOKEN_INVALID,
                __('api.unable_to_reset_password'),
                'token',
                400
            );
        }

        // Update by user ID AND tenant_id — defense-in-depth against cross-tenant updates
        DB::update(
            "UPDATE users SET password_hash = ? WHERE id = ? AND tenant_id = ?",
            [$hashedPassword, $user['id'], $user['tenant_id']]
        );

        // Delete all reset tokens for this email
        DB::delete(
            "DELETE FROM password_resets WHERE email = ?",
            [$email]
        );

        // Invalidate all existing refresh tokens for security
        $this->invalidateUserTokens((int)$user['id']);

        // Log the password change
        try {
            \App\Models\ActivityLog::log(
                $user['id'],
                'password_reset',
                'Password was reset via API'
            );
        } catch (\Throwable $e) {
            error_log("Failed to log password reset: " . $e->getMessage());
        }

        // Security notification: bell + email for password change
        try {
            Notification::createNotification(
                (int) $user['id'],
                'Your password was changed. If you did not do this, contact support immediately.',
                null,
                'password_changed'
            );
        } catch (\Throwable $e) {
            error_log("Failed to create password change notification: " . $e->getMessage());
        }

        try {
            $mailer = Mailer::forCurrentTenant();
            $tenantName = TenantContext::get()['name'] ?? 'Project NEXUS';

            $html = EmailTemplate::render(
                "Password Changed",
                "Your password was recently changed.",
                "Your account password was changed. If you did not make this change, please contact support immediately to secure your account.",
                null,
                null,
                $tenantName
            );

            $mailer->send($email, "Security Alert: Password Changed - " . $tenantName, $html);
        } catch (\Throwable $e) {
            error_log("Failed to send password change email: " . $e->getMessage());
        }

        return $this->respondWithData([
            'message' => __('api_controllers_2.password_reset.updated')
        ]);
    }

    /**
     * Process password reset request for an email
     */
    private function processPasswordResetRequest(string $email): void
    {
        // Find user by email (check across all tenants for this operation)
        $user = User::findGlobalByEmail($email);

        if (!$user) {
            // User doesn't exist, but we don't reveal this
            return;
        }

        // Generate a secure random token
        $token = bin2hex(random_bytes(32));

        // Hash the token before storing (we send unhashed token via email)
        $hashedToken = password_hash($token, PASSWORD_DEFAULT);

        // Delete any existing tokens for this email
        DB::delete(
            "DELETE FROM password_resets WHERE email = ?",
            [$email]
        );

        // Store the hashed token
        DB::insert(
            "INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, NOW())",
            [$email, $hashedToken]
        );

        // Build reset URL — include tenant base path for correct routing
        $appUrl = TenantContext::getFrontendUrl();
        $basePath = TenantContext::getSlugPrefix();

        // Defensive: ensure frontend URL is never the API URL
        if (!$appUrl || str_contains($appUrl, 'api.')) {
            $appUrl = Env::get('APP_URL', 'https://app.project-nexus.ie');
            if (str_contains($appUrl, 'api.')) {
                $appUrl = str_replace('api.', 'app.', $appUrl);
            }
        }

        $resetUrl = $appUrl . $basePath . "/password/reset?token=" . $token;

        // Send reset email
        try {
            $mailer = Mailer::forCurrentTenant();
            $tenantName = TenantContext::get()['name'] ?? 'Project NEXUS';

            $html = EmailTemplate::render(
                "Reset Your Password",
                "We received a request to reset your password.",
                "Click the button below to set a new password. This link will expire in 1 hour.<br><br>If you did not request this change, please ignore this email or contact support if you have concerns.",
                "Reset Password",
                $resetUrl,
                $tenantName
            );

            $mailer->send($email, "Password Reset Request - " . $tenantName, $html);
        } catch (\Throwable $e) {
            $maskedEmail = substr($email, 0, 2) . '***@' . (explode('@', $email)[1] ?? '***');
            error_log("Password reset email failed for {$maskedEmail}: " . $e->getMessage());
        }
    }

    /**
     * Find a valid (non-expired) reset token
     */
    private function findValidResetToken(string $token): ?array
    {
        // Clean up expired tokens
        DB::delete(
            "DELETE FROM password_resets WHERE created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [self::TOKEN_EXPIRY_SECONDS]
        );

        // Fetch all non-expired tokens
        $records = DB::select(
            "SELECT * FROM password_resets WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND) ORDER BY created_at DESC LIMIT 500",
            [self::TOKEN_EXPIRY_SECONDS]
        );

        // Check each record with password_verify (constant-time comparison)
        foreach ($records as $record) {
            $recordArr = (array)$record;
            if (password_verify($token, $recordArr['token'])) {
                return $recordArr;
            }
        }

        return null;
    }

    /**
     * Validate password strength
     */
    private function validatePasswordStrength(string $password): array
    {
        $errors = [];

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_TOO_SHORT,
                'message' => __('api.password_min_length_generic', ['length' => self::MIN_PASSWORD_LENGTH]),
                'field' => 'password'
            ];
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_INVALID_FORMAT,
                'message' => __('api.password_uppercase'),
                'field' => 'password'
            ];
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_INVALID_FORMAT,
                'message' => __('api.password_lowercase'),
                'field' => 'password'
            ];
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_INVALID_FORMAT,
                'message' => __('api.password_number'),
                'field' => 'password'
            ];
        }

        if (!preg_match('/[\W_]/', $password)) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_INVALID_FORMAT,
                'message' => __('api.password_special_char'),
                'field' => 'password'
            ];
        }

        return $errors;
    }

    /**
     * Invalidate all tokens for a user after password change
     */
    private function invalidateUserTokens(int $userId): void
    {
        try {
            $this->tokenService->revokeAllTokensForUser($userId);
        } catch (\Throwable $e) {
            error_log("Could not revoke tokens for user {$userId}: " . $e->getMessage());
        }

        try {
            $columns = DB::select(
                "SHOW COLUMNS FROM users LIKE 'password_changed_at'"
            );

            if (!empty($columns)) {
                DB::update(
                    "UPDATE users SET password_changed_at = NOW() WHERE id = ? AND tenant_id = ?",
                    [$userId, $this->getTenantId()]
                );
            }
        } catch (\Throwable $e) {
            error_log("Could not update password_changed_at: " . $e->getMessage());
        }
    }
}
