<?php

namespace Nexus\Controllers\Api;

use Nexus\Services\UserService;
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
}
