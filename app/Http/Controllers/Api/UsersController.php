<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Services\UserService;
use App\Services\Enterprise\GdprService;
use App\Services\ListingService;
use App\Services\MailchimpService;
use App\Core\TenantContext;
use App\Models\User;

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
            return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
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
            }
            return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
        }

        return $this->respondWithData($profile);
    }

    /**
     * GET /api/v2/users/search?q=
     */
    public function search(): JsonResponse
    {
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
        $tenantId = TenantContext::getId();

        $stats = $this->userService->getProfileStats($userId, $tenantId);

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
            return $this->respondWithError('UPDATE_FAILED', 'Failed to update theme preference', null, 500);
        }

        return $this->respondWithData([
            'message' => 'Theme preference updated',
            'theme'   => $theme,
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
            return $this->respondWithError('UPDATE_FAILED', 'Failed to update language preference', null, 500);
        }

        // Update session so PHP admin views pick it up immediately
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['locale'] = $language;
        }

        return $this->respondWithData([
            'message'  => 'Language preference updated',
            'language' => $language,
        ]);
    }

    // ================================================================
    // PASSWORD
    // ================================================================

    /**
     * PUT /api/v2/users/me/password
     */
    public function updatePassword(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('password_change', 3, 60);

        $currentPassword = $this->input('current_password');
        $newPassword = $this->input('new_password');

        if (empty($currentPassword)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Current password is required', 'current_password', 400);
        }

        if (empty($newPassword)) {
            return $this->respondWithError('VALIDATION_ERROR', 'New password is required', 'new_password', 400);
        }

        $success = $this->userService->updatePassword($userId, $currentPassword, $newPassword);

        if (!$success) {
            $errors = $this->userService->getErrors();
            return $this->respondWithErrors($errors, 400);
        }

        return $this->respondWithData(['message' => 'Password updated successfully']);
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

        $success = $this->userService->deleteAccount($userId);

        if (!$success) {
            $errors = $this->userService->getErrors();
            return $this->respondWithErrors($errors, 400);
        }

        return $this->respondWithData(['message' => 'Account deleted successfully']);
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
            return $this->respondWithError('VALIDATION_ERROR', 'No valid preferences provided', null, 400);
        }

        $success = User::updateNotificationPreferences($userId, $prefs);

        if (!$success) {
            return $this->respondWithError('UPDATE_FAILED', 'Failed to update preferences', null, 500);
        }

        return $this->respondWithData(['message' => 'Notification preferences updated']);
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
            return $this->respondWithError('VALIDATION_ERROR', 'Missing consent slug', 'slug', 400);
        }

        try {
            $result = $this->gdprService->updateUserConsent($userId, $slug, $given);

            // Sync newsletter subscription when marketing_email consent changes
            if ($slug === 'marketing_email') {
                $this->syncNewsletterFromConsent($userId, $given);
            }

            return $this->respondWithData($result);
        } catch (\Exception $e) {
            return $this->respondWithError('CONSENT_UPDATE_FAILED', $e->getMessage(), null, 400);
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
                'message'    => 'Your request has been submitted and will be processed within 30 days.',
            ], null, 201);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('DUPLICATE_REQUEST', $e->getMessage(), null, 409);
        } catch (\Exception $e) {
            return $this->respondWithError('REQUEST_FAILED', $e->getMessage(), null, 500);
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
            return $this->respondWithError('VALIDATION_ERROR', 'Latitude and longitude are required', null, 400);
        }

        $lat = (float) $lat;
        $lon = (float) $lon;

        if ($lat < -90 || $lat > 90) {
            return $this->respondWithError('VALIDATION_ERROR', 'Latitude must be between -90 and 90', 'lat', 400);
        }
        if ($lon < -180 || $lon > 180) {
            return $this->respondWithError('VALIDATION_ERROR', 'Longitude must be between -180 and 180', 'lon', 400);
        }

        $filters = [
            'radius_km' => (float) $this->query('radius_km', '25'),
            'limit'     => $this->queryInt('per_page', 20, 1, 100),
        ];

        $result = $this->userService->getNearby($lat, $lon, $filters, $currentUserId);

        return $this->respondWithData($result['items'], [
            'search' => [
                'type'      => 'nearby',
                'lat'       => $lat,
                'lon'       => $lon,
                'radius_km' => $filters['radius_km'],
            ],
            'per_page' => $filters['limit'],
            'has_more' => $result['has_more'],
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
                return $this->error($e->getMessage(), 500);
            }
        }

        $contextType = $input['context_type'] ?? null;
        $contextId = $input['context_id'] ?? null;
        $frequency = $input['frequency'] ?? null;

        if (!in_array($contextType, ['global', 'group', 'thread'])) {
            return $this->error('Invalid context type', 400);
        }

        if (!in_array($frequency, ['instant', 'daily', 'weekly', 'off'])) {
            return $this->error('Invalid frequency', 400);
        }

        if ($contextType === 'global') {
            $contextId = 0;
        } else {
            if (!$contextId) {
                return $this->error('Context ID required', 400);
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
            return $this->error($e->getMessage(), 500);
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
            return $this->respondWithError('VALIDATION_ERROR', 'No avatar file uploaded or upload error', 'avatar', 400);
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
                    error_log("Mailchimp Subscribe Failed: " . $e->getMessage());
                }
            } else {
                if ($existing && $existing['status'] === 'active') {
                    \App\Models\NewsletterSubscriber::update($existing['id'], ['status' => 'unsubscribed']);

                    try {
                        $mailchimp = $this->mailchimpService;
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

        $totalCount = (int) DB::selectOne("SELECT COUNT(*) as total FROM users u WHERE $whereClause", $params)->total;

        $sql = "SELECT u.id, u.name, u.first_name, u.last_name,
                       u.avatar_url as avatar, u.bio as tagline,
                       u.location, u.latitude, u.longitude,
                       u.created_at, u.last_login_at, u.is_verified,
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
        }
        unset($user);

        if (!$request->has('sort') && \App\Services\MemberRankingService::isEnabled() && !empty($users)) {
            $users = \App\Services\MemberRankingService::rankMembers($users, $viewerId, ['search' => $search]);
        }

        $users = array_map(static function (array $u): array {
            unset($u['_community_rank'], $u['_score_breakdown'], $u['hours_given'], $u['offer_count'], $u['request_count'], $u['last_login_at']);
            return $u;
        }, $users);

        return response()->json([
            'data' => $users,
            'meta' => [
                'total_items' => $totalCount,
                'per_page' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $totalCount,
            ],
        ]);
    }

}
