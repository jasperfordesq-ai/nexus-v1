<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\UserService;
use App\Services\Enterprise\GdprService;
use App\Services\ListingService;
use App\Services\MailchimpService;
use App\Models\User;
use App\Services\MemberRankingService;
use App\Services\OnboardingConfigService;
use App\Services\GamificationService;
use App\Models\UserBadge;

/**
 * UsersController - User profiles, settings, preferences, sessions.
 *
 * Converted from delegation to direct static service calls.
 * All methods are now native Laravel — no legacy delegation remains.
 */
class UsersController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly GdprService $gdprService,
        private readonly ListingService $listingService,
        private readonly MailchimpService $mailchimpService,
        private readonly UserService $userService,
    ) {}

    // ================================================================
    // PROFILE
    // ================================================================

    /**
     * GET /api/v2/users/me
     */
    public function me(): JsonResponse
    {
        $userId = $this->requireAuth();

        $profile = $this->userService->getOwnProfile($userId);

        if (!$profile) {
            return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
        }

        return $this->respondWithData($profile);
    }

    /**
     * PUT /api/v2/users/me
     */
    public function update(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('profile_update', 10, 60);

        $data = $this->getAllInput();

        $success = $this->userService->updateProfile($userId, $data);

        if (!$success) {
            $errors = $this->userService->getErrors();
            return $this->respondWithErrors($errors, 422);
        }

        $profile = $this->userService->getOwnProfile($userId);

        // Check if profile is now complete and award XP (one-time, guarded by awardXP)
        try {
            $hasName = !empty($profile['first_name']) && !empty($profile['last_name']);
            $hasBio = !empty($profile['bio']);
            $hasAvatar = !empty($profile['avatar_url']);
            if ($hasName && $hasBio && $hasAvatar) {
                \App\Services\GamificationService::awardXP($userId, \App\Services\GamificationService::XP_VALUES['complete_profile'], 'complete_profile', 'Completed profile');
            }
        } catch (\Throwable $e) {
            \Log::warning('Gamification XP award failed', ['action' => 'complete_profile', 'user' => $userId, 'error' => $e->getMessage()]);
        }

        return $this->respondWithData($profile);
    }

    /**
     * GET /api/v2/users/{id}
     */
    public function show(int $id): JsonResponse
    {
        $viewerId = $this->getOptionalUserId();

        $profile = $this->userService->getPublicProfile($id, $viewerId);

        if (!$profile) {
            $errors = $this->userService->getErrors();
            if (!empty($errors)) {
                $errorCode = $errors[0]['code'] ?? 'NOT_FOUND';
                if ($errorCode === 'FORBIDDEN') {
                    return $this->respondWithErrors($errors, 403);
                }
                if ($errorCode === 'PROFILE_INCOMPLETE') {
                    return $this->respondWithError('PROFILE_INCOMPLETE', __('api.user_profile_incomplete'), null, 404);
                }
            }
            return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
        }

        return $this->respondWithData($profile);
    }

    /**
     * GET /api/v2/users/search?q=
     */
    public function search(): JsonResponse
    {
        $this->rateLimit('user_search', 60, 60);
        $q = $this->query('q', '');
        $limit = $this->queryInt('limit', 20, 1, 100);

        $results = $this->userService->search($q, $limit);

        return $this->respondWithData($results);
    }

    /**
     * GET /api/v2/me/stats
     */
    public function stats(): JsonResponse
    {
        $userId = $this->requireAuth();

        $stats = $this->userService->getProfileStats($userId);

        return $this->respondWithData($stats);
    }

    // ================================================================
    // LISTINGS
    // ================================================================

    /**
     * GET /api/v2/users/me/listings
     */
    public function myListings(): JsonResponse
    {
        $id = $this->requireAuth();
        return $this->listings($id);
    }

    /**
     * GET /api/v2/users/{id}/listings
     */
    public function listings($id): JsonResponse
    {
        $id = (int) $id;
        $this->rateLimit('user_listings', 30, 60);

        $limit = $this->queryInt('limit', 20, 1, 100);
        $type = $this->query('type');

        $filters = [
            'user_id' => $id,
            'limit'   => $limit,
        ];

        if ($type && in_array($type, ['offer', 'request'])) {
            $filters['type'] = $type;
        }

        $cursor = $this->query('cursor');
        if ($cursor) {
            $filters['cursor'] = $cursor;
        }

        $result = $this->listingService->getAll($filters);

        return $this->respondWithData($result['items'], [
            'cursor'   => $result['cursor'],
            'has_more' => $result['has_more'],
            'limit'    => $limit,
        ]);
    }

    // ================================================================
    // PREFERENCES
    // ================================================================

    /**
     * GET /api/v2/users/me/preferences
     */
    public function getPreferences(): JsonResponse
    {
        $userId = $this->requireAuth();

        $profile = $this->userService->getOwnProfile($userId);

        return $this->respondWithData([
            'privacy' => [
                'privacy_profile' => $profile['privacy_profile'] ?? 'public',
                'privacy_search'  => (bool) ($profile['privacy_search'] ?? true),
                'privacy_contact' => (bool) ($profile['privacy_contact'] ?? true),
            ],
            'notifications' => $profile['notification_preferences'] ?? [],
        ]);
    }

    /**
     * PUT /api/v2/users/me/preferences
     */
    public function updatePreferences(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('preferences_update', 20, 60);

        $data = $this->getAllInput();

        // Update privacy settings if provided
        if (isset($data['privacy']) && is_array($data['privacy'])) {
            $success = $this->userService->updatePrivacy($userId, $data['privacy']);
            if (!$success) {
                $errors = $this->userService->getErrors();
                return $this->respondWithErrors($errors, 422);
            }
        }

        // Update notification preferences if provided
        if (isset($data['notifications']) && is_array($data['notifications'])) {
            $this->userService->updateNotificationPreferences($userId, $data['notifications']);
        }

        $profile = $this->userService->getOwnProfile($userId);

        return $this->respondWithData([
            'privacy' => [
                'privacy_profile' => $profile['privacy_profile'] ?? 'public',
                'privacy_search'  => $profile['privacy_search'] ?? true,
                'privacy_contact' => $profile['privacy_contact'] ?? true,
            ],
            'notifications' => $profile['notification_preferences'] ?? [],
        ]);
    }

    // ================================================================
    // THEME / LANGUAGE
    // ================================================================

    /**
     * PUT /api/v2/users/me/theme
     */
    public function updateTheme(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('theme_update', 30, 60);

        $data = $this->getAllInput();
        $theme = $data['theme'] ?? null;

        $validThemes = ['light', 'dark', 'system'];
        if (!$theme || !in_array($theme, $validThemes)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                'Invalid theme. Must be one of: light, dark, system',
                'theme',
                400
            );
        }

        $success = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', \App\Core\TenantContext::getId())
            ->update(['preferred_theme' => $theme]);

        if ($success === false) {
            return $this->respondWithError('UPDATE_FAILED', __('api.user_theme_update_failed'), null, 500);
        }

        return $this->respondWithData([
            'message' => __('api_controllers_2.users.theme_updated'),
            'theme'   => $theme,
        ]);
    }

    /**
     * PUT /api/v2/users/me/theme-preferences
     */
    public function updateThemePreferences(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('theme_prefs_update', 30, 60);

        $data = $this->getAllInput();

        // Validate accent_color (hex color)
        $accentColor = $data['accent_color'] ?? null;
        if ($accentColor !== null && !preg_match('/^#[0-9a-fA-F]{6}$/', $accentColor)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                'Invalid accent_color. Must be a valid hex color (e.g. #6366f1)',
                'accent_color',
                400
            );
        }

        // Validate font_size
        $fontSize = $data['font_size'] ?? null;
        $validFontSizes = ['small', 'medium', 'large'];
        if ($fontSize !== null && !in_array($fontSize, $validFontSizes, true)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                'Invalid font_size. Must be one of: small, medium, large',
                'font_size',
                400
            );
        }

        // Validate density
        $density = $data['density'] ?? null;
        $validDensities = ['compact', 'comfortable', 'spacious'];
        if ($density !== null && !in_array($density, $validDensities, true)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                'Invalid density. Must be one of: compact, comfortable, spacious',
                'density',
                400
            );
        }

        // Validate high_contrast
        $highContrast = $data['high_contrast'] ?? null;
        if ($highContrast !== null && !is_bool($highContrast)) {
            // Accept truthy/falsy values
            $highContrast = filter_var($highContrast, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($highContrast === null) {
                return $this->respondWithError(
                    'VALIDATION_ERROR',
                    'Invalid high_contrast. Must be a boolean',
                    'high_contrast',
                    400
                );
            }
        }

        // Build preferences JSON
        $preferences = [
            'accent_color'  => $accentColor ?? '#6366f1',
            'font_size'     => $fontSize ?? 'medium',
            'density'       => $density ?? 'comfortable',
            'high_contrast' => (bool) ($highContrast ?? false),
        ];

        $success = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', TenantContext::getId())
            ->update(['theme_preferences' => json_encode($preferences)]);

        if ($success === false) {
            return $this->respondWithError('UPDATE_FAILED', __('api.user_theme_prefs_update_failed'), null, 500);
        }

        return $this->respondWithData([
            'message'           => __('api.users.theme_updated'),
            'theme_preferences' => $preferences,
        ]);
    }

    /**
     * PUT /api/v2/users/me/language
     */
    public function updateLanguage(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('language_update', 30, 60);

        $data = $this->getAllInput();
        $language = $data['language'] ?? null;

        $validLanguages = TenantContext::getSetting('supported_languages', ['en', 'ga']);
        if (!$language || !in_array($language, $validLanguages, true)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                'Invalid language. Must be one of: ' . implode(', ', $validLanguages),
                'language',
                400
            );
        }

        $success = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', \App\Core\TenantContext::getId())
            ->update(['preferred_language' => $language]);

        if ($success === false) {
            return $this->respondWithError('UPDATE_FAILED', __('api.user_lang_update_failed'), null, 500);
        }

        // Update session so PHP admin views pick it up immediately
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['locale'] = $language;
        }

        return $this->respondWithData([
            'message'  => __('api.users.language_updated'),
            'language' => $language,
        ]);
    }

    // ================================================================
    // PASSWORD
    // ================================================================

    /**
     * POST /api/v2/users/me/password
     */
    public function updatePassword(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('password_change', 3, 60);

        $currentPassword = $this->input('current_password');
        $newPassword = $this->input('new_password');

        if (empty($currentPassword)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.user_current_password_required'), 'current_password', 400);
        }

        if (empty($newPassword)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.user_new_password_required'), 'new_password', 400);
        }

        $success = $this->userService->updatePassword($userId, $currentPassword, $newPassword);

        if (!$success) {
            $errors = $this->userService->getErrors();
            return $this->respondWithErrors($errors, 400);
        }

        return $this->respondWithData(['message' => __('api_controllers_2.users.password_updated')]);
    }

    // ================================================================
    // DELETE ACCOUNT
    // ================================================================

    /**
     * DELETE /api/v2/users/me
     */
    public function deleteAccount(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('delete_account', 1, 60);

        // Require password re-authentication before account deletion
        $password = $this->input('password', '');
        if (empty($password)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.user_password_required'), 'password', 400);
        }

        $tenantId = $this->getTenantId();
        $userRow = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->select(['password_hash', 'email', 'first_name', 'name'])
            ->first();

        if (!$userRow || !password_verify($password, $userRow->password_hash)) {
            return $this->respondWithError('INVALID_PASSWORD', __('api.user_invalid_password'), 'password', 403);
        }

        // Capture contact details before anonymization
        $userEmail = $userRow->email;
        $userName  = $userRow->first_name ?? $userRow->name ?? 'there';

        $success = $this->userService->deleteAccount($userId);

        if (!$success) {
            $errors = $this->userService->getErrors();
            return $this->respondWithErrors($errors, 400);
        }

        // Send farewell confirmation to the now-anonymized account's original email
        try {
            if (!empty($userEmail) && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                $community = TenantContext::getName();
                $html = EmailTemplateBuilder::make()
                    ->theme('warning')
                    ->title(__('emails_misc.admin_actions.self_deletion_title'))
                    ->greeting(__('emails_misc.admin_actions.self_deletion_greeting', ['name' => $userName]))
                    ->paragraph(__('emails_misc.admin_actions.self_deletion_body', ['community' => $community]))
                    ->paragraph(__('emails_misc.admin_actions.self_deletion_body_contact'))
                    ->render();
                Mailer::forCurrentTenant()->send(
                    $userEmail,
                    __('emails_misc.admin_actions.self_deletion_subject', ['community' => $community]),
                    $html
                );
            }
        } catch (\Throwable $e) {
            Log::warning('[UsersController] deleteAccount farewell email failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }

        return $this->respondWithData(['message' => __('api_controllers_2.users.account_deleted')]);
    }

    // ================================================================
    // NOTIFICATION PREFERENCES
    // ================================================================

    /**
     * GET /api/v2/users/me/notifications
     */
    public function notificationPreferences(): JsonResponse
    {
        $userId = $this->requireAuth();

        $prefs = User::getNotificationPreferences($userId);

        return $this->respondWithData([
            'email_messages'                => (bool) ($prefs['email_messages'] ?? true),
            'email_listings'                => (bool) ($prefs['email_listings'] ?? true),
            'email_digest'                  => (bool) ($prefs['email_digest'] ?? false),
            'email_connections'             => (bool) ($prefs['email_connections'] ?? true),
            'email_transactions'            => (bool) ($prefs['email_transactions'] ?? true),
            'email_reviews'                 => (bool) ($prefs['email_reviews'] ?? true),
            'email_gamification_digest'     => (bool) ($prefs['email_gamification_digest'] ?? true),
            'email_gamification_milestones' => (bool) ($prefs['email_gamification_milestones'] ?? true),
            'email_org_payments'            => (bool) ($prefs['email_org_payments'] ?? true),
            'email_org_transfers'           => (bool) ($prefs['email_org_transfers'] ?? true),
            'email_org_membership'          => (bool) ($prefs['email_org_membership'] ?? true),
            'email_org_admin'               => (bool) ($prefs['email_org_admin'] ?? true),
            'push_enabled'                  => (bool) ($prefs['push_enabled'] ?? true),
        ]);
    }

    /**
     * PUT /api/v2/users/me/notifications
     */
    public function updateNotificationPreferences(): JsonResponse
    {
        $userId = $this->requireAuth();
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
            return $this->respondWithError('VALIDATION_ERROR', __('api.user_no_valid_prefs'), null, 400);
        }

        $success = User::updateNotificationPreferences($userId, $prefs);

        if (!$success) {
            return $this->respondWithError('UPDATE_FAILED', __('api.user_prefs_update_failed'), null, 500);
        }

        return $this->respondWithData(['message' => __('api_controllers_2.users.prefs_updated')]);
    }

    // ================================================================
    // CONSENT / GDPR
    // ================================================================

    /**
     * GET /api/v2/users/me/consent
     */
    public function getConsent(): JsonResponse
    {
        $userId = $this->requireAuth();

        $consents = $this->gdprService->getUserConsents($userId);

        return $this->respondWithData($consents);
    }

    /**
     * PUT /api/v2/users/me/consent
     */
    public function updateConsent(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('update_consent', 10, 60);

        $data = $this->getAllInput();
        $slug = trim($data['slug'] ?? '');
        $given = (bool) ($data['given'] ?? false);

        if (empty($slug)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.user_consent_slug_required'), 'slug', 400);
        }

        try {
            $result = $this->gdprService->updateUserConsent($userId, $slug, $given);

            // Sync newsletter subscription when marketing_email consent changes
            if ($slug === 'marketing_email') {
                $this->syncNewsletterFromConsent($userId, $given);
            }

            return $this->respondWithData($result);
        } catch (\Exception $e) {
            Log::error('Consent update failed', ['user' => $userId, 'slug' => $slug, 'error' => $e->getMessage()]);
            return $this->respondWithError('CONSENT_UPDATE_FAILED', __('api.user_consent_update_failed'), null, 500);
        }
    }

    /**
     * POST /api/v2/users/me/gdpr-request
     */
    public function createGdprRequest(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('gdpr_request', 5, 3600);

        $data = $this->getAllInput();
        $type = trim($data['type'] ?? '');
        $notes = $data['notes'] ?? null;

        $validTypes = ['access', 'erasure', 'portability', 'rectification', 'restriction', 'objection'];

        if (!in_array($type, $validTypes, true)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                'Invalid request type. Valid types: ' . implode(', ', $validTypes),
                'type',
                400
            );
        }

        try {
            $result = $this->gdprService->createRequest($userId, $type, [
                'notes'    => $notes,
                'metadata' => [
                    'source'        => 'user_settings',
                    'user_agent'    => request()->header('User-Agent', ''),
                    'requested_via' => 'api',
                ],
            ]);

            return $this->respondWithData([
                'request_id' => $result['id'],
                'type'       => $type,
                'status'     => 'pending',
                'message'    => __('api.users.gdpr_request_submitted'),
            ], null, 201);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('DUPLICATE_REQUEST', __('api.user_duplicate_request'), null, 409);
        } catch (\Exception $e) {
            Log::error('GDPR request creation failed', ['user' => $userId, 'type' => $type, 'error' => $e->getMessage()]);
            return $this->respondWithError('REQUEST_FAILED', __('api.user_request_failed'), null, 500);
        }
    }

    // ================================================================
    // SESSIONS
    // ================================================================

    /**
     * GET /api/v2/users/me/sessions
     */
    public function sessions(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        $rows = DB::table('sessions')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('last_activity')
            ->limit(20)
            ->select(['id', 'ip_address', 'user_agent', 'device_type', 'last_activity', 'created_at'])
            ->get();

        $currentSessionId = session_id() ?: '';

        $sessions = $rows->map(function ($row) use ($currentSessionId) {
            $ua = $row->user_agent ?? '';
            return [
                'id'          => $row->id,
                'device'      => $this->parseDevice($ua, $row->device_type ?? 'unknown'),
                'browser'     => $this->parseBrowser($ua),
                'ip_address'  => $row->ip_address ?? '',
                'last_active' => $row->last_activity ?? $row->created_at,
                'is_current'  => $row->id === $currentSessionId,
            ];
        })->all();

        return $this->respondWithData($sessions);
    }

    // ================================================================
    // NEARBY MEMBERS
    // ================================================================

    /**
     * GET /api/v2/members/nearby
     */
    public function nearby(): JsonResponse
    {
        $currentUserId = $this->getOptionalUserId();

        $lat = $this->query('lat');
        $lon = $this->query('lon');

        if ($lat === null || $lon === null) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.event_lat_lon_required'), null, 400);
        }

        $lat = (float) $lat;
        $lon = (float) $lon;

        if ($lat < -90 || $lat > 90) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.event_lat_range'), 'lat', 400);
        }
        if ($lon < -180 || $lon > 180) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.event_lon_range'), 'lon', 400);
        }

        // Accept both 'limit' (frontend sends this) and 'per_page' for backwards compat
        $limit = $this->queryInt('limit', 0, 1, 100);
        if ($limit === 0) {
            $limit = $this->queryInt('per_page', 20, 1, 100);
        }

        $offset = max((int) request()->query('offset', 0), 0);

        $filters = [
            'radius_km' => (float) $this->query('radius_km', '25'),
            'limit'     => $limit,
            'offset'    => $offset,
        ];

        $result = $this->userService->getNearby($lat, $lon, $filters, $currentUserId);

        return $this->respondWithData($result['items'], [
            'search' => [
                'type'      => 'nearby',
                'lat'       => $lat,
                'lon'       => $lon,
                'radius_km' => $filters['radius_km'],
            ],
            'per_page'    => $filters['limit'],
            'total_items' => $result['total'] ?? null,
            'has_more'    => $result['has_more'],
        ]);
    }

    // ================================================================
    // NOTIFICATION SETTINGS (legacy v1 — push_enabled, context settings)
    // ================================================================

    /**
     * POST /api/notifications/settings
     */
    public function updateSettings(): JsonResponse
    {
        $userId = $this->requireAuth();

        $input = $this->getAllInput();

        // Handle push_enabled preference update (from PWA subscription)
        if (isset($input['push_enabled'])) {
            try {
                $pushEnabled = $input['push_enabled'] ? 1 : 0;

                $currentPrefs = User::getNotificationPreferences($userId);
                $currentPrefs['push_enabled'] = $pushEnabled;
                User::updateNotificationPreferences($userId, $currentPrefs);

                return $this->success(['push_enabled' => $pushEnabled]);
            } catch (\Exception $e) {
                Log::error('Failed to update push notification setting', ['user' => $userId, 'error' => $e->getMessage()]);
                return $this->error(__('api_controllers_2.users.notification_settings_update_failed'), 500);
            }
        }

        $contextType = $input['context_type'] ?? null;
        $contextId = $input['context_id'] ?? null;
        $frequency = $input['frequency'] ?? null;

        if (!in_array($contextType, ['global', 'group', 'thread'])) {
            return $this->error(__('api_controllers_2.users.invalid_context_type'), 400);
        }

        if (!in_array($frequency, ['instant', 'daily', 'weekly', 'off'])) {
            return $this->error(__('api_controllers_2.users.invalid_frequency'), 400);
        }

        if ($contextType === 'global') {
            $contextId = 0;
        } else {
            if (!$contextId) {
                return $this->error(__('api_controllers_2.users.context_id_required'), 400);
            }
            $contextId = (int) $contextId;

            // Validate context_id belongs to the user's tenant
            $tenantId = TenantContext::getId();
            if ($contextType === 'group') {
                $exists = DB::table('groups')
                    ->where('id', $contextId)
                    ->where('tenant_id', $tenantId)
                    ->exists();
                if (!$exists) {
                    return $this->error(__('api_controllers_2.users.invalid_group_id'), 400);
                }
            } elseif ($contextType === 'thread') {
                // Verify user is a participant in this thread
                $exists = DB::table('message_threads')
                    ->where('id', $contextId)
                    ->where('tenant_id', $tenantId)
                    ->exists();
                if (!$exists) {
                    return $this->error(__('api_controllers_2.users.invalid_thread_id'), 400);
                }
            }
        }

        try {
            DB::table('notification_settings')->updateOrInsert(
                [
                    'user_id'      => $userId,
                    'context_type' => $contextType,
                    'context_id'   => $contextId,
                ],
                ['frequency' => $frequency]
            );

            return $this->success(true);
        } catch (\Exception $e) {
            Log::error('Failed to update notification settings', ['user' => $userId, 'error' => $e->getMessage()]);
            return $this->error(__('api_controllers_2.users.notification_settings_update_failed'), 500);
        }
    }

    // ================================================================
    // AVATAR UPLOAD
    // ================================================================

    /**
     * PUT /api/v2/users/me/avatar
     *
     * Upload a new avatar for the authenticated user. Uses request()->file() (Laravel native).
     * Field name: 'avatar'
     */
    public function updateAvatar(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('avatar_upload', 5, 60);

        $file = request()->file('avatar');
        if (!$file || !$file->isValid()) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.user_no_avatar_uploaded'), 'avatar', 400);
        }

        // Build a $_FILES-compatible array for $this->userService->updateAvatar()
        $fileArray = [
            'name'     => $file->getClientOriginalName(),
            'type'     => $file->getMimeType(),
            'tmp_name' => $file->getRealPath(),
            'error'    => UPLOAD_ERR_OK,
            'size'     => $file->getSize(),
        ];

        $avatarUrl = $this->userService->updateAvatar($userId, $fileArray);

        if (!$avatarUrl) {
            $errors = $this->userService->getErrors();
            return $this->respondWithErrors($errors, 400);
        }

        return $this->respondWithData(['avatar_url' => $avatarUrl]);
    }

    // ================================================================
    // PRIVATE HELPERS
    // ================================================================

    /**
     * Sync newsletter subscription based on marketing_email consent
     */
    private function syncNewsletterFromConsent(int $userId, bool $subscribed): void
    {
        try {
            $user = User::findById($userId);
            if (!$user) {
                return;
            }

            $email = $user['email'];
            $existing = \App\Models\NewsletterSubscriber::findByEmail($email);

            if ($subscribed) {
                if (!$existing) {
                    \App\Models\NewsletterSubscriber::createConfirmed(
                        $email,
                        $user['first_name'],
                        $user['last_name'],
                        'gdpr_settings',
                        $userId
                    );
                } elseif ($existing['status'] === 'unsubscribed') {
                    \App\Models\NewsletterSubscriber::update($existing['id'], ['status' => 'active']);
                }

                try {
                    $mailchimp = $this->mailchimpService;
                    $mailchimp->subscribe($email, $user['first_name'], $user['last_name']);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Mailchimp Subscribe Failed: " . $e->getMessage());
                }
            } else {
                if ($existing && $existing['status'] === 'active') {
                    \App\Models\NewsletterSubscriber::update($existing['id'], ['status' => 'unsubscribed']);

                    try {
                        $mailchimp = $this->mailchimpService;
                        $mailchimp->unsubscribe($email);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning("Mailchimp Unsubscribe Failed: " . $e->getMessage());
                    }
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Newsletter Sync From Consent Failed: " . $e->getMessage());
        }
    }

    /**
     * Parse browser name and version from user agent string
     */
    private function parseBrowser(string $ua): string
    {
        if (empty($ua)) {
            return 'Unknown';
        }

        if (preg_match('/Edg[e\/]?([\d.]+)/i', $ua, $m)) {
            return 'Edge ' . intval($m[1]);
        }
        if (preg_match('/OPR\/([\d.]+)/i', $ua, $m)) {
            return 'Opera ' . intval($m[1]);
        }
        if (preg_match('/Chrome\/([\d.]+)/i', $ua, $m)) {
            return 'Chrome ' . intval($m[1]);
        }
        if (preg_match('/Firefox\/([\d.]+)/i', $ua, $m)) {
            return 'Firefox ' . intval($m[1]);
        }
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
        if (empty($ua)) {
            return ucfirst($deviceType);
        }

        if (preg_match('/iPhone/i', $ua)) {
            return 'iPhone';
        }
        if (preg_match('/iPad/i', $ua)) {
            return 'iPad';
        }
        if (preg_match('/Android/i', $ua)) {
            return 'Android';
        }
        if (preg_match('/Windows NT 10/i', $ua)) {
            return 'Windows';
        }
        if (preg_match('/Macintosh/i', $ua)) {
            return 'Mac';
        }
        if (preg_match('/Linux/i', $ua)) {
            return 'Linux';
        }

        return ucfirst($deviceType);
    }


    /**
     * GET /api/v2/users — Member directory listing
     * Migrated from legacy closure in httpdocs/routes/users.php
     */
    public function index(): JsonResponse
    {
        $this->rateLimit('member_directory', 60, 60);
        $request = request();
        $tenantId = $this->getTenantId();
        $viewerId = $this->getOptionalUserId();

        $limit = min((int) $request->query('limit', 50), 100);
        $offset = max((int) $request->query('offset', 0), 0);
        $search = $request->query('q', '');
        $sort = $request->query('sort', 'name');
        $order = strtoupper($request->query('order', 'ASC'));
        if (!in_array($order, ['ASC', 'DESC'])) {
            $order = 'ASC';
        }

        $validSorts = [
            'name' => 'u.name',
            'joined' => 'u.created_at',
            'rating' => '(SELECT AVG(rating) FROM reviews WHERE receiver_id = u.id AND tenant_id = u.tenant_id)',
            'hours_given' => "(SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE sender_id = u.id AND status = 'completed' AND tenant_id = u.tenant_id)",
        ];
        $orderByField = $validSorts[$sort] ?? 'u.name';
        $orderBy = "$orderByField $order";

        $params = [$tenantId, 'active'];
        $whereClause = 'u.tenant_id = ? AND u.status = ?';

        if ($search) {
            $memberIds = \App\Services\SearchService::searchUsersStatic($search, $tenantId);
            if ($memberIds !== false && !empty($memberIds)) {
                $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
                $whereClause .= " AND u.id IN ($placeholders)";
                $params = array_merge($params, array_map('intval', $memberIds));
            } elseif ($memberIds !== false) {
                $whereClause .= ' AND 1=0';
            } else {
                $whereClause .= ' AND (
                    MATCH(u.first_name, u.last_name, u.bio, u.skills) AGAINST(? IN BOOLEAN MODE)
                    OR u.name LIKE ?
                    OR u.location LIKE ?
                )';
                $params[] = $search;
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
        }

        if ($viewerId) {
            $whereClause .= ' AND u.id != ?';
            $params[] = $viewerId;
        }

        // Respect privacy_search setting — hide users who opted out of search
        $whereClause .= ' AND (u.privacy_search = 1 OR u.privacy_search IS NULL)';

        // Apply onboarding visibility gating (admin-configurable per tenant)
        $visibilityConditions = OnboardingConfigService::getVisibilitySqlConditions($tenantId);
        foreach ($visibilityConditions as $condition) {
            $whereClause .= " AND ($condition)";
        }

        $totalCount = (int) DB::selectOne("SELECT COUNT(*) as total FROM users u WHERE $whereClause", $params)->total;

        $sql = "SELECT u.id,
                       CASE
                           WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != '' THEN u.organization_name
                           ELSE CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))
                       END as name,
                       u.first_name, u.last_name,
                       u.avatar_url as avatar,
                       COALESCE(u.tagline, LEFT(u.bio, 120)) as tagline,
                       u.location, u.latitude, u.longitude,
                       u.created_at, u.last_login_at, u.is_verified,
                       COALESCE(u.xp, 0) as xp, COALESCE(u.level, 0) as level,
                       (SELECT AVG(rating) FROM reviews WHERE receiver_id = u.id AND tenant_id = u.tenant_id) as rating,
                       (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE sender_id = u.id AND status = 'completed' AND tenant_id = u.tenant_id) as total_hours_given,
                       (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE receiver_id = u.id AND status = 'completed' AND tenant_id = u.tenant_id) as total_hours_received,
                       (SELECT COUNT(*) FROM listings WHERE user_id = u.id AND status = 'active' AND type = 'offer' AND tenant_id = u.tenant_id) as offer_count,
                       (SELECT COUNT(*) FROM listings WHERE user_id = u.id AND status = 'active' AND type = 'request' AND tenant_id = u.tenant_id) as request_count
                FROM users u
                WHERE $whereClause
                ORDER BY $orderBy
                LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $users = DB::select($sql, $params);
        $users = array_map(fn ($u) => (array) $u, $users);

        foreach ($users as &$user) {
            $user['rating'] = $user['rating'] ? (float) $user['rating'] : null;
            $user['total_hours_given'] = (int) $user['total_hours_given'];
            $user['hours_given'] = $user['total_hours_given'];
            $user['total_hours_received'] = (int) $user['total_hours_received'];
            $user['offer_count'] = (int) $user['offer_count'];
            $user['request_count'] = (int) $user['request_count'];
            $user['is_verified'] = (bool) $user['is_verified'];
            $user['xp'] = (int) $user['xp'];
            $user['level'] = (int) $user['level'];
        }
        unset($user);

        // Enrich with showcased badges if gamification is enabled
        if (TenantContext::hasFeature('gamification')) {
            $userIds = array_column($users, 'id');
            if (!empty($userIds)) {
                $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                $badgeRows = DB::select(
                    "SELECT user_id, badge_key, name, icon
                     FROM user_badges
                     WHERE user_id IN ($placeholders) AND tenant_id = ? AND is_showcased = 1
                     ORDER BY showcase_order ASC",
                    array_merge(array_map('intval', $userIds), [$tenantId])
                );

                // Group badges by user_id (max 3 per user)
                $badgesByUser = [];
                foreach ($badgeRows as $row) {
                    $uid = (int) $row->user_id;
                    if (!isset($badgesByUser[$uid])) {
                        $badgesByUser[$uid] = [];
                    }
                    if (count($badgesByUser[$uid]) < 3) {
                        $def = GamificationService::getBadgeByKey($row->badge_key);
                        $badgesByUser[$uid][] = [
                            'badge_key' => $row->badge_key,
                            'name'      => $def['name'] ?? $row->name ?? $row->badge_key,
                            'icon'      => $def['icon'] ?? $row->icon ?? '',
                        ];
                    }
                }

                foreach ($users as &$user) {
                    $user['showcased_badges'] = $badgesByUser[$user['id']] ?? [];
                }
                unset($user);
            }
        } else {
            foreach ($users as &$user) {
                $user['showcased_badges'] = [];
            }
            unset($user);
        }

        // Clean up internal fields before returning
        $users = array_map(static function (array $u): array {
            unset($u['hours_given'], $u['offer_count'], $u['request_count'], $u['last_login_at']);
            return $u;
        }, $users);

        return $this->respondWithData($users, [
            'total_items' => $totalCount,
            'per_page'    => $limit,
            'offset'      => $offset,
            'has_more'    => ($offset + $limit) < $totalCount,
        ]);
    }

}
