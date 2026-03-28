<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use App\Core\TenantContext;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasTenantScope;

    protected $table = 'users';

    protected $fillable = [
        'tenant_id', 'name', 'first_name', 'last_name', 'email', 'username',
        'password_hash',
        'status', 'avatar_url', 'bio', 'location', 'latitude', 'longitude',
        'phone', 'is_verified', 'is_approved',
        'balance',
        'onboarding_completed',
        'profile_type', 'organization_name', 'totp_enabled',
        'notification_preferences',
        'email_verified_at', 'last_active_at',
    ];

    protected $hidden = [
        'password_hash', 'totp_secret', 'totp_backup_codes',
    ];

    protected $appends = ['avatar', 'tagline'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'balance' => 'decimal:2',
        'is_verified' => 'boolean',
        'is_admin' => 'boolean',
        'is_super_admin' => 'boolean',
        'is_god' => 'boolean',
        'is_tenant_super_admin' => 'boolean',
        'is_approved' => 'boolean',
        'onboarding_completed' => 'boolean',
        'totp_enabled' => 'boolean',
        'email_verified_at' => 'datetime',
        'last_active_at' => 'datetime',
        'notification_preferences' => 'array',
    ];

    /**
     * Accessor: React frontend expects 'avatar' (alias for avatar_url).
     */
    public function getAvatarAttribute(): ?string
    {
        return $this->avatar_url;
    }

    /**
     * Accessor: React frontend expects 'tagline'.
     * Returns the real tagline column if set, otherwise falls back to a
     * truncated bio so every member has something displayed.
     */
    public function getTaglineAttribute(): ?string
    {
        $raw = $this->getRawOriginal('tagline');
        if (!empty($raw)) {
            return $raw;
        }
        // Fallback: first 120 chars of bio
        $bio = $this->getRawOriginal('bio') ?? $this->bio;
        return $bio ? mb_substr($bio, 0, 120) : null;
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function groups(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')
                     ->withPivot('role', 'status')
                     ->withTimestamps();
    }

    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class);
    }

    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(Review::class, 'receiver_id');
    }

    public function reviewsGiven(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function sentTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'sender_id');
    }

    public function receivedTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'receiver_id');
    }

    public function badges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->whereIn('role', ['admin', 'super_admin', 'tenant_admin']);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    /**
     * Find user by ID, returning as array for legacy compatibility.
     */
    public static function findById(int $id, bool $withTenant = true): ?array
    {
        $tenantId = TenantContext::getId();

        $query = DB::table('users')
            ->select([
                'id', 'first_name', 'last_name',
                DB::raw("CASE
                    WHEN profile_type = 'organisation' AND organization_name IS NOT NULL AND organization_name != '' THEN organization_name
                    ELSE CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))
                END as name"),
                'organization_name', 'email', 'role', 'profile_type', 'balance', 'bio', 'tagline',
                'location', 'latitude', 'longitude', 'skills', 'phone', 'avatar_url',
                'created_at', 'tenant_id', 'is_approved',
                'privacy_profile', 'privacy_search', 'privacy_contact',
                'is_super_admin', 'is_god', 'is_tenant_super_admin', 'onboarding_completed',
                DB::raw('COALESCE(xp, 0) as xp'),
                DB::raw('COALESCE(level, 1) as level'),
                'last_active_at', 'last_login_at',
            ])
            ->where('id', $id);

        if ($withTenant && $tenantId) {
            $isSuperAdmin = !empty($_SESSION['is_super_admin']) || !empty($_SESSION['is_tenant_super_admin']);
            if (!$isSuperAdmin) {
                $query->where(function ($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId)
                      ->orWhere('is_super_admin', 1)
                      ->orWhere('is_tenant_super_admin', 1);
                });
            }
        }

        $row = $query->first();
        return $row ? (array) $row : null;
    }

    /**
     * Find user by email (tenant-scoped, allows super admins).
     */
    public static function findByEmail(string $email): ?array
    {
        $tenantId = TenantContext::getId();

        $row = DB::table('users')
            ->where('email', $email)
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                  ->orWhere('is_super_admin', 1)
                  ->orWhere('is_tenant_super_admin', 1);
            })
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * Find user by email globally (no tenant scope).
     */
    public static function findGlobalByEmail(string $email): ?array
    {
        $row = DB::table('users')->where('email', $email)->first();
        return $row ? (array) $row : null;
    }

    /**
     * Update user's last active timestamp.
     */
    public static function updateLastActive(int $userId): void
    {
        try {
            DB::table('users')->where('id', $userId)->where('tenant_id', TenantContext::getId())->update(['last_active_at' => now()]);
        } catch (\Exception $e) {
            // Column may not exist yet - silently fail
        }
    }

    /**
     * Create a user with explicit tenant ID.
     */
    public static function createWithTenant(array $data, int $tenantId): ?int
    {
        $email = $data['email'] ?? '';

        // Check if email already exists
        $existing = DB::table('users')->where('email', $email)->first();
        if ($existing) {
            return null;
        }

        $firstName = $data['first_name'] ?? '';
        $lastName = $data['last_name'] ?? '';
        $password = $data['password'] ?? '';
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $userId = DB::table('users')->insertGetId([
            'tenant_id' => $tenantId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => trim($firstName . ' ' . $lastName),
            'email' => $email,
            'password_hash' => $hash,
            'role' => $data['role'] ?? 'member',
            'location' => $data['location'] ?? null,
            'phone' => $data['phone'] ?? null,
            'profile_type' => $data['profile_type'] ?? 'individual',
            'organization_name' => $data['organization_name'] ?? null,
            'is_approved' => $data['is_approved'] ?? 1,
            'is_tenant_super_admin' => $data['is_tenant_super_admin'] ?? 0,
            'created_at' => now(),
        ]);

        // Seed federation settings
        if ($userId > 0) {
            try {
                DB::statement(
                    "INSERT IGNORE INTO federation_user_settings (
                        user_id, federation_optin, profile_visible_federated,
                        messaging_enabled_federated, transactions_enabled_federated,
                        appear_in_federated_search, show_skills_federated,
                        show_location_federated, service_reach, opted_in_at, created_at
                    ) VALUES (?, 1, 1, 1, 1, 1, 1, 0, 'local_only', NOW(), NOW())",
                    [$userId]
                );
            } catch (\Exception $e) {
                error_log("User::createWithTenant federation seed error: " . $e->getMessage());
            }
        }

        return (int) $userId;
    }

    /**
     * Get notification preferences for a user.
     */
    public static function getNotificationPreferences(int $userId): array
    {
        $defaults = [
            'email_messages' => 1,
            'email_connections' => 1,
            'email_transactions' => 1,
            'email_reviews' => 1,
            'push_enabled' => 1,
            'email_org_payments' => 1,
            'email_org_transfers' => 1,
            'email_org_membership' => 1,
            'email_org_admin' => 1,
            'email_gamification_digest' => 1,
            'email_gamification_milestones' => 1,
        ];

        try {
            $tenantId = TenantContext::getId();
            $row = DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->value('notification_preferences');

            if ($row) {
                return json_decode($row, true) ?: $defaults;
            }
        } catch (\Exception $e) {
            error_log('[User::getNotificationPreferences] Error: ' . $e->getMessage());
        }

        return $defaults;
    }

    /**
     * Update notification preferences for a user.
     */
    public static function updateNotificationPreferences(int $userId, array $prefs): bool
    {
        try {
            $tenantId = TenantContext::getId();
            DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->update(['notification_preferences' => json_encode($prefs)]);
            return true;
        } catch (\Exception $e) {
            error_log('[User::updateNotificationPreferences] Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update admin-controlled fields (role, is_approved, optionally is_super_admin).
     */
    public static function updateAdminFields(int $userId, array $fields): bool
    {
        $tenantId = $fields['tenant_id'] ?? TenantContext::getId();
        $updateData = [];

        if (isset($fields['role'])) {
            $updateData['role'] = $fields['role'];
        }
        if (isset($fields['is_approved'])) {
            $updateData['is_approved'] = $fields['is_approved'];
        }

        // Super admin changes require god privileges
        if (isset($fields['is_super_admin'])) {
            $currentUser = DB::table('users')->where('id', $userId)->value('is_super_admin');
            $currentIsSuperAdmin = !empty($currentUser);

            if ((bool) $fields['is_super_admin'] !== $currentIsSuperAdmin) {
                if (empty($_SESSION['is_god'])) {
                    error_log("SECURITY: Blocked unauthorized is_super_admin change for user {$userId}");
                } else {
                    $updateData['is_super_admin'] = $fields['is_super_admin'] ? 1 : 0;
                }
            }
        }

        if (empty($updateData)) {
            return true;
        }

        $affected = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->update($updateData);

        return $affected > 0;
    }

    /**
     * Check if a user has god-level privileges.
     *
     * When called without arguments, checks the current session.
     * When called with a userId, queries the database.
     */
    public static function isGod(?int $userId = null): bool
    {
        if ($userId === null) {
            return !empty($_SESSION['is_god']);
        }

        $isGod = DB::table('users')
            ->where('id', $userId)
            ->value('is_god');

        return !empty($isGod);
    }

    /**
     * Check if user is a tenant super admin (can access Super Admin Panel).
     */
    public static function isTenantSuperAdmin(int $userId): bool
    {
        $user = DB::table('users')
            ->where('id', $userId)
            ->select(['is_tenant_super_admin', 'is_super_admin'])
            ->first();

        return $user && ($user->is_tenant_super_admin || $user->is_super_admin);
    }

    /**
     * Check if user is the Master super admin (tenant_id = 1 + super admin).
     */
    public static function isMasterSuperAdmin(int $userId): bool
    {
        $user = DB::table('users')
            ->where('id', $userId)
            ->select(['tenant_id', 'is_tenant_super_admin', 'is_super_admin'])
            ->first();

        return $user
            && (int) $user->tenant_id === 1
            && ($user->is_tenant_super_admin || $user->is_super_admin);
    }

    /**
     * Move a user to a different tenant (updates users.tenant_id).
     */
    public static function moveTenant(int $userId, int $newTenantId): bool
    {
        $affected = DB::table('users')
            ->where('id', $userId)
            ->update(['tenant_id' => $newTenantId]);

        return $affected > 0;
    }
}
