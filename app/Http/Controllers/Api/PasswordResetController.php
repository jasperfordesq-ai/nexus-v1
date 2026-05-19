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
use App\Core\EmailTemplateBuilder;
use App\I18n\LocaleContext;
use App\Models\Notification;
use App\Models\User;
use App\Services\EmailDispatchService;
use Illuminate\Support\Facades\Log;

/**
 * PasswordResetController -- Password reset flow.
 */
class PasswordResetController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly TokenService $tokenService,
        private readonly \App\Services\PwnedPasswordService $pwnedPassword,
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

        // Forgot-password Turnstile gate removed 2026-05-16 — too many false
        // positives blocked legitimate users from recovering their accounts.
        // Bot/enumeration defence: per-IP 5/15min route throttle + per-email
        // 10/hr limiter below + email enumeration safety (always-success
        // response regardless of whether the address exists).

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

        // Additional rate limit by email - 10 requests per hour. Bumped from
        // 3/hr (2026-05-15) because legitimate users frustrated by typos or
        // missing inbox delivery were silently rate-limited and never saw a
        // reset email at all. Bots are caught by the per-IP throttle (5/min)
        // and the Turnstile gate above, so the per-email cap exists only to
        // prevent a single-account spam loop, not as a primary defence.
        $emailKey = 'forgot_password_email:' . strtolower($email);
        if (!RateLimiter::attempt($emailKey, 10, 3600)) {
            $masked = substr($email, 0, 2) . '***@' . (explode('@', $email)[1] ?? '***');
            Log::warning('[PasswordReset] per-email rate limit hit', [
                'email_masked' => $masked,
                'ip' => \App\Core\ClientIp::get(),
            ]);
            // Surface the rate limit so the frontend can tell the user to wait
            // rather than promising an email that will never arrive. This is
            // safe against enumeration: the limiter triggers regardless of
            // whether the email belongs to a real account.
            return $this->respondWithError(
                ApiErrorCodes::RATE_LIMIT_EXCEEDED,
                __('api.too_many_attempts'),
                'email',
                429
            );
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

        // HIBP check — reject if the chosen new password appears in known
        // breach corpora. Same rule as registration.
        if ($this->pwnedPassword->isPwned($password)) {
            return $this->respondWithError(
                ApiErrorCodes::VALIDATION_ERROR,
                __('api.password_pwned'),
                'password',
                422
            );
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
        $tokenTenantId = isset($resetRecord['tenant_id']) ? (int) $resetRecord['tenant_id'] : null;
        $hashedPassword = password_hash($password, PASSWORD_ARGON2ID);

        // Find the user globally — the reset token validates identity, and the user
        // may be resetting from a different tenant context than where they belong
        $userRow = DB::table('users')
            ->where('email', $email)
            ->where('tenant_id', $tokenTenantId)
            ->whereNull('deleted_at')
            ->first();
        $user = $userRow ? (array) $userRow : null;

        if (!$user) {
            return $this->respondWithError(
                ApiErrorCodes::AUTH_TOKEN_INVALID,
                __('api.unable_to_reset_password'),
                'token',
                400
            );
        }

        // Defence-in-depth: every active token MUST carry its origin tenant.
        // The migration purges pre-existing NULL-tenant rows; a NULL here can
        // only mean tampering or schema drift — reject either way.
        if ($tokenTenantId === null || (int) $user['tenant_id'] !== $tokenTenantId) {
            return $this->respondWithError(
                ApiErrorCodes::AUTH_TOKEN_INVALID,
                __('api.invalid_reset_token'),
                'token',
                400
            );
        }

        // Atomically update password, delete reset tokens, and revoke session tokens
        DB::transaction(function () use ($hashedPassword, $user, $email, $tokenTenantId) {
            // Update by user ID AND tenant_id — defense-in-depth against cross-tenant updates
            DB::update(
                "UPDATE users SET password_hash = ? WHERE id = ? AND tenant_id = ?",
                [$hashedPassword, $user['id'], $user['tenant_id']]
            );

            // Delete reset tokens for this (email, tenant) pair only.
            if ($tokenTenantId !== null) {
                DB::delete(
                    "DELETE FROM password_resets WHERE email = ? AND tenant_id <=> ?",
                    [$email, $tokenTenantId]
                );
            } else {
                DB::delete(
                    "DELETE FROM password_resets WHERE email = ? AND tenant_id IS NULL",
                    [$email]
                );
            }

            // Invalidate all existing refresh tokens for security.
            $this->invalidateUserTokens((int) $user['id'], (int) $tokenTenantId);
        });

        // Log the password change
        try {
            \App\Models\ActivityLog::log(
                $user['id'],
                'password_reset',
                'Password was reset via API'
            );
        } catch (\Throwable $e) {
            Log::warning('[PasswordReset] Failed to log password reset: ' . $e->getMessage());
        }

        // Security notification: bell + email for password change
        // Both render under the user's preferred_language.
        $recipientLocale = $user['preferred_language'] ?? null;
        try {
            LocaleContext::withLocale($recipientLocale, function () use ($user) {
                Notification::createNotification(
                    (int) $user['id'],
                    __('api_controllers_2.password_reset.changed_bell'),
                    null,
                    'password_changed'
                );
            });
        } catch (\Throwable $e) {
            Log::warning('[PasswordReset] Failed to create password change notification: ' . $e->getMessage());
        }

        $previousTenantId = TenantContext::currentId();
        try {
            TenantContext::setById($tokenTenantId);
            LocaleContext::withLocale($recipientLocale, function () use ($email, $tokenTenantId) {
                $tenantName = TenantContext::get()['name'] ?? 'Project NEXUS';

                $html = EmailTemplateBuilder::make()
                    ->theme('warning')
                    ->title(__('emails.security.password_changed_title'))
                    ->previewText(__('emails.security.password_changed_preview'))
                    ->paragraph(__('emails.security.password_changed_body'))
                    ->highlight(__('emails.security.password_changed_warning'))
                    ->render();

                $subject = __('emails.security.password_changed_subject', ['community' => $tenantName]);
                if (!EmailDispatchService::sendRaw($email, $subject, $html, null, null, null, 'security_alert', ['tenant_id' => $tokenTenantId])) {
                    Log::warning('[PasswordReset] password change email send returned false');
                }
            });
        } catch (\Throwable $e) {
            Log::warning('[PasswordReset] Failed to send password change email: ' . $e->getMessage());
        } finally {
            if ($previousTenantId !== null) {
                TenantContext::setById($previousTenantId);
            } else {
                TenantContext::reset();
            }
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
        $masked = substr($email, 0, 2) . '***@' . (explode('@', $email)[1] ?? '***');
        $user = $this->resolvePasswordResetUser($email);

        if (!$user) {
            // User doesn't exist, but we don't reveal this. Log so operators
            // can diagnose "no email arrived" complaints — distinguishes
            // "wrong email" from "mailer broken".
            Log::info('[PasswordReset] reset requested for unknown email', [
                'email_masked' => $masked,
                'tenant_id' => TenantContext::getId(),
                'ip' => \App\Core\ClientIp::get(),
            ]);
            return;
        }

        // Generate a secure random token (256-bit entropy, hex-encoded)
        $token = bin2hex(random_bytes(32));

        // Hash the token with SHA-256 for storage. SHA-256 is appropriate here
        // (NOT for passwords) because the token is high-entropy random data.
        // This enables an indexed exact-match lookup instead of scanning
        // every non-expired record with bcrypt's password_verify().
        $hashedToken = hash('sha256', $token);

        $userTenantId = isset($user['tenant_id']) ? (int) $user['tenant_id'] : null;

        // Build reset URL — include tenant base path for correct routing
        $resetUrl = null;
        $tenantName = 'Project NEXUS';
        try {
            TenantContext::setById($userTenantId);
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
            $tenantName = TenantContext::get()['name'] ?? 'Project NEXUS';
        } finally {
            TenantContext::reset();
        }

        // Send reset email under the user's preferred locale
        try {
            TenantContext::setById($userTenantId);
            $resetEmailSent = (bool) LocaleContext::withLocale($user['preferred_language'] ?? null, function () use ($user, $email, $resetUrl, $tenantName, $userTenantId) {
                $firstName = $user['first_name'] ?? ($user['name'] ?? null);
                $greeting = $firstName
                    ? __('emails.password_reset.greeting', ['name' => htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8')])
                    : null;

                $html = EmailTemplateBuilder::make()
                    ->theme('warning')
                    ->title(__('emails.password_reset.title'))
                    ->previewText(__('emails.password_reset.preview'))
                    ->greeting($greeting ?? __('emails.password_reset.greeting', ['name' => 'there']))
                    ->paragraph(__('emails.password_reset.body'))
                    ->paragraph(__('emails.password_reset.expiry'))
                    ->button(__('emails.password_reset.cta'), $resetUrl)
                    ->paragraph(__('emails.password_reset.ignore'))
                    ->render();

                $subject = __('emails.password_reset.subject', ['community' => $tenantName]);
                return EmailDispatchService::sendRaw($email, $subject, $html, null, null, null, 'password_reset', ['tenant_id' => $userTenantId]);
            });
            if ($resetEmailSent) {
                // Rotate reset tokens only after the dispatcher accepts the new
                // email. If SMTP/Gmail fails, any previous valid link remains
                // usable instead of being silently invalidated.
                if ($userTenantId !== null) {
                    DB::delete(
                        "DELETE FROM password_resets WHERE email = ? AND tenant_id <=> ?",
                        [$email, $userTenantId]
                    );
                } else {
                    DB::delete(
                        "DELETE FROM password_resets WHERE email = ? AND tenant_id IS NULL",
                        [$email]
                    );
                }

                DB::insert(
                    "INSERT INTO password_resets (email, tenant_id, token, created_at) VALUES (?, ?, ?, NOW())",
                    [$email, $userTenantId, $hashedToken]
                );

                Log::info('[PasswordReset] reset email dispatched', [
                    'email_masked' => $masked,
                    'user_id' => $user['id'] ?? null,
                    'tenant_id' => $userTenantId,
                ]);
            } else {
                Log::warning('[PasswordReset] reset email send returned false', [
                    'email_masked' => $masked,
                    'user_id' => $user['id'] ?? null,
                    'tenant_id' => $userTenantId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[PasswordReset] Password reset email failed for ' . $masked . ': ' . $e->getMessage(), [
                'user_id' => $user['id'] ?? null,
                'tenant_id' => $userTenantId,
            ]);
        } finally {
            TenantContext::reset();
        }
    }

    private function resolvePasswordResetUser(string $email): ?array
    {
        $tenantId = TenantContext::getId();
        $normalizedEmail = strtolower(trim($email));

        if ($tenantId && $tenantId !== 1) {
            $row = DB::table('users')
                ->where('email', $normalizedEmail)
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->first();

            return $row ? (array) $row : null;
        }

        $rows = DB::table('users')
            ->where('email', $normalizedEmail)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($rows->count() === 1) {
            return (array) $rows->first();
        }

        if ($rows->count() > 1) {
            Log::warning('[PasswordReset] ambiguous global reset request for email present in multiple tenants', [
                'email_masked' => substr($email, 0, 2) . '***@' . (explode('@', $email)[1] ?? '***'),
            ]);
        }

        return null;
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

        // Hash the supplied token with SHA-256 and look it up directly by
        // the indexed token column. This eliminates the linear-scan timing
        // signal from the previous bcrypt-per-row approach.
        $hashedToken = hash('sha256', $token);

        $record = DB::selectOne(
            "SELECT * FROM password_resets WHERE token = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND) LIMIT 1",
            [$hashedToken, self::TOKEN_EXPIRY_SECONDS]
        );

        if (!$record) {
            return null;
        }

        $recordArr = (array)$record;

        // Constant-time confirmation (defence in depth — DB equality already
        // matched, but hash_equals avoids any future-proofing surprises).
        if (!hash_equals($recordArr['token'], $hashedToken)) {
            return null;
        }

        return $recordArr;
    }

    /**
     * Validate password strength
     */
    private function validatePasswordStrength(string $password): array
    {
        // NIST SP 800-63B aligned: enforce length only, no mandatory
        // character classes. The HIBP breach check (called by the
        // resetPassword handler immediately after this) is the meaningful
        // defence; complexity rules push users to predictable patterns
        // without slowing down attackers.
        $errors = [];
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_TOO_SHORT,
                'message' => __('api.password_min_length_generic', ['length' => self::MIN_PASSWORD_LENGTH]),
                'field' => 'password',
            ];
        }
        return $errors;
    }

    /**
     * Invalidate all tokens for a user after password change
     */
    private function invalidateUserTokens(int $userId, int $tenantId): void
    {
        try {
            $this->tokenService->revokeAllTokensForUser($userId);
        } catch (\Throwable $e) {
            Log::warning('[PasswordReset] Could not revoke tokens for user: ' . $e->getMessage(), ['user_id' => $userId]);
        }

        try {
            $columns = DB::select(
                "SHOW COLUMNS FROM users LIKE 'password_changed_at'"
            );

            if (!empty($columns)) {
                DB::update(
                    "UPDATE users SET password_changed_at = NOW() WHERE id = ? AND tenant_id = ?",
                    [$userId, $tenantId]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('[PasswordReset] Could not update password_changed_at: ' . $e->getMessage(), ['user_id' => $userId]);
        }
    }
}
