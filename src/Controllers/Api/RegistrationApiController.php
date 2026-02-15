<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\ApiErrorCodes;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\RateLimiter;
use Nexus\Core\Mailer;
use Nexus\Core\EmailTemplate;
use Nexus\Core\Validator;
use Nexus\Services\TokenService;
use Nexus\Services\LegalDocumentService;

/**
 * RegistrationApiController - V2 user registration endpoint
 *
 * Provides a standardized v2 API endpoint for user registration that:
 * - Returns tokens immediately after registration
 * - Uses field-level validation errors
 * - Follows the v2 response envelope format
 * - Triggers email verification flow
 * - Records GDPR consent and legal document acceptance
 * - Subscribes to newsletter if opted in
 * - Notifies admins of new registrations
 *
 * Endpoint:
 * - POST /api/v2/auth/register
 *
 * Security measures:
 * - Rate limiting (5 per hour per IP)
 * - Strong password validation (12+ characters with complexity)
 * - Email uniqueness validation
 * - Tenant context awareness
 * - Bot protection (timestamp validation)
 *
 * @package Nexus\Controllers\Api
 */
class RegistrationApiController extends BaseApiController
{
    /** Mark this as a v2 API */
    protected bool $isV2Api = true;

    /** Minimum password length */
    private const MIN_PASSWORD_LENGTH = 12;

    /** Minimum form submission time (seconds) - bot protection */
    private const MIN_FORM_TIME = 3;

    /**
     * POST /api/v2/auth/register
     *
     * Register a new user account.
     *
     * Request:
     * {
     *   "email": "user@example.com",
     *   "password": "SecurePass123!",
     *   "password_confirmation": "SecurePass123!",
     *   "first_name": "John",
     *   "last_name": "Doe",
     *   "tenant_id": 1,                         // Optional, defaults to current tenant
     *   "profile_type": "individual",           // Optional: individual or organisation
     *   "organization_name": "Acme Corp",       // Required if profile_type is organisation
     *   "location": "Dublin, Ireland",          // Optional
     *   "phone": "087 123 4567",                // Optional
     *   "terms_accepted": true,                 // Required
     *   "newsletter_opt_in": false              // Optional
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

        // Collect input - Basic
        $email = strtolower(trim($this->input('email', '')));
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

        // Collect input - Profile
        $profileType = $this->input('profile_type', 'individual');
        if (!in_array($profileType, ['individual', 'organisation'])) {
            $profileType = 'individual';
        }
        $organizationName = trim($this->input('organization_name', ''));

        // Collect input - Contact
        $location = trim($this->input('location', ''));
        $phone = trim($this->input('phone', ''));

        // Collect input - Consents
        $termsAccepted = $this->inputBool('terms_accepted', false);
        $newsletterOptIn = $this->inputBool('newsletter_opt_in', false);

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
                'message' => 'First name is required',
                'field' => 'first_name'
            ];
        }

        if (empty($lastName)) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'message' => 'Last name is required',
                'field' => 'last_name'
            ];
        }

        // Organisation name required for organisations
        if ($profileType === 'organisation' && empty($organizationName)) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'message' => 'Organisation name is required',
                'field' => 'organization_name'
            ];
        }

        // Phone validation (optional, but validate format if provided)
        if (!empty($phone) && !Validator::isIrishPhone($phone)) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_INVALID_FORMAT,
                'message' => 'Please enter a valid phone number (e.g., 087 123 4567)',
                'field' => 'phone'
            ];
        }

        // Terms acceptance required
        if (!$termsAccepted) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'message' => 'You must accept the Terms of Service and Privacy Policy',
                'field' => 'terms_accepted'
            ];
        }

        // Password validation
        $passwordErrors = $this->validatePasswordStrength($password);
        $errors = array_merge($errors, $passwordErrors);

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
                INSERT INTO users (
                    first_name, last_name, email, password_hash, tenant_id,
                    profile_type, organization_name, location, phone,
                    role, status, email_verified, created_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'member', 'pending', 0, NOW())
            ");
            $stmt->execute([
                $firstName,
                $lastName,
                $email,
                $passwordHash,
                $tenantId,
                $profileType,
                $organizationName ?: null,
                $location ?: null,
                $phone ?: null
            ]);
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

        // Post-registration actions (non-blocking)
        $this->recordGdprConsent($userId, $tenantId);
        $this->recordLegalDocumentAcceptance($userId);
        $this->logActivity($userId);

        if ($newsletterOptIn) {
            $this->subscribeToNewsletter($userId, $email, $firstName, $lastName);
        }

        $this->notifyAdmins($userId, $firstName, $lastName, $email, $tenantId);
        $this->awardWelcomeXp($userId);

        // Generate tokens
        $isMobile = TokenService::isMobileRequest();
        $accessToken = TokenService::generateToken($userId, $tenantId, [
            'role' => 'member',
            'email' => $email
        ], $isMobile);
        $refreshToken = TokenService::generateRefreshToken($userId, $tenantId, $isMobile);

        // Send verification email
        $this->sendVerificationEmail($userId, $email, $firstName);

        // Return success response
        $this->respondWithData([
            'user' => [
                'id' => $userId,
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'name' => trim($firstName . ' ' . $lastName),
                'profile_type' => $profileType,
                'organization_name' => $organizationName ?: null,
                'location' => $location ?: null,
                'tenant_id' => $tenantId,
                'status' => 'pending'
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
     * Validate password strength (matching frontend validation)
     *
     * Requirements:
     * - At least 12 characters
     * - At least one uppercase letter
     * - At least one lowercase letter
     * - At least one number
     * - At least one special character
     *
     * @param string $password
     * @return array Array of error objects if invalid, empty if valid
     */
    private function validatePasswordStrength(string $password): array
    {
        $errors = [];

        if (empty($password)) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'message' => 'Password is required',
                'field' => 'password'
            ];
            return $errors;
        }

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors[] = [
                'code' => ApiErrorCodes::VALIDATION_TOO_SHORT,
                'message' => 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters',
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
     * Record GDPR consent for the user
     */
    private function recordGdprConsent(int $userId, int $tenantId): void
    {
        try {
            $gdprService = new \Nexus\Services\Enterprise\GdprService($tenantId);
            $consentText = "I have read and agree to the Terms of Service and Privacy Policy.";
            $consentVersion = '1.0';

            // Record Terms of Service consent
            $gdprService->recordConsent($userId, 'terms_of_service', true, $consentText, $consentVersion);

            // Record Privacy Policy consent
            $gdprService->recordConsent($userId, 'privacy_policy', true, $consentText, $consentVersion);

        } catch (\Throwable $e) {
            error_log("[Registration] GDPR Consent Recording Failed: " . $e->getMessage());
        }
    }

    /**
     * Record legal document acceptance (versioned)
     */
    private function recordLegalDocumentAcceptance(int $userId): void
    {
        try {
            // Record acceptance of Terms of Service
            $termsDoc = LegalDocumentService::getByType(LegalDocumentService::TYPE_TERMS);
            if ($termsDoc && $termsDoc['current_version_id']) {
                LegalDocumentService::recordAcceptance(
                    $userId,
                    $termsDoc['id'],
                    $termsDoc['current_version_id'],
                    LegalDocumentService::ACCEPTANCE_REGISTRATION,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                );
            }

            // Record acceptance of Privacy Policy
            $privacyDoc = LegalDocumentService::getByType(LegalDocumentService::TYPE_PRIVACY);
            if ($privacyDoc && $privacyDoc['current_version_id']) {
                LegalDocumentService::recordAcceptance(
                    $userId,
                    $privacyDoc['id'],
                    $privacyDoc['current_version_id'],
                    LegalDocumentService::ACCEPTANCE_REGISTRATION,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                );
            }
        } catch (\Throwable $e) {
            error_log("[Registration] Legal Document Acceptance Recording Failed: " . $e->getMessage());
        }
    }

    /**
     * Log the registration activity
     */
    private function logActivity(int $userId): void
    {
        try {
            \Nexus\Models\ActivityLog::log($userId, 'register', 'New user registered via API');
        } catch (\Throwable $e) {
            error_log("[Registration] Activity logging failed: " . $e->getMessage());
        }
    }

    /**
     * Subscribe to newsletter (internal + Mailchimp)
     */
    private function subscribeToNewsletter(int $userId, string $email, string $firstName, string $lastName): void
    {
        // Internal newsletter subscription
        try {
            \Nexus\Models\NewsletterSubscriber::createConfirmed(
                $email,
                $firstName,
                $lastName,
                'registration',
                $userId
            );
        } catch (\Throwable $e) {
            error_log("[Registration] Internal newsletter subscription failed: " . $e->getMessage());
        }

        // Mailchimp subscription
        try {
            $mailchimp = new \Nexus\Services\MailchimpService();
            $mailchimp->subscribe($email, $firstName, $lastName);
        } catch (\Throwable $e) {
            error_log("[Registration] Mailchimp subscription failed: " . $e->getMessage());
        }

        // Record marketing consent for GDPR
        try {
            $gdprService = new \Nexus\Services\Enterprise\GdprService(TenantContext::getId());
            $gdprService->recordConsent(
                $userId,
                'marketing_email',
                true,
                'User opted in to newsletter during registration',
                '1.0'
            );
        } catch (\Throwable $e) {
            error_log("[Registration] Marketing consent recording failed: " . $e->getMessage());
        }
    }

    /**
     * Notify admins of new user registration
     */
    private function notifyAdmins(int $userId, string $firstName, string $lastName, string $email, int $tenantId): void
    {
        try {
            $tenantName = TenantContext::get()['name'] ?? 'Project NEXUS';
            $adminLink = TenantContext::getDomain() . '/admin-legacy/users/' . $userId;

            // Get all admins for this tenant
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT email, first_name FROM users
                WHERE tenant_id = ? AND role IN ('admin', 'super_admin') AND status = 'active'
            ");
            $stmt->execute([$tenantId]);
            $admins = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($admins)) {
                return;
            }

            $mailer = new Mailer();

            foreach ($admins as $admin) {
                $html = EmailTemplate::render(
                    "New User Registration",
                    "A new user has registered on $tenantName",
                    "<strong>User:</strong> $firstName $lastName ($email)<br>
                     <strong>Status:</strong> Pending Approval<br><br>
                     Please review and approve this user to grant them access.",
                    "Review User",
                    $adminLink,
                    $tenantName
                );

                $mailer->send(
                    $admin['email'],
                    "New User Registration - $tenantName",
                    $html
                );
            }
        } catch (\Throwable $e) {
            error_log("[Registration] Admin notification failed: " . $e->getMessage());
        }
    }

    /**
     * Award welcome XP to new user (gamification)
     */
    private function awardWelcomeXp(int $userId): void
    {
        try {
            if (TenantContext::hasFeature('gamification')) {
                \Nexus\Services\GamificationService::awardXP($userId, 'registration', 50);
            }
        } catch (\Throwable $e) {
            error_log("[Registration] Welcome XP award failed: " . $e->getMessage());
        }
    }

    /**
     * Send email verification email to the new user
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

            // Build verification URL - use React app URL if available
            $baseUrl = TenantContext::getDomain() ?: 'https://app.project-nexus.ie';
            $verifyUrl = $baseUrl . '/verify-email?token=' . $token . '&user=' . $userId;

            // Send email using standard template
            $siteName = TenantContext::getSetting('site_name', 'Project NEXUS');
            $subject = 'Verify your email address - ' . $siteName;

            $html = EmailTemplate::render(
                'Welcome to ' . htmlspecialchars($siteName) . '!',
                'Please verify your email address to complete registration',
                '<p>Hi ' . htmlspecialchars($firstName) . ',</p>
                <p>Thanks for registering. Please verify your email address by clicking the button below.</p>
                <p>This link will expire in 24 hours.</p>
                <p>If you did not create an account, you can ignore this email.</p>',
                'Verify Email Address',
                $verifyUrl,
                $siteName
            );

            Mailer::send($email, $subject, $html);

        } catch (\Exception $e) {
            // Log but don't fail registration if email fails
            error_log('[Registration] Failed to send verification email: ' . $e->getMessage());
        }
    }
}
