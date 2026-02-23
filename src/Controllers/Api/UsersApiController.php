<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\UserService;
use Nexus\Services\Enterprise\GdprService;
use Nexus\Core\TenantContext;
use Nexus\Core\ImageUploader;

/**
 * UsersApiController - RESTful API for user profiles
 *
 * Provides profile management endpoints with standardized response format.
 *
 * Endpoints:
 * - GET    /api/v2/users/me              - Get authenticated user's full profile
 * - GET    /api/v2/users/{id}            - Get public profile of a user
 * - PUT    /api/v2/users/me              - Update own profile
 * - PUT    /api/v2/users/me/preferences  - Update notification/privacy preferences
 * - PUT    /api/v2/users/me/avatar       - Update avatar
 * - PUT    /api/v2/users/me/password     - Update password
 * - GET    /api/v2/members/nearby        - Get nearby active members (geospatial)
 *
 * Response Format:
 * Success: { "data": {...}, "meta": {...} }
 * Error:   { "errors": [{ "code": "...", "message": "...", "field": "..." }] }
 */
class UsersApiController extends BaseApiController
{
    /**
     * GET /api/v2/users/me
     *
     * Get the authenticated user's full profile.
     * Includes private data, notification preferences, and statistics.
     *
     * Response: 200 OK with user profile data
     */
    public function me(): void
    {
        $userId = $this->getUserId();

        $profile = UserService::getOwnProfile($userId);

        if (!$profile) {
            $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
        }

        $this->respondWithData($profile);
    }

    /**
     * GET /api/v2/users/{id}
     *
     * Get a user's public profile.
     * Respects privacy settings and shows connection status.
     *
     * Response: 200 OK with public profile data, or 404/403 based on privacy
     */
    public function show(int $id): void
    {
        // Optional auth - affects what data is visible
        $viewerId = $this->getOptionalUserId();

        $profile = UserService::getPublicProfile($id, $viewerId);

        if (!$profile) {
            $errors = UserService::getErrors();

            // Check if it's a privacy restriction
            if (!empty($errors)) {
                $errorCode = $errors[0]['code'] ?? 'NOT_FOUND';
                if ($errorCode === 'FORBIDDEN') {
                    $this->respondWithErrors($errors, 403);
                }
            }

            $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
        }

        $this->respondWithData($profile);
    }

    /**
     * GET /api/v2/users/me/listings
     *
     * Get the authenticated user's own listings.
     * Registered before {id}/listings so "me" is never passed as a string ID.
     */
    public function myListings(): void
    {
        $id = $this->getUserId();
        $this->listings($id);
    }

    /**
     * GET /api/v2/users/{id}/listings
     *
     * Get a user's public listings.
     *
     * Query Parameters:
     * - limit: int (default 20, max 100)
     * - offset: int (default 0)
     * - type: 'offer'|'request' (optional filter)
     *
     * Response: 200 OK with paginated listings
     */
    public function listings(int $id): void
    {
        $this->rateLimit('user_listings', 30, 60);

        // Get filters from query params
        $limit = min((int) ($this->input('limit') ?? 20), 100);
        $offset = (int) ($this->input('offset') ?? 0);
        $type = $this->input('type');

        $filters = [
            'user_id' => $id,
            'limit' => $limit,
        ];

        if ($type && in_array($type, ['offer', 'request'])) {
            $filters['type'] = $type;
        }

        // Use cursor-based pagination from ListingService::getAll()
        $cursor = $this->input('cursor');
        if ($cursor) {
            $filters['cursor'] = $cursor;
        }

        $result = \Nexus\Services\ListingService::getAll($filters);

        $this->respondWithData($result['items'], [
            'cursor' => $result['cursor'],
            'has_more' => $result['has_more'],
            'limit' => $limit,
        ]);
    }

    /**
     * PUT /api/v2/users/me
     *
     * Update the authenticated user's profile.
     *
     * Request Body (JSON):
     * {
     *   "first_name": "string",
     *   "last_name": "string",
     *   "bio": "string",
     *   "location": "string",
     *   "phone": "string",
     *   "profile_type": "individual|organisation",
     *   "organization_name": "string",
     *   "latitude": "float",
     *   "longitude": "float"
     * }
     *
     * Response: 200 OK with updated profile
     */
    public function update(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('profile_update', 10, 60);

        $data = $this->getAllInput();

        $success = UserService::updateProfile($userId, $data);

        if (!$success) {
            $errors = UserService::getErrors();
            $this->respondWithErrors($errors, 422);
        }

        // Return updated profile
        $profile = UserService::getOwnProfile($userId);
        $this->respondWithData($profile);
    }

    /**
     * PUT /api/v2/users/me/preferences
     *
     * Update notification and privacy preferences.
     *
     * Request Body (JSON):
     * {
     *   "privacy": {
     *     "privacy_profile": "public|members|connections",
     *     "privacy_search": true|false,
     *     "privacy_contact": true|false
     *   },
     *   "notifications": {
     *     "email_messages": true|false,
     *     "email_connections": true|false,
     *     "email_transactions": true|false,
     *     "email_reviews": true|false,
     *     "push_enabled": true|false,
     *     ...
     *   }
     * }
     *
     * Response: 200 OK with updated preferences
     */
    /**
     * GET /api/v2/users/me/preferences
     * Get the user's privacy and notification preferences
     */
    public function getPreferences(): void
    {
        $userId = $this->getUserId();

        $profile = UserService::getOwnProfile($userId);
        $this->respondWithData([
            'privacy' => [
                'privacy_profile' => $profile['privacy_profile'] ?? 'public',
                'privacy_search' => (bool) ($profile['privacy_search'] ?? true),
                'privacy_contact' => (bool) ($profile['privacy_contact'] ?? true),
            ],
            'notifications' => $profile['notification_preferences'] ?? [],
        ]);
    }

    public function updatePreferences(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('preferences_update', 20, 60);

        $data = $this->getAllInput();

        // Update privacy settings if provided
        if (isset($data['privacy']) && is_array($data['privacy'])) {
            $success = UserService::updatePrivacy($userId, $data['privacy']);
            if (!$success) {
                $errors = UserService::getErrors();
                $this->respondWithErrors($errors, 422);
            }
        }

        // Update notification preferences if provided
        if (isset($data['notifications']) && is_array($data['notifications'])) {
            UserService::updateNotificationPreferences($userId, $data['notifications']);
        }

        // Return updated profile (includes preferences)
        $profile = UserService::getOwnProfile($userId);
        $this->respondWithData([
            'privacy' => [
                'privacy_profile' => $profile['privacy_profile'] ?? 'public',
                'privacy_search' => $profile['privacy_search'] ?? true,
                'privacy_contact' => $profile['privacy_contact'] ?? true,
            ],
            'notifications' => $profile['notification_preferences'] ?? [],
        ]);
    }

    /**
     * PUT /api/v2/users/me/avatar
     *
     * Upload a new avatar image.
     *
     * Request: multipart/form-data with 'avatar' file
     *
     * Response: 200 OK with new avatar URL
     */
    public function updateAvatar(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('avatar_upload', 5, 60);

        // Check for uploaded file
        if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            $this->respondWithError('VALIDATION_ERROR', 'No avatar file uploaded or upload error', 'avatar', 400);
        }

        $avatarUrl = UserService::updateAvatar($userId, $_FILES['avatar']);

        if (!$avatarUrl) {
            $errors = UserService::getErrors();
            $this->respondWithErrors($errors, 400);
        }

        $this->respondWithData(['avatar_url' => $avatarUrl]);
    }

    /**
     * PUT /api/v2/users/me/password
     *
     * Change the authenticated user's password.
     *
     * Request Body (JSON):
     * {
     *   "current_password": "string (required)",
     *   "new_password": "string (required, min 8 chars)"
     * }
     *
     * Response: 200 OK on success
     */
    public function updatePassword(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('password_change', 3, 60);

        $currentPassword = $this->input('current_password');
        $newPassword = $this->input('new_password');

        if (empty($currentPassword)) {
            $this->respondWithError('VALIDATION_ERROR', 'Current password is required', 'current_password', 400);
        }

        if (empty($newPassword)) {
            $this->respondWithError('VALIDATION_ERROR', 'New password is required', 'new_password', 400);
        }

        $success = UserService::updatePassword($userId, $currentPassword, $newPassword);

        if (!$success) {
            $errors = UserService::getErrors();
            $this->respondWithErrors($errors, 400);
        }

        $this->respondWithData(['message' => 'Password updated successfully']);
    }

    /**
     * DELETE /api/v2/users/me
     * Delete the current user's account
     */
    public function deleteAccount(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('delete_account', 1, 60);

        $success = UserService::deleteAccount($userId);

        if (!$success) {
            $errors = UserService::getErrors();
            $this->respondWithErrors($errors, 400);
        }

        $this->respondWithData(['message' => 'Account deleted successfully']);
    }

    /**
     * GET /api/v2/users/me/notifications
     * Get the user's notification preferences
     */
    public function notificationPreferences(): void
    {
        $userId = $this->getUserId();

        $prefs = \Nexus\Models\User::getNotificationPreferences($userId);

        $this->respondWithData([
            'email_messages' => (bool) ($prefs['email_messages'] ?? true),
            'email_listings' => (bool) ($prefs['email_listings'] ?? true),
            'email_digest' => (bool) ($prefs['email_digest'] ?? false),
            'email_connections' => (bool) ($prefs['email_connections'] ?? true),
            'email_transactions' => (bool) ($prefs['email_transactions'] ?? true),
            'email_reviews' => (bool) ($prefs['email_reviews'] ?? true),
            'email_gamification_digest' => (bool) ($prefs['email_gamification_digest'] ?? true),
            'email_gamification_milestones' => (bool) ($prefs['email_gamification_milestones'] ?? true),
            'email_org_payments' => (bool) ($prefs['email_org_payments'] ?? true),
            'email_org_transfers' => (bool) ($prefs['email_org_transfers'] ?? true),
            'email_org_membership' => (bool) ($prefs['email_org_membership'] ?? true),
            'email_org_admin' => (bool) ($prefs['email_org_admin'] ?? true),
            'push_enabled' => (bool) ($prefs['push_enabled'] ?? true),
        ]);
    }

    /**
     * PUT /api/v2/users/me/notifications
     * Update the user's notification preferences
     */
    public function updateNotificationPreferences(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('update_notifications', 10, 60);

        $data = $this->getAllInput();

        $prefs = [];
        $allowedKeys = [
            'email_messages', 'email_listings', 'email_digest',
            'email_connections', 'email_transactions', 'email_reviews',
            'email_gamification_digest', 'email_gamification_milestones',
            'email_org_payments', 'email_org_transfers', 'email_org_membership', 'email_org_admin',
            'push_enabled',
        ];

        foreach ($allowedKeys as $key) {
            if (isset($data[$key])) {
                $prefs[$key] = (bool) $data[$key];
            }
        }

        if (empty($prefs)) {
            $this->respondWithError('VALIDATION_ERROR', 'No valid preferences provided', null, 400);
        }

        $success = \Nexus\Models\User::updateNotificationPreferences($userId, $prefs);

        if (!$success) {
            $this->respondWithError('UPDATE_FAILED', 'Failed to update preferences', null, 500);
        }

        $this->respondWithData(['message' => 'Notification preferences updated']);
    }

    /**
     * GET /api/v2/users/me/consent
     * Get the user's GDPR consent statuses
     */
    public function getConsent(): void
    {
        $userId = $this->getUserId();

        $gdprService = new GdprService();
        $consents = $gdprService->getUserConsents($userId);

        $this->respondWithData($consents);
    }

    /**
     * PUT /api/v2/users/me/consent
     * Update a single consent preference
     *
     * Request Body (JSON):
     * {
     *   "slug": string (required) - consent type slug,
     *   "given": bool (required)
     * }
     */
    public function updateConsent(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('update_consent', 10, 60);

        $data = $this->getAllInput();
        $slug = trim($data['slug'] ?? '');
        $given = (bool) ($data['given'] ?? false);

        if (empty($slug)) {
            $this->respondWithError('VALIDATION_ERROR', 'Missing consent slug', 'slug', 400);
        }

        try {
            $gdprService = new GdprService();
            $result = $gdprService->updateUserConsent($userId, $slug, $given);

            // Sync newsletter subscription when marketing_email consent changes
            if ($slug === 'marketing_email') {
                $this->syncNewsletterFromConsent($userId, $given);
            }

            $this->respondWithData($result);
        } catch (\Exception $e) {
            $this->respondWithError('CONSENT_UPDATE_FAILED', $e->getMessage(), null, 400);
        }
    }

    /**
     * POST /api/v2/users/me/gdpr-request
     * Create a GDPR data request (access, erasure, portability, rectification, restriction, objection)
     *
     * Request Body (JSON):
     * {
     *   "type": string (required) - GDPR request type,
     *   "notes": string (optional)
     * }
     */
    public function createGdprRequest(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('gdpr_request', 5, 3600);

        $data = $this->getAllInput();
        $type = trim($data['type'] ?? '');
        $notes = $data['notes'] ?? null;

        $validTypes = ['access', 'erasure', 'portability', 'rectification', 'restriction', 'objection'];

        if (!in_array($type, $validTypes, true)) {
            $this->respondWithError(
                'VALIDATION_ERROR',
                'Invalid request type. Valid types: ' . implode(', ', $validTypes),
                'type',
                400
            );
        }

        try {
            $gdprService = new GdprService();
            $result = $gdprService->createRequest($userId, $type, [
                'notes' => $notes,
                'metadata' => [
                    'source' => 'user_settings',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'requested_via' => $this->isStatelessRequest() ? 'api' : 'web',
                ],
            ]);

            $this->respondWithData([
                'request_id' => $result['id'],
                'type' => $type,
                'status' => 'pending',
                'message' => 'Your request has been submitted and will be processed within 30 days.',
            ], null, 201);
        } catch (\RuntimeException $e) {
            // Duplicate request
            $this->respondWithError('DUPLICATE_REQUEST', $e->getMessage(), null, 409);
        } catch (\Exception $e) {
            $this->respondWithError('REQUEST_FAILED', $e->getMessage(), null, 500);
        }
    }

    /**
     * Sync newsletter subscription based on marketing_email consent
     */
    private function syncNewsletterFromConsent(int $userId, bool $subscribed): void
    {
        try {
            $user = \Nexus\Models\User::findById($userId);
            if (!$user) return;

            $email = $user['email'];
            $existing = \Nexus\Models\NewsletterSubscriber::findByEmail($email);

            if ($subscribed) {
                if (!$existing) {
                    \Nexus\Models\NewsletterSubscriber::createConfirmed(
                        $email,
                        $user['first_name'],
                        $user['last_name'],
                        'gdpr_settings',
                        $userId
                    );
                } elseif ($existing['status'] === 'unsubscribed') {
                    \Nexus\Models\NewsletterSubscriber::update($existing['id'], ['status' => 'active']);
                }

                try {
                    $mailchimp = new \Nexus\Services\MailchimpService();
                    $mailchimp->subscribe($email, $user['first_name'], $user['last_name']);
                } catch (\Throwable $e) {
                    error_log("Mailchimp Subscribe Failed: " . $e->getMessage());
                }
            } else {
                if ($existing && $existing['status'] === 'active') {
                    \Nexus\Models\NewsletterSubscriber::update($existing['id'], ['status' => 'unsubscribed']);

                    try {
                        $mailchimp = new \Nexus\Services\MailchimpService();
                        $mailchimp->unsubscribe($email);
                    } catch (\Throwable $e) {
                        error_log("Mailchimp Unsubscribe Failed: " . $e->getMessage());
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log("Newsletter Sync From Consent Failed: " . $e->getMessage());
        }
    }

    /**
     * GET /api/v2/users/me/sessions
     * List user's active sessions with device/browser info
     */
    public function sessions(): void
    {
        $userId = $this->getUserId();
        $tenantId = TenantContext::getId();

        $db = \Nexus\Core\Database::getInstance();
        $stmt = $db->prepare(
            "SELECT id, ip_address, user_agent, device_type, last_activity, created_at
             FROM sessions
             WHERE user_id = ? AND tenant_id = ?
               AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY last_activity DESC
             LIMIT 20"
        );
        $stmt->execute([$userId, $tenantId]);
        $rows = $stmt->fetchAll();

        $currentSessionId = session_id() ?: '';

        $sessions = array_map(function ($row) use ($currentSessionId) {
            $ua = $row['user_agent'] ?? '';
            return [
                'id' => $row['id'],
                'device' => $this->parseDevice($ua, $row['device_type'] ?? 'unknown'),
                'browser' => $this->parseBrowser($ua),
                'ip_address' => $row['ip_address'] ?? '',
                'last_active' => $row['last_activity'] ?? $row['created_at'],
                'is_current' => $row['id'] === $currentSessionId,
            ];
        }, $rows);

        $this->respondWithData($sessions);
    }

    /**
     * Parse browser name and version from user agent string
     */
    private function parseBrowser(string $ua): string
    {
        if (empty($ua)) return 'Unknown';

        if (preg_match('/Edg[e\/]?([\d.]+)/i', $ua, $m)) return 'Edge ' . intval($m[1]);
        if (preg_match('/OPR\/([\d.]+)/i', $ua, $m)) return 'Opera ' . intval($m[1]);
        if (preg_match('/Chrome\/([\d.]+)/i', $ua, $m)) return 'Chrome ' . intval($m[1]);
        if (preg_match('/Firefox\/([\d.]+)/i', $ua, $m)) return 'Firefox ' . intval($m[1]);
        if (preg_match('/Safari\/([\d.]+)/i', $ua) && preg_match('/Version\/([\d.]+)/i', $ua, $m)) {
            return 'Safari ' . intval($m[1]);
        }
        return 'Unknown';
    }

    /**
     * Parse device/OS from user agent string
     */
    private function parseDevice(string $ua, string $deviceType): string
    {
        if (empty($ua)) return ucfirst($deviceType);

        if (preg_match('/iPhone/i', $ua)) return 'iPhone';
        if (preg_match('/iPad/i', $ua)) return 'iPad';
        if (preg_match('/Android/i', $ua)) return 'Android';
        if (preg_match('/Windows NT 10/i', $ua)) return 'Windows';
        if (preg_match('/Macintosh/i', $ua)) return 'Mac';
        if (preg_match('/Linux/i', $ua)) return 'Linux';

        return ucfirst($deviceType);
    }

    /**
     * GET /api/v2/members/nearby
     *
     * Get active members near a geographic point.
     * Only includes users who have latitude/longitude set (respects privacy).
     *
     * Query Parameters:
     * - lat: float (required) - Latitude
     * - lon: float (required) - Longitude
     * - radius_km: float (default 25) - Search radius in kilometers
     * - per_page: int (default 20, max 100)
     *
     * Response: 200 OK with data array and search meta
     */
    public function nearby(): void
    {
        // Optional auth - if logged in, exclude current user from results
        $currentUserId = $this->getOptionalUserId();

        $lat = $this->query('lat');
        $lon = $this->query('lon');

        if ($lat === null || $lon === null) {
            $this->respondWithError('VALIDATION_ERROR', 'Latitude and longitude are required', null, 400);
        }

        $lat = (float)$lat;
        $lon = (float)$lon;

        // Validate coordinates
        if ($lat < -90 || $lat > 90) {
            $this->respondWithError('VALIDATION_ERROR', 'Latitude must be between -90 and 90', 'lat', 400);
        }
        if ($lon < -180 || $lon > 180) {
            $this->respondWithError('VALIDATION_ERROR', 'Longitude must be between -180 and 180', 'lon', 400);
        }

        $filters = [
            'radius_km' => (float)$this->query('radius_km', '25'),
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        $result = UserService::getNearby($lat, $lon, $filters, $currentUserId);

        $this->respondWithData($result['items'], [
            'search' => [
                'type' => 'nearby',
                'lat' => $lat,
                'lon' => $lon,
                'radius_km' => $filters['radius_km'],
            ],
            'per_page' => $filters['limit'],
            'has_more' => $result['has_more'],
        ]);
    }

    /**
     * PUT /api/v2/users/me/theme
     *
     * Update the user's theme preference.
     *
     * Request Body (JSON):
     * {
     *   "theme": "light"|"dark"|"system"
     * }
     *
     * Response: 200 OK with success message
     */
    public function updateTheme(): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('theme_update', 30, 60);

        $data = $this->getAllInput();
        $theme = $data['theme'] ?? null;

        $validThemes = ['light', 'dark', 'system'];
        if (!$theme || !in_array($theme, $validThemes)) {
            $this->respondWithError(
                'VALIDATION_ERROR',
                'Invalid theme. Must be one of: light, dark, system',
                'theme',
                400
            );
        }

        $success = \Nexus\Core\Database::query(
            "UPDATE users SET preferred_theme = ? WHERE id = ?",
            [$theme, $userId]
        );

        if (!$success) {
            $this->respondWithError('UPDATE_FAILED', 'Failed to update theme preference', null, 500);
        }

        $this->respondWithData([
            'message' => 'Theme preference updated',
            'theme' => $theme
        ]);
    }
}
