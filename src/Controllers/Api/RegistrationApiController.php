<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\ApiErrorCodes;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\RateLimiter;
use Nexus\Core\Mailer;
use Nexus\Core\EmailTemplate;
use Nexus\Services\TokenService;

/**
 * RegistrationApiController - V2 user registration endpoint
 *
 * Provides a standardized v2 API endpoint for user registration that:
 * - Returns tokens immediately after registration
 * - Uses field-level validation errors
 * - Follows the v2 response envelope format
 * - Triggers email verification flow
 *
 * Endpoint:
 * - POST /api/v2/auth/register
 *
 * Security measures:
 * - Rate limiting (5 per hour per IP)
 * - Strong password validation (12+ characters)
 * - Email uniqueness validation
 * - Tenant context awareness
 *
 * @package Nexus\Controllers\Api
 */
class RegistrationApiController extends BaseApiController
{
    /** Mark this as a v2 API */
    protected bool $isV2Api = true;

    /** Minimum password length */
    private const MIN_PASSWORD_LENGTH = 12;

    /**
     * POST /api/v2/auth/register
     *
     * Register a new user account.
     *
     * Request:
     * {
     *   "email": "user@example.com",
     *   "password": "...",
     *   "password_confirmation": "...",
     *   "name": "John Doe",            // OR first_name/last_name
     *   "first_name": "John",
     *   "last_name": "Doe",
     *   "tenant_id": 1                  // Optional, defaults to current tenant
     * }
     *
     * Response (201):
     * {
     *   "data": {
     *     "user": { "id": 1, "email": "...", "name": "..." },
     *     "access_token": "...",
     *     "refresh_token": "...",
     *     "requires_verification": true
     *   }
     * }
     */
    public function register(): void
    {
        // Rate limit by IP - 5 registrations per hour
        $this->rateLimit('registration', 5, 3600);

        // Collect input
        $email = trim($this->input('email', ''));
        $password = $this->input('password', '');
        $passwordConfirmation = $this->input('password_confirmation', '');
        $tenantId = $this->inputInt('tenant_id', TenantContext::getId());

        // Handle name - accept either full name or first/last
        $firstName = trim($this->input('first_name', ''));
        $lastName = trim($this->input('last_name', ''));
        $fullName = trim($this->input('name', ''));

        if (empty($firstName) && !empty($fullName)) {
            $parts = explode(' ', $fullName, 2);
            $firstName = $parts[0];
            $lastName = $parts[1] ?? '';
        }

        // Validate all fields and collect errors
        $errors = [];

        // Email validation
        if (empty($email)) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'message' => 'Email is required',
                'field' => 'email'
            ];
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_INVALID_FORMAT,
                'message' => 'Please provide a valid email address',
                'field' => 'email'
            ];
        }

        // Name validation
        if (empty($firstName)) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'message' => 'First name (or name) is required',
                'field' => 'first_name'
            ];
        }

        // Password validation
        if (empty($password)) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'message' => 'Password is required',
                'field' => 'password'
            ];
        } elseif (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_TOO_SHORT,
                'message' => 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters',
                'field' => 'password'
            ];
        }

        // Password confirmation
        if (!empty($password) && $password !== $passwordConfirmation) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_INVALID_VALUE,
                'message' => 'Passwords do not match',
                'field' => 'password_confirmation'
            ];
        }

        // Return all validation errors at once
        if (!empty($errors)) {
            $this->respondWithErrors($errors, 400);
        }

        // Check if email already exists
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_DUPLICATE,
                'An account with this email already exists',
                'email',
                409
            );
        }

        // Create the user
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $stmt = $db->prepare("
                INSERT INTO users (first_name, last_name, email, password_hash, tenant_id, role, email_verified, created_at)
                VALUES (?, ?, ?, ?, ?, 'member', 0, NOW())
            ");
            $stmt->execute([$firstName, $lastName, $email, $passwordHash, $tenantId]);
            $userId = (int) $db->lastInsertId();
        } catch (\Exception $e) {
            error_log('[Registration] Failed to create user: ' . $e->getMessage());
            $this->respondWithError(
                ApiErrorCodes::SERVER_INTERNAL_ERROR,
                'Failed to create account. Please try again.',
                null,
                500
            );
        }

        // Generate tokens
        $isMobile = TokenService::isMobileRequest();
        $accessToken = TokenService::generateToken($userId, $tenantId, [
            'role' => 'member',
            'email' => $email
        ], $isMobile);
        $refreshToken = TokenService::generateRefreshToken($userId, $tenantId, $isMobile);

        // Send verification email (async/non-blocking)
        $this->sendVerificationEmail($userId, $email, $firstName);

        // Return success response
        $this->respondWithData([
            'user' => [
                'id' => $userId,
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'name' => trim($firstName . ' ' . $lastName),
                'tenant_id' => $tenantId
            ],
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => TokenService::getAccessTokenExpiry($isMobile),
            'refresh_expires_in' => TokenService::getRefreshTokenExpiry($isMobile),
            'requires_verification' => true
        ], null, 201);
    }

    /**
     * Send email verification email to the new user
     *
     * @param int $userId
     * @param string $email
     * @param string $firstName
     */
    private function sendVerificationEmail(int $userId, string $email, string $firstName): void
    {
        try {
            // Generate verification token
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24 hours

            $db = Database::getConnection();

            // Store token hash (never store raw token)
            $stmt = $db->prepare("
                INSERT INTO email_verification_tokens (user_id, token, expires_at)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, $tokenHash, $expiresAt]);

            // Build verification URL
            $baseUrl = TenantContext::getDomain() ?: 'https://project-nexus.ie';
            $verifyUrl = $baseUrl . '/verify-email?token=' . $token . '&user=' . $userId;

            // Send email
            $siteName = TenantContext::getSetting('site_name', 'Project NEXUS');
            $subject = 'Verify your email address - ' . $siteName;

            $html = EmailTemplate::wrap(
                $subject,
                '<h2>Welcome to ' . htmlspecialchars($siteName) . '!</h2>
                <p>Hi ' . htmlspecialchars($firstName) . ',</p>
                <p>Thanks for registering. Please verify your email address by clicking the button below:</p>
                <p style="text-align: center; margin: 30px 0;">
                    <a href="' . htmlspecialchars($verifyUrl) . '"
                       style="background: #6366f1; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; display: inline-block;">
                        Verify Email Address
                    </a>
                </p>
                <p>Or copy and paste this link into your browser:</p>
                <p style="word-break: break-all;">' . htmlspecialchars($verifyUrl) . '</p>
                <p>This link will expire in 24 hours.</p>
                <p>If you did not create an account, you can ignore this email.</p>'
            );

            Mailer::send($email, $subject, $html);

        } catch (\Exception $e) {
            // Log but don't fail registration if email fails
            error_log('[Registration] Failed to send verification email: ' . $e->getMessage());
        }
    }
}
