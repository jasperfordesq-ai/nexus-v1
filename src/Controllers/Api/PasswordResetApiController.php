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
use Nexus\Models\User;

/**
 * PasswordResetApiController - Stateless password reset API endpoints
 *
 * Provides API endpoints for password reset flow that work with Bearer token
 * authentication and follow v2 API response conventions.
 *
 * Endpoints:
 * - POST /api/auth/forgot-password - Request password reset email
 * - POST /api/auth/reset-password - Complete password reset with token
 *
 * Security measures:
 * - Rate limiting on both endpoints
 * - Token hashing (never store raw tokens)
 * - Same response for existing/non-existing emails (prevent enumeration)
 * - Token expiry (1 hour)
 * - Invalidate all refresh tokens after password change
 * - Strong password validation
 *
 * @package Nexus\Controllers\Api
 */
class PasswordResetApiController extends BaseApiController
{
    /** Token expiry in seconds (1 hour) */
    private const TOKEN_EXPIRY_SECONDS = 3600;

    /** Minimum password length */
    private const MIN_PASSWORD_LENGTH = 12;

    /**
     * POST /api/auth/forgot-password
     *
     * Request a password reset email. Always returns the same response
     * regardless of whether the email exists to prevent account enumeration.
     *
     * Request: { "email": "user@example.com" }
     * Response: { "data": { "message": "If an account exists..." } }
     */
    public function forgotPassword(): void
    {
        // Rate limit by IP - 5 requests per 15 minutes
        $this->rateLimit('forgot_password', 5, 900);

        $email = $this->input('email');

        // Validate email format
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_INVALID_FORMAT,
                'Please provide a valid email address',
                'email',
                400
            );
        }

        // Additional rate limit by email - 3 requests per hour
        $emailKey = 'forgot_password_email:' . strtolower($email);
        if (!RateLimiter::attempt($emailKey, 3, 3600)) {
            // Still return success to prevent enumeration
            $this->respondWithData([
                'message' => 'If an account exists with that email address, a password reset link has been sent.'
            ]);
        }

        // Process reset request (don't reveal if user exists)
        $this->processPasswordResetRequest($email);

        // Always return the same response
        $this->respondWithData([
            'message' => 'If an account exists with that email address, a password reset link has been sent.'
        ]);
    }

    /**
     * POST /api/auth/reset-password
     *
     * Complete password reset using the token from the email.
     *
     * Request: {
     *   "token": "abc123...",
     *   "password": "newSecurePassword123!",
     *   "password_confirmation": "newSecurePassword123!"
     * }
     * Response: { "data": { "message": "Password updated successfully" } }
     */
    public function resetPassword(): void
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
                'message' => 'Reset token is required',
                'field' => 'token'
            ];
        }

        if (empty($password)) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'message' => 'Password is required',
                'field' => 'password'
            ];
        }

        if (empty($passwordConfirmation)) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'message' => 'Password confirmation is required',
                'field' => 'password_confirmation'
            ];
        }

        if (!empty($errors)) {
            $this->respondWithErrors($errors, 400);
        }

        // Check passwords match
        if ($password !== $passwordConfirmation) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_ERROR,
                'Passwords do not match',
                'password_confirmation',
                400
            );
        }

        // Validate password strength
        $passwordErrors = $this->validatePasswordStrength($password);
        if (!empty($passwordErrors)) {
            $this->respondWithErrors($passwordErrors, 400);
        }

        // Find and validate the reset token
        $resetRecord = $this->findValidResetToken($token);

        if (!$resetRecord) {
            $this->respondWithError(
                ApiErrorCodes::AUTH_TOKEN_INVALID,
                'Invalid or expired reset token. Please request a new password reset.',
                'token',
                400
            );
        }

        // Update the password
        $email = $resetRecord['email'];
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        Database::query(
            "UPDATE users SET password_hash = ? WHERE email = ?",
            [$hashedPassword, $email]
        );

        // Delete all reset tokens for this email
        Database::query(
            "DELETE FROM password_resets WHERE email = ?",
            [$email]
        );

        // Invalidate all existing refresh tokens for security
        // This forces re-login on all devices after password change
        $this->invalidateUserTokens($email);

        // Log the password change
        $user = User::findByEmail($email);
        if ($user) {
            try {
                \Nexus\Models\ActivityLog::log(
                    $user['id'],
                    'password_reset',
                    'Password was reset via API'
                );
            } catch (\Throwable $e) {
                // Activity logging is optional
                error_log("Failed to log password reset: " . $e->getMessage());
            }
        }

        $this->respondWithData([
            'message' => 'Password updated successfully. Please log in with your new password.'
        ]);
    }

    /**
     * Process password reset request for an email
     *
     * @param string $email
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
        Database::query(
            "DELETE FROM password_resets WHERE email = ?",
            [$email]
        );

        // Store the hashed token
        Database::query(
            "INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, NOW())",
            [$email, $hashedToken]
        );

        // Build reset URL
        $appUrl = TenantContext::getFrontendUrl();

        // For API clients, provide a deep link or web fallback
        // Mobile apps can intercept this URL pattern
        $resetUrl = $appUrl . "/password/reset?token=" . $token;

        // Send reset email
        try {
            $mailer = new Mailer();
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
            // Log but don't expose email errors
            error_log("Password reset email failed for {$email}: " . $e->getMessage());
        }
    }

    /**
     * Find a valid (non-expired) reset token
     *
     * @param string $token The unhashed token from the user
     * @return array|null The reset record if valid, null otherwise
     */
    private function findValidResetToken(string $token): ?array
    {
        // Find all recent tokens (within expiry window)
        $records = Database::query(
            "SELECT * FROM password_resets WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [self::TOKEN_EXPIRY_SECONDS]
        )->fetchAll();

        // Check each record with password_verify (constant-time comparison)
        foreach ($records as $record) {
            if (password_verify($token, $record['token'])) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Validate password strength
     *
     * @param string $password
     * @return array Array of error objects if invalid, empty if valid
     */
    private function validatePasswordStrength(string $password): array
    {
        $errors = [];

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_TOO_SHORT,
                'message' => 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters long',
                'field' => 'password'
            ];
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_INVALID_FORMAT,
                'message' => 'Password must contain at least one uppercase letter',
                'field' => 'password'
            ];
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_INVALID_FORMAT,
                'message' => 'Password must contain at least one lowercase letter',
                'field' => 'password'
            ];
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_INVALID_FORMAT,
                'message' => 'Password must contain at least one number',
                'field' => 'password'
            ];
        }

        if (!preg_match('/[\W_]/', $password)) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_INVALID_FORMAT,
                'message' => 'Password must contain at least one special character',
                'field' => 'password'
            ];
        }

        return $errors;
    }

    /**
     * Invalidate all refresh tokens for a user
     *
     * Since we use stateless JWTs, we can't truly invalidate them.
     * However, we can:
     * 1. Update a "password_changed_at" timestamp that tokens are validated against
     * 2. Or store a token version that increments on password change
     *
     * For now, we'll update the user's password_changed_at field if it exists.
     *
     * @param string $email
     */
    private function invalidateUserTokens(string $email): void
    {
        try {
            // Check if password_changed_at column exists
            $columns = Database::query(
                "SHOW COLUMNS FROM users LIKE 'password_changed_at'"
            )->fetchAll();

            if (!empty($columns)) {
                Database::query(
                    "UPDATE users SET password_changed_at = NOW() WHERE email = ?",
                    [$email]
                );
            }
        } catch (\Throwable $e) {
            // Column might not exist, that's fine
            error_log("Could not update password_changed_at: " . $e->getMessage());
        }
    }
}
