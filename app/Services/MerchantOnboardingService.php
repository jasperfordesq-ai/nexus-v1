<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MerchantOnboardingService — Business onboarding wizard for the marketplace (AG48).
 *
 * Manages the 4-step self-serve wizard that guides a user through creating
 * a marketplace seller profile and earns them the 'marktplatz_partner' badge
 * upon completion.
 *
 * All methods are tenant-scoped via the $tenantId parameter.
 */
class MerchantOnboardingService
{
    private const TABLE = 'marketplace_seller_profiles';
    private const BADGE_KEY = 'marktplatz_partner';

    // ─────────────────────────────────────────────────────────────────────────
    //  Guard
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check whether the marketplace seller profiles table exists.
     */
    public static function isAvailable(): bool
    {
        return Schema::hasTable(self::TABLE);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Profile helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fetch the existing seller profile for this user+tenant or insert a blank one.
     *
     * Returns the row as an associative array.
     */
    public static function getOrCreateProfile(int $tenantId, int $userId): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $existing = DB::table(self::TABLE)
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            return (array) $existing;
        }

        DB::table(self::TABLE)->insertOrIgnore([
            'tenant_id'             => $tenantId,
            'user_id'               => $userId,
            'seller_type'           => 'business',
            'joined_marketplace_at' => now(),
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        return (array) DB::table(self::TABLE)
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Step savers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Save Step 1 — Business identity fields.
     *
     * Accepted keys: business_name, display_name, bio, seller_type,
     *                business_registration (optional)
     */
    public static function saveStep1(int $tenantId, int $userId, array $data): array
    {
        self::getOrCreateProfile($tenantId, $userId);

        $allowed = ['business_name', 'display_name', 'bio', 'seller_type', 'business_registration'];
        $update  = array_intersect_key($data, array_flip($allowed));
        $update['updated_at'] = now();

        DB::table(self::TABLE)
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->update($update);

        return self::getOrCreateProfile($tenantId, $userId);
    }

    /**
     * Save Step 2 — Location and opening hours.
     *
     * Accepted keys: business_address (array|string → stored as JSON),
     *                opening_hours (array|string → stored as JSON, optional)
     */
    public static function saveStep2(int $tenantId, int $userId, array $data): array
    {
        self::getOrCreateProfile($tenantId, $userId);

        $update = ['updated_at' => now()];

        if (isset($data['business_address'])) {
            $update['business_address'] = is_string($data['business_address'])
                ? $data['business_address']
                : json_encode($data['business_address']);
        }

        if (array_key_exists('opening_hours', $data)) {
            $update['opening_hours'] = is_string($data['opening_hours'])
                ? $data['opening_hours']
                : json_encode($data['opening_hours']);
        }

        DB::table(self::TABLE)
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->update($update);

        return self::getOrCreateProfile($tenantId, $userId);
    }

    /**
     * Save Step 3 — Profile and cover images (URLs already uploaded).
     */
    public static function saveStep3(int $tenantId, int $userId, string $avatarUrl, ?string $coverImageUrl = null): array
    {
        self::getOrCreateProfile($tenantId, $userId);

        $update = [
            'avatar_url'  => $avatarUrl,
            'updated_at'  => now(),
        ];

        if ($coverImageUrl !== null) {
            $update['cover_image_url'] = $coverImageUrl;
        }

        DB::table(self::TABLE)
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->update($update);

        return self::getOrCreateProfile($tenantId, $userId);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Completion
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Mark the wizard as complete and grant the 'marktplatz_partner' badge.
     *
     * Returns the updated profile row plus a badge_granted boolean.
     */
    public static function completeOnboarding(int $tenantId, int $userId): array
    {
        self::getOrCreateProfile($tenantId, $userId);

        // Mark the wizard as done; also fill joined_marketplace_at if still null.
        DB::table(self::TABLE)
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->update([
                'onboarding_completed_at' => now(),
                'updated_at'             => now(),
            ]);

        // Fill joined_marketplace_at only when it is still NULL.
        DB::table(self::TABLE)
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('joined_marketplace_at')
            ->update(['joined_marketplace_at' => now()]);

        $badgeGranted = self::grantBadge($tenantId, $userId);
        $profile      = self::getOrCreateProfile($tenantId, $userId);

        return array_merge($profile, ['badge_granted' => $badgeGranted]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Status
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return the current onboarding status for a user.
     */
    public static function getOnboardingStatus(int $tenantId, int $userId): array
    {
        if (!self::isAvailable()) {
            return [
                'has_profile'           => false,
                'onboarding_completed'  => false,
                'profile'               => null,
            ];
        }

        $profile = DB::table(self::TABLE)
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();

        if (!$profile) {
            return [
                'has_profile'           => false,
                'onboarding_completed'  => false,
                'profile'               => null,
            ];
        }

        $row = (array) $profile;

        return [
            'has_profile'          => true,
            'onboarding_completed' => !empty($row['onboarding_completed_at']),
            'profile'              => $row,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Internal helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Attempt to grant the marktplatz_partner badge.
     *
     * Checks both `user_badges` and `user_achievements` table names silently.
     * Returns true if a row was inserted, false otherwise.
     */
    private static function grantBadge(int $tenantId, int $userId): bool
    {
        // user_badges uses badge_key column (confirmed from BadgeService)
        if (Schema::hasTable('user_badges')) {
            $inserted = DB::table('user_badges')->insertOrIgnore([
                'tenant_id'  => $tenantId,
                'user_id'    => $userId,
                'badge_key'  => self::BADGE_KEY,
                'awarded_at' => now(),
            ]);
            return $inserted > 0;
        }

        // Fallback: try user_achievements with badge_name column
        if (Schema::hasTable('user_achievements')) {
            try {
                $inserted = DB::table('user_achievements')->insertOrIgnore([
                    'tenant_id'  => $tenantId,
                    'user_id'    => $userId,
                    'badge_name' => self::BADGE_KEY,
                    'awarded_at' => now(),
                    'source'     => 'merchant_onboarding',
                ]);
                return $inserted > 0;
            } catch (\Throwable) {
                // Column set differs — skip silently
            }
        }

        return false;
    }
}
