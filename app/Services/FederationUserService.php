<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FederationUserService — manages individual user federation settings,
 * trust scores, and federated user queries.
 */
class FederationUserService
{
    /** Valid service reach values */
    private const VALID_SERVICE_REACH = ['local_only', 'remote_ok', 'travel_ok'];

    /** Default federation settings for a new user */
    private const DEFAULT_SETTINGS = [
        'federation_optin'               => false,
        'profile_visible_federated'      => false,
        'messaging_enabled_federated'    => false,
        'transactions_enabled_federated' => false,
        'appear_in_federated_search'     => false,
        'show_skills_federated'          => false,
        'show_location_federated'        => false,
        'service_reach'                  => 'local_only',
        'travel_radius_km'              => null,
    ];

    public function __construct()
    {
    }

    /**
     * Get federation settings for a user (returns defaults if none saved).
     */
    public static function getUserSettings(int $userId): array
    {
        try {
            $row = DB::table('federation_user_settings')
                ->where('user_id', $userId)
                ->first();

            if (!$row) {
                return array_merge(self::DEFAULT_SETTINGS, ['user_id' => $userId]);
            }

            return [
                'user_id'                        => (int) $row->user_id,
                'federation_optin'               => (bool) $row->federation_optin,
                'profile_visible_federated'      => (bool) $row->profile_visible_federated,
                'messaging_enabled_federated'    => (bool) $row->messaging_enabled_federated,
                'transactions_enabled_federated' => (bool) $row->transactions_enabled_federated,
                'appear_in_federated_search'     => (bool) $row->appear_in_federated_search,
                'show_skills_federated'          => (bool) $row->show_skills_federated,
                'show_location_federated'        => (bool) $row->show_location_federated,
                'service_reach'                  => $row->service_reach ?? 'local_only',
                'travel_radius_km'              => $row->travel_radius_km ?? null,
            ];
        } catch (\Throwable $e) {
            return array_merge(self::DEFAULT_SETTINGS, ['user_id' => $userId]);
        }
    }

    /**
     * Create or update federation settings for a user.
     */
    public static function updateSettings(int $userId, array $settings): bool
    {
        try {
            $data = ['user_id' => $userId, 'updated_at' => now()];

            if (isset($settings['federation_optin'])) {
                $data['federation_optin'] = $settings['federation_optin'] ? 1 : 0;
            }
            if (isset($settings['profile_visible_federated'])) {
                $data['profile_visible_federated'] = $settings['profile_visible_federated'] ? 1 : 0;
            }
            if (isset($settings['messaging_enabled_federated'])) {
                $data['messaging_enabled_federated'] = $settings['messaging_enabled_federated'] ? 1 : 0;
            }
            if (isset($settings['transactions_enabled_federated'])) {
                $data['transactions_enabled_federated'] = $settings['transactions_enabled_federated'] ? 1 : 0;
            }
            if (isset($settings['appear_in_federated_search'])) {
                $data['appear_in_federated_search'] = $settings['appear_in_federated_search'] ? 1 : 0;
            }
            if (isset($settings['show_skills_federated'])) {
                $data['show_skills_federated'] = $settings['show_skills_federated'] ? 1 : 0;
            }
            if (isset($settings['show_location_federated'])) {
                $data['show_location_federated'] = $settings['show_location_federated'] ? 1 : 0;
            }
            if (isset($settings['service_reach'])) {
                $data['service_reach'] = in_array($settings['service_reach'], self::VALID_SERVICE_REACH, true)
                    ? $settings['service_reach']
                    : 'local_only';
            }
            if (isset($settings['travel_radius_km'])) {
                $data['travel_radius_km'] = (int) $settings['travel_radius_km'];
            }

            DB::table('federation_user_settings')->updateOrInsert(
                ['user_id' => $userId],
                $data
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to update federation settings', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check if a user has opted into federation.
     */
    public static function hasOptedIn(int $userId, ?int $tenantId = null): bool
    {
        try {
            $row = DB::table('federation_user_settings')
                ->where('user_id', $userId)
                ->first();

            return $row && (bool) $row->federation_optin;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Opt a user out of federation (disables all federation features).
     */
    public static function optOut(int $userId): bool
    {
        try {
            DB::table('federation_user_settings')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'federation_optin'               => 0,
                    'profile_visible_federated'      => 0,
                    'messaging_enabled_federated'    => 0,
                    'transactions_enabled_federated' => 0,
                    'appear_in_federated_search'     => 0,
                    'show_skills_federated'          => 0,
                    'show_location_federated'        => 0,
                    'updated_at'                     => now(),
                ]
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to opt out of federation', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get users who have opted into federation for a tenant.
     */
    public static function getFederatedUsers(int $tenantId, array $filters = []): array
    {
        try {
            $query = DB::table('federation_user_settings as fus')
                ->join('users as u', 'fus.user_id', '=', 'u.id')
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 'active')
                ->where('fus.federation_optin', 1)
                ->select(['u.id', 'u.first_name', 'u.last_name', 'u.avatar_url', 'u.skills', 'u.location',
                           'fus.service_reach', 'fus.messaging_enabled_federated', 'fus.transactions_enabled_federated']);

            if (!empty($filters['service_reach'])) {
                $query->where('fus.service_reach', $filters['service_reach']);
            }

            return $query->get()->map(fn ($row) => (array) $row)->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Check if federation is available for a user (tenant has federation enabled).
     */
    public static function isFederationAvailableForUser(int $userId): bool
    {
        try {
            $user = DB::table('users')->where('id', $userId)->first();
            if (!$user) {
                return false;
            }

            $control = DB::table('federation_system_control')
                ->where('tenant_id', $user->tenant_id)
                ->first();

            return $control && (bool) ($control->federation_enabled ?? false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Calculate a trust score for a federated user.
     *
     * @return array{score: int|float, level: string, components: array, details: array}
     */
    public static function getTrustScore(int $userId): array
    {
        $empty = [
            'score'      => 0,
            'level'      => 'unknown',
            'components' => ['reviews' => 0, 'transactions' => 0, 'federation' => 0],
            'details'    => [
                'review_count'          => 0,
                'avg_rating'            => 0,
                'transaction_count'     => 0,
                'completion_rate'       => 0,
                'cross_tenant_activity' => 0,
            ],
        ];

        try {
            $tenantId = \App\Core\TenantContext::getId();

            // Review component (up to 40 points)
            $reviewCount = (int) DB::table('reviews')
                ->where('receiver_id', $userId)
                ->where('tenant_id', $tenantId)
                ->count();

            $avgRating = (float) (DB::table('reviews')
                ->where('receiver_id', $userId)
                ->where('tenant_id', $tenantId)
                ->avg('rating') ?? 0);

            $reviewScore = min(40, ($reviewCount * 2) + ($avgRating * 4));

            // Transaction component (up to 40 points)
            $txTotal = (int) DB::table('transactions')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($userId) {
                    $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
                })
                ->count();

            $txCompleted = (int) DB::table('transactions')
                ->where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->where(function ($q) use ($userId) {
                    $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
                })
                ->count();

            $completionRate = $txTotal > 0 ? round(($txCompleted / $txTotal) * 100, 1) : 0;
            $txScore = min(40, ($txCompleted * 2) + ($completionRate > 90 ? 10 : 0));

            // Federation component (up to 20 points)
            $crossTenant = 0;
            try {
                $crossTenant = (int) DB::table('federation_messages')
                    ->where(function ($q) use ($userId) {
                        $q->where('sender_user_id', $userId)->orWhere('receiver_user_id', $userId);
                    })
                    ->count();
            } catch (\Throwable $e) {
                // Table may not exist
            }

            $fedScore = min(20, $crossTenant * 5);

            $totalScore = (int) round($reviewScore + $txScore + $fedScore);
            $totalScore = min(100, max(0, $totalScore));

            // Determine level
            $level = match (true) {
                $totalScore >= 80 => 'excellent',
                $totalScore >= 60 => 'trusted',
                $totalScore >= 40 => 'established',
                $totalScore >= 20 => 'growing',
                $totalScore > 0   => 'new',
                default           => 'new',
            };

            return [
                'score'      => $totalScore,
                'level'      => $level,
                'components' => [
                    'reviews'      => round($reviewScore, 1),
                    'transactions' => round($txScore, 1),
                    'federation'   => round($fedScore, 1),
                ],
                'details' => [
                    'review_count'          => $reviewCount,
                    'avg_rating'            => round($avgRating, 1),
                    'transaction_count'     => $txCompleted,
                    'completion_rate'       => $completionRate,
                    'cross_tenant_activity' => $crossTenant,
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed to calculate trust score', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return $empty;
        }
    }

    /**
     * Get federated reviews for a user from a specific tenant.
     *
     * @return array{reviews: array}
     */
    public static function getFederatedReviews(int $userId, int $tenantId): array
    {
        try {
            $reviews = DB::table('reviews')
                ->where('receiver_id', $userId)
                ->where('tenant_id', $tenantId)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();

            return ['reviews' => $reviews];
        } catch (\Throwable $e) {
            return ['reviews' => []];
        }
    }
}
