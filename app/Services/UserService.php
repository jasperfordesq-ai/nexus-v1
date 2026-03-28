<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\User;
use App\Services\OnboardingConfigService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Core\TenantContext;

/**
 * UserService — Laravel DI-based service for user/profile operations.
 *
 * Eloquent queries on User are tenant-scoped via HasTenantScope.
 * Raw DB::table() queries are explicitly scoped with tenant_id filters.
 */
class UserService
{
    /** @var array<int, array{code: string, message: string}> */
    private static array $errors = [];

    public function __construct(
        private readonly User $user,
    ) {}

    /**
     * Get a user by ID with related data.
     */
    public static function getById(int $id): ?array
    {
        $user = User::query()
            ->with(['listings', 'badges'])
            ->find($id);

        if (! $user) {
            return null;
        }

        $data = $user->toArray();
        $data['name'] = ($user->profile_type === 'organisation' && $user->organization_name)
            ? $user->organization_name
            : trim($user->first_name . ' ' . $user->last_name);

        return $data;
    }

    /**
     * Get the authenticated user's own profile (for /me endpoint).
     * Matches the legacy UserService::getOwnProfile() response shape.
     */
    public static function getOwnProfile(int $userId): ?array
    {
        return self::getMe($userId);
    }

    /**
     * Get the authenticated user's own profile (alias).
     */
    public static function getMe(int $userId): ?array
    {
        $user = User::query()
            ->with(['listings', 'badges'])
            ->find($userId);

        if (! $user) {
            return null;
        }

        $profile = self::formatProfile($user, true);

        // Add notification preferences
        $profile['notification_preferences'] = self::getNotificationPreferences($userId);

        // Add statistics
        $profile['stats'] = self::getUserStats($userId);

        // Add badges
        $profile['badges'] = self::getUserBadges($userId);

        // Add NexusScore summary
        $profile['nexus_score'] = self::getNexusScoreSummary($userId);

        return $profile;
    }

    /**
     * Get a user's public profile with privacy checks.
     * Matches the legacy UserService::getPublicProfile() response shape.
     */
    public static function getPublicProfile(int $userId, ?int $viewerId = null): ?array
    {
        $user = User::query()
            ->with(['listings', 'badges'])
            ->find($userId);

        if (! $user) {
            return null;
        }

        // Check onboarding visibility gating (admin-configurable)
        // If the viewer is looking at someone else's profile, check if the target
        // meets the tenant's visibility requirements (onboarding complete, avatar, bio).
        if ($viewerId !== $userId) {
            if (!OnboardingConfigService::isProfileVisible(null, $userId)) {
                self::setError('PROFILE_INCOMPLETE', 'This member\'s profile is not yet complete');
                return null;
            }
        }

        // Check privacy settings
        $privacyLevel = $user->privacy_profile ?? 'public';

        if ($privacyLevel !== 'public' && $viewerId !== $userId) {
            if ($privacyLevel === 'members' && ! $viewerId) {
                return null;
            }
            if ($privacyLevel === 'connections') {
                if (! $viewerId || ! self::areConnected($userId, $viewerId)) {
                    return null;
                }
            }
        }

        $profile = self::formatProfile($user, false);

        // Add connection status if viewer is logged in
        if ($viewerId && $viewerId !== $userId) {
            $profile['connection_status'] = self::getConnectionStatus($userId, $viewerId);
        }

        // Add public stats
        $stats = self::getPublicStats($userId);
        $profile['stats'] = $stats;

        // Flatten key stats to root for frontend compatibility
        $profile['total_hours_given'] = $stats['total_hours_given'] ?? 0;
        $profile['total_hours_received'] = $stats['total_hours_received'] ?? 0;
        $profile['groups_count'] = $stats['groups_count'] ?? 0;
        $profile['events_attended'] = $stats['events_attended'] ?? 0;
        $profile['rating'] = $stats['average_rating'] ?? null;

        // Add badges
        $profile['badges'] = self::getUserBadges($userId);

        // Add NexusScore summary
        $profile['nexus_score'] = self::getNexusScoreSummary($userId);

        return $profile;
    }

    /**
     * Update a user profile.
     */
    public static function update(int $id, array $data): User
    {
        /** @var User $user */
        $user = User::query()->findOrFail($id);

        $allowed = [
            'first_name', 'last_name', 'bio', 'tagline', 'location', 'latitude', 'longitude',
            'phone', 'avatar_url', 'organization_name', 'profile_type',
        ];

        $user->fill(collect($data)->only($allowed)->all());
        $user->save();

        return $user->fresh();
    }

    /**
     * Search users by name, email, or location.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public static function search(string $term, int $limit = 20): array
    {
        $limit = min($limit, 100);
        $like = '%' . $term . '%';

        $query = User::query()
            ->where(function (Builder $q) use ($like) {
                $q->where('first_name', 'LIKE', $like)
                  ->orWhere('last_name', 'LIKE', $like)
                  ->orWhere('email', 'LIKE', $like)
                  ->orWhere('organization_name', 'LIKE', $like)
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like]);
            })
            ->where('status', '!=', 'banned')
            ->orderByDesc('id')
            ->limit($limit + 1);

        // Apply onboarding visibility gating
        OnboardingConfigService::applyVisibilityScope($query);

        $items = $query->get();

        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        return [
            'items'    => $items->toArray(),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get profile stats for sidebar widget.
     * Matches legacy UserService::getProfileStats() response shape.
     */
    public static function getProfileStats(int $userId): array
    {
        $tenantId = TenantContext::getId();

        // Count offers
        $offersCount = DB::table('listings')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('type', 'offer')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'active');
            })
            ->count();

        // Count requests
        $requestsCount = DB::table('listings')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('type', 'request')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'active');
            })
            ->count();

        // Hours given
        $givenTotal = (float) DB::table('transactions')
            ->where('sender_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->sum('amount');

        // Hours received
        $receivedTotal = (float) DB::table('transactions')
            ->where('receiver_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->sum('amount');

        // Wallet balance
        $balance = (float) (User::query()->where('id', $userId)->value('balance') ?? 0);

        return [
            'listings_count'  => $offersCount + $requestsCount,
            'given_count'     => round($givenTotal, 1),
            'received_count'  => round($receivedTotal, 1),
            'offers_count'    => $offersCount,
            'requests_count'  => $requestsCount,
            'wallet_balance'  => round($balance, 2),
        ];
    }

    /**
     * Validate profile update data before applying it.
     *
     * @return bool True if valid, false if errors (check getErrors()).
     */
    public static function validateProfileUpdate(array $data): bool
    {
        self::$errors = [];

        // first_name: optional, max 100 chars
        if (isset($data['first_name']) && strlen($data['first_name']) > 100) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'First name must not exceed 100 characters', 'field' => 'first_name'];
        }

        // last_name: optional, max 100 chars
        if (isset($data['last_name']) && strlen($data['last_name']) > 100) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Last name must not exceed 100 characters', 'field' => 'last_name'];
        }

        // bio: optional, max 5000 chars
        if (isset($data['bio']) && strlen($data['bio']) > 5000) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Bio must not exceed 5000 characters', 'field' => 'bio'];
        }

        // profile_type: must be 'individual' or 'organisation'
        if (isset($data['profile_type']) && !in_array($data['profile_type'], ['individual', 'organisation'], true)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Profile type must be individual or organisation', 'field' => 'profile_type'];
        }

        // phone: optional, if provided must be valid (7+ digits after stripping non-digits)
        if (isset($data['phone']) && $data['phone'] !== '') {
            $digits = preg_replace('/[^0-9]/', '', $data['phone']);
            if (strlen($digits) < 7) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Phone number is invalid', 'field' => 'phone'];
            }
        }

        return empty(self::$errors);
    }

    /**
     * Update a user profile (alias for update()).
     *
     * Validates data first via validateProfileUpdate(), then applies changes.
     *
     * @return bool True on success, false on failure (check getErrors()).
     */
    public static function updateProfile(int $userId, array $data): bool
    {
        if (!empty($data) && !self::validateProfileUpdate($data)) {
            return false;
        }

        try {
            self::update($userId, $data);
            return true;
        } catch (\Throwable $e) {
            Log::warning('Profile update failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            self::setError('UPDATE_FAILED', $e->getMessage());
            return false;
        }
    }

    /**
     * Update user password after verifying the current one.
     */
    public static function updatePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        self::$errors = [];
        $user = User::query()->find($userId);

        if (! $user) {
            self::setError('NOT_FOUND', 'User not found');
            return false;
        }

        if (! Hash::check($currentPassword, $user->password_hash)) {
            self::setError('INVALID_PASSWORD', 'Current password is incorrect');
            return false;
        }

        if (strlen($newPassword) < 8) {
            self::setError('WEAK_PASSWORD', 'New password must be at least 8 characters');
            return false;
        }

        $user->password_hash = Hash::make($newPassword);
        $user->save();

        return true;
    }

    /**
     * Upload and update user avatar.
     *
     * @param array $fileArray $_FILES-compatible array
     * @return string|null The new avatar URL, or null on failure (check getErrors()).
     */
    public static function updateAvatar(int $userId, array $fileArray): ?string
    {
        try {
            $avatarUrl = \App\Core\ImageUploader::upload($fileArray, 'profiles', [
                'crop'   => true,
                'width'  => 400,
                'height' => 400,
            ]);

            if (! $avatarUrl) {
                self::setError('UPLOAD_FAILED', 'Avatar upload returned empty result');
                return null;
            }

            $user = User::query()->findOrFail($userId);
            $user->avatar_url = $avatarUrl;
            $user->save();

            return $avatarUrl;
        } catch (\Throwable $e) {
            Log::warning('Avatar upload failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            self::setError('UPLOAD_FAILED', $e->getMessage());
            return null;
        }
    }

    /**
     * Soft-delete a user account: set status to 'deleted' and anonymize PII.
     */
    public static function deleteAccount(int $userId): bool
    {
        $user = User::query()->find($userId);

        if (! $user) {
            self::setError('NOT_FOUND', 'User not found');
            return false;
        }

        try {
            $user->status     = 'deleted';
            $user->email      = 'deleted_' . $userId . '@anonymized.invalid';
            $user->first_name = 'Deleted';
            $user->last_name  = 'User';
            $user->bio        = null;
            $user->tagline    = null;
            $user->phone      = null;
            $user->avatar_url = null;
            $user->location   = null;
            $user->latitude   = null;
            $user->longitude  = null;
            $user->save();

            return true;
        } catch (\Throwable $e) {
            Log::error('Account deletion failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            self::setError('DELETE_FAILED', $e->getMessage());
            return false;
        }
    }

    /**
     * Update user privacy settings.
     */
    public static function updatePrivacy(int $userId, array $privacyData): bool
    {
        self::$errors = [];

        $user = User::query()->find($userId);

        if (! $user) {
            self::setError('NOT_FOUND', 'User not found');
            return false;
        }

        try {
            $allowed = ['privacy_profile', 'privacy_search', 'privacy_contact'];
            $filtered = collect($privacyData)->only($allowed)->all();

            if (empty($filtered)) {
                self::setError('VALIDATION_ERROR', 'No valid privacy fields provided');
                return false;
            }

            // Validate privacy_profile value
            if (isset($filtered['privacy_profile']) && !in_array($filtered['privacy_profile'], ['public', 'members', 'connections'], true)) {
                self::setError('VALIDATION_ERROR', 'Invalid privacy_profile value');
                return false;
            }

            $user->fill($filtered);
            $user->save();

            return true;
        } catch (\Throwable $e) {
            Log::warning('Privacy update failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            self::setError('UPDATE_FAILED', $e->getMessage());
            return false;
        }
    }

    /**
     * Update notification preferences for a user.
     */
    public static function updateNotificationPreferences(int $userId, array $prefs): bool
    {
        try {
            DB::table('user_notification_preferences')->updateOrInsert(
                ['user_id' => $userId],
                $prefs
            );
            return true;
        } catch (\Throwable $e) {
            Log::warning('Notification preferences update failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            self::setError('UPDATE_FAILED', $e->getMessage());
            return false;
        }
    }

    /**
     * Get nearby users using Haversine distance formula.
     *
     * @return array{items: array, has_more: bool}
     */
    public static function getNearby(float $lat, float $lon, array $filters = [], ?int $currentUserId = null): array
    {
        $radiusKm = (float) ($filters['radius_km'] ?? 50);
        $limit    = (int) ($filters['limit'] ?? 20);
        $limit    = min($limit, 100);
        $offset   = max((int) ($filters['offset'] ?? 0), 0);

        // Haversine formula (result in km) — tenant scoping via HasTenantScope on User model
        $haversine = "(
            6371 * acos(
                cos(radians(?)) * cos(radians(latitude)) *
                cos(radians(longitude) - radians(?)) +
                sin(radians(?)) * sin(radians(latitude))
            )
        )";

        $tenantId = TenantContext::getId();

        $query = User::query()
            ->selectRaw("*, $haversine AS distance", [$lat, $lon, $lat])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('status', 'active')
            ->havingRaw('distance <= ?', [$radiusKm])
            ->orderBy('distance');

        if ($currentUserId) {
            $query->where('id', '!=', $currentUserId);
        }

        // Apply onboarding visibility gating
        OnboardingConfigService::applyVisibilityScope($query);

        $items = $query->offset($offset)->limit($limit + 1)->get();

        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        // Collect user IDs for batch badge lookup
        $userIds = $items->pluck('id')->all();
        $badgesByUser = [];
        if (!empty($userIds) && TenantContext::hasFeature('gamification')) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $badgeRows = DB::select(
                "SELECT user_id, badge_key, name, icon
                 FROM user_badges
                 WHERE user_id IN ($placeholders) AND tenant_id = ? AND is_showcased = 1
                 ORDER BY showcase_order ASC",
                array_merge(array_map('intval', $userIds), [$tenantId])
            );
            foreach ($badgeRows as $row) {
                $uid = (int) $row->user_id;
                if (!isset($badgesByUser[$uid])) {
                    $badgesByUser[$uid] = [];
                }
                if (count($badgesByUser[$uid]) < 3) {
                    $def = \App\Services\GamificationService::getBadgeByKey($row->badge_key);
                    $badgesByUser[$uid][] = [
                        'badge_key' => $row->badge_key,
                        'name'      => $def['name'] ?? $row->name ?? $row->badge_key,
                        'icon'      => $def['icon'] ?? $row->icon ?? '',
                    ];
                }
            }
        }

        $result = $items->map(function (User $user) use ($badgesByUser, $tenantId) {
            // Compute rating and hours via subqueries for consistency with index()
            $rating = DB::selectOne(
                "SELECT AVG(rating) as avg_rating FROM reviews WHERE receiver_id = ? AND tenant_id = ?",
                [$user->id, $tenantId]
            );
            $hoursGiven = DB::selectOne(
                "SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE sender_id = ? AND status = 'completed' AND tenant_id = ?",
                [$user->id, $tenantId]
            );
            $hoursReceived = DB::selectOne(
                "SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE receiver_id = ? AND status = 'completed' AND tenant_id = ?",
                [$user->id, $tenantId]
            );

            return [
                'id'                   => $user->id,
                'name'                 => ($user->profile_type === 'organisation' && $user->organization_name)
                                              ? $user->organization_name
                                              : trim($user->first_name . ' ' . $user->last_name),
                'first_name'           => $user->first_name,
                'last_name'            => $user->last_name,
                'avatar'               => $user->avatar_url,
                'tagline'              => $user->getRawOriginal('tagline') ?: ($user->bio ? mb_substr($user->bio, 0, 120) : null),
                'location'             => $user->location,
                'latitude'             => $user->latitude,
                'longitude'            => $user->longitude,
                'created_at'           => $user->created_at?->toISOString(),
                'is_verified'          => (bool) $user->is_verified,
                'xp'                   => (int) ($user->xp ?? 0),
                'level'                => (int) ($user->level ?? 0),
                'rating'               => $rating->avg_rating ? (float) $rating->avg_rating : null,
                'total_hours_given'    => (int) $hoursGiven->total,
                'total_hours_received' => (int) $hoursReceived->total,
                'showcased_badges'     => $badgesByUser[$user->id] ?? [],
                'distance'             => round((float) $user->distance, 1),
            ];
        })->all();

        return [
            'items'    => $result,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get accumulated errors from the last operation.
     *
     * @return array<int, array{code: string, message: string}>
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Record an error for the current operation.
     */
    protected static function setError(string $code, string $message): void
    {
        self::$errors[] = ['code' => $code, 'message' => $message];
    }

    // ================================================================
    // Private helpers
    // ================================================================

    /**
     * Format user model for API response.
     */
    private static function formatProfile(User $user, bool $includePrivate = false): array
    {
        $profile = [
            'id'                => $user->id,
            'name'              => ($user->profile_type === 'organisation' && $user->organization_name)
                                    ? $user->organization_name
                                    : trim($user->first_name . ' ' . $user->last_name),
            'first_name'        => $user->first_name,
            'last_name'         => $user->last_name,
            'avatar_url'        => $user->avatar_url,
            'bio'               => $user->bio,
            'tagline'           => $user->tagline ?? null,
            'location'          => $user->location,
            'latitude'          => $user->latitude,
            'longitude'         => $user->longitude,
            'skills'            => $user->skills ? array_map('trim', explode(',', $user->skills)) : null,
            'profile_type'      => $user->profile_type ?? 'individual',
            'organization_name' => $user->organization_name,
            'created_at'        => $user->created_at?->toISOString(),
            'is_online'         => $user->last_active_at && $user->last_active_at->diffInMinutes(now()) < 5,
            'online_status'     => self::getOnlineStatusText($user->last_active_at),
        ];

        // Gamification fields
        if (isset($user->xp)) {
            $profile['xp'] = (int) $user->xp;
        }
        if (isset($user->level)) {
            $profile['level'] = (int) $user->level;
        }

        // Private fields (only for own profile)
        if ($includePrivate) {
            $profile['tenant_id']               = $user->tenant_id;
            $profile['email']                   = $user->email;
            $profile['phone']                   = $user->phone;
            $profile['status']                  = $user->status ?? 'active';
            $profile['email_verified_at']       = $user->email_verified_at?->toISOString();
            $profile['balance']                 = (float) ($user->balance ?? 0);
            $profile['role']                    = $user->role ?? 'member';
            $profile['is_admin']                = in_array($user->role ?? '', ['admin', 'tenant_admin', 'super_admin'])
                                                    || (bool) $user->is_super_admin
                                                    || (bool) $user->is_tenant_super_admin;
            $profile['is_super_admin']          = (bool) $user->is_super_admin;
            $profile['is_god']                  = (bool) $user->is_god;
            $profile['is_tenant_super_admin']   = (bool) $user->is_tenant_super_admin;
            $profile['is_approved']             = (bool) ($user->is_approved ?? false);
            $profile['privacy_profile']         = $user->privacy_profile ?? 'public';
            $profile['privacy_search']          = (bool) ($user->privacy_search ?? true);
            $profile['privacy_contact']         = (bool) ($user->privacy_contact ?? true);
            $profile['onboarding_completed']    = (bool) ($user->onboarding_completed ?? false);
            $profile['preferred_language']       = $user->preferred_language ?? 'en';
            $profile['has_2fa_enabled']          = (bool) ($user->totp_enabled ?? false);
            $profile['preferred_theme']          = $user->preferred_theme ?? 'system';
            $profile['theme_preferences']        = $user->theme_preferences ? json_decode($user->theme_preferences, true) : null;
        }

        return $profile;
    }

    /**
     * Get user stats (own profile).
     */
    private static function getUserStats(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $avgRating = DB::table('reviews')
            ->where('receiver_id', $userId)
            ->where('tenant_id', $tenantId)
            ->avg('rating');

        return [
            'listings_count'     => DB::table('listings')
                ->where('user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->where(function ($q) { $q->whereNull('status')->orWhere('status', 'active'); })
                ->count(),
            'transactions_count' => DB::table('transactions')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($userId) {
                    $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
                })
                ->count(),
            'connections_count'  => DB::table('connections')
                ->where('tenant_id', $tenantId)
                ->where('status', 'accepted')
                ->where(function ($q) use ($userId) {
                    $q->where('requester_id', $userId)->orWhere('receiver_id', $userId);
                })
                ->count(),
            'reviews_count'      => DB::table('reviews')
                ->where('receiver_id', $userId)
                ->where('tenant_id', $tenantId)
                ->count(),
            'average_rating'     => $avgRating ? round((float) $avgRating, 1) : null,
        ];
    }

    /**
     * Get public stats (includes hours, groups, events).
     */
    private static function getPublicStats(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $stats = self::getUserStats($userId);
        unset($stats['transactions_count']);

        $stats['total_hours_given'] = round((float) DB::table('transactions')
            ->where('sender_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->sum('amount'), 1);

        $stats['total_hours_received'] = round((float) DB::table('transactions')
            ->where('receiver_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->sum('amount'), 1);

        // group_members has no tenant_id column — scope through groups.tenant_id
        $stats['groups_count'] = (int) DB::table('group_members')
            ->join('groups', 'group_members.group_id', '=', 'groups.id')
            ->where('group_members.user_id', $userId)
            ->where('group_members.status', 'active')
            ->where('groups.tenant_id', $tenantId)
            ->count();

        $stats['events_attended'] = (int) DB::table('event_rsvps')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'going')
            ->count();

        return $stats;
    }

    /**
     * Get user badges.
     */
    private static function getUserBadges(int $userId): array
    {
        try {
            $tenantId = TenantContext::getId();

            return DB::table('user_badges')
                ->join('badges', function ($join) {
                    $join->on('user_badges.badge_key', '=', 'badges.badge_key')
                         ->on('user_badges.tenant_id', '=', 'badges.tenant_id');
                })
                ->where('user_badges.user_id', $userId)
                ->where('user_badges.tenant_id', $tenantId)
                ->select('badges.name', 'badges.badge_key', 'badges.icon', 'badges.description', 'user_badges.awarded_at as earned_at')
                ->orderByDesc('user_badges.awarded_at')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Failed to load user badges', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get notification preferences for a user.
     */
    private static function getNotificationPreferences(int $userId): array
    {
        try {
            // user_notification_preferences has no tenant_id column;
            // tenant isolation is enforced by the caller passing a tenant-scoped userId.
            $row = DB::table('user_notification_preferences')
                ->where('user_id', $userId)
                ->first();

            if (! $row) {
                return [];
            }

            return (array) $row;
        } catch (\Throwable $e) {
            Log::warning('Failed to load notification preferences', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Check if two users are connected.
     */
    private static function areConnected(int $userId1, int $userId2): bool
    {
        $tenantId = TenantContext::getId();

        return DB::table('connections')
            ->where('tenant_id', $tenantId)
            ->where('status', 'accepted')
            ->where(function ($q) use ($userId1, $userId2) {
                $q->where(function ($q2) use ($userId1, $userId2) {
                    $q2->where('requester_id', $userId1)->where('receiver_id', $userId2);
                })->orWhere(function ($q2) use ($userId1, $userId2) {
                    $q2->where('requester_id', $userId2)->where('receiver_id', $userId1);
                });
            })
            ->exists();
    }

    /**
     * Get connection status between two users.
     */
    private static function getConnectionStatus(int $targetUserId, int $viewerId): ?string
    {
        $tenantId = TenantContext::getId();

        $connection = DB::table('connections')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($targetUserId, $viewerId) {
                $q->where(function ($q2) use ($targetUserId, $viewerId) {
                    $q2->where('requester_id', $viewerId)->where('receiver_id', $targetUserId);
                })->orWhere(function ($q2) use ($targetUserId, $viewerId) {
                    $q2->where('requester_id', $targetUserId)->where('receiver_id', $viewerId);
                });
            })
            ->first();

        if (! $connection) {
            return null;
        }

        return $connection->status;
    }

    /**
     * Get NexusScore summary for a user (cached, returns null if unavailable).
     *
     * @return array{total_score: float, tier: string, percentile: int}|null
     */
    private static function getNexusScoreSummary(int $userId): ?array
    {
        try {
            $tenantId = TenantContext::getId();

            if (! \Illuminate\Support\Facades\Schema::hasTable('nexus_score_cache')) {
                return null;
            }

            $cached = DB::table('nexus_score_cache')
                ->where('user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->first();

            if (! $cached || empty($cached->total_score)) {
                return null;
            }

            // Determine tier from score (matches NexusScoreService::calculateTier thresholds)
            $score = (float) $cached->total_score;
            $tier = match (true) {
                $score >= 900 => 'Legendary',
                $score >= 800 => 'Elite',
                $score >= 700 => 'Expert',
                $score >= 600 => 'Advanced',
                $score >= 500 => 'Proficient',
                $score >= 400 => 'Intermediate',
                $score >= 300 => 'Developing',
                $score >= 200 => 'Beginner',
                default       => 'Novice',
            };

            return [
                'total_score' => round($score, 1),
                'tier'        => $cached->tier ?? $tier,
                'percentile'  => (int) ($cached->percentile ?? 0),
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed to load NexusScore summary', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get online status text.
     */
    private static function getOnlineStatusText($lastActiveAt): string
    {
        if (! $lastActiveAt) {
            return 'offline';
        }

        $minutes = $lastActiveAt->diffInMinutes(now());

        if ($minutes < 5) {
            return 'online';
        }
        if ($minutes < 60) {
            return 'away';
        }

        return 'offline';
    }
}
