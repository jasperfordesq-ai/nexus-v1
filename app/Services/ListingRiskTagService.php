<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ListingRiskTagService — manages risk assessments/tags for listings.
 *
 * Brokers can flag listings with risk levels for safeguarding purposes.
 */
class ListingRiskTagService
{
    public const RISK_LOW = 'low';
    public const RISK_MEDIUM = 'medium';
    public const RISK_HIGH = 'high';
    public const RISK_CRITICAL = 'critical';

    public const RISK_LEVELS = [
        self::RISK_LOW,
        self::RISK_MEDIUM,
        self::RISK_HIGH,
        self::RISK_CRITICAL,
    ];

    public const CATEGORIES = [
        'safeguarding' => 'Safeguarding Concern',
        'financial' => 'Financial Risk',
        'health_safety' => 'Health & Safety',
        'legal' => 'Legal/Regulatory',
        'reputation' => 'Reputational Risk',
        'fraud' => 'Potential Fraud',
        'other' => 'Other',
    ];

    /**
     * Tag a listing with a risk assessment.
     */
    public static function tagListing(int $listingId, array $data, int $brokerId): ?int
    {
        $tenantId = TenantContext::getId();

        $riskLevel = $data['risk_level'] ?? self::RISK_LOW;
        if (!in_array($riskLevel, self::RISK_LEVELS, true)) {
            $riskLevel = self::RISK_LOW;
        }

        $category = $data['risk_category'] ?? null;
        if ($category && !array_key_exists($category, self::CATEGORIES)) {
            $category = 'other';
        }

        $existing = self::getTagForListing($listingId);

        $memberVisibleNotes = $data['member_visible_notes'] ?? null;
        $insuranceRequired = ($data['insurance_required'] ?? false) ? 1 : 0;
        $dbsRequired = ($data['dbs_required'] ?? false) ? 1 : 0;
        $requiresApproval = ($data['requires_approval'] ?? false) ? 1 : 0;

        if ($existing) {
            $oldRiskLevel = $existing['risk_level'];

            DB::table('listing_risk_tags')
                ->where('id', $existing['id'])
                ->update([
                    'risk_level' => $riskLevel,
                    'risk_category' => $category,
                    'risk_notes' => $data['risk_notes'] ?? null,
                    'member_visible_notes' => $memberVisibleNotes,
                    'requires_approval' => $requiresApproval,
                    'insurance_required' => $insuranceRequired,
                    'dbs_required' => $dbsRequired,
                    'tagged_by' => $brokerId,
                    'updated_at' => now(),
                ]);

            // Notify admins if risk level was upgraded to high/critical
            $highLevels = [self::RISK_HIGH, self::RISK_CRITICAL];
            if (in_array($riskLevel, $highLevels, true) && !in_array($oldRiskLevel, $highLevels, true)) {
                self::notifyAdminsOfRiskTag($listingId, $riskLevel, $brokerId);
            }

            return $existing['id'];
        }

        // Create new tag
        $tagId = DB::table('listing_risk_tags')->insertGetId([
            'tenant_id' => $tenantId,
            'listing_id' => $listingId,
            'risk_level' => $riskLevel,
            'risk_category' => $category,
            'risk_notes' => $data['risk_notes'] ?? null,
            'member_visible_notes' => $memberVisibleNotes,
            'requires_approval' => $requiresApproval,
            'insurance_required' => $insuranceRequired,
            'dbs_required' => $dbsRequired,
            'tagged_by' => $brokerId,
            'created_at' => now(),
        ]);

        if (in_array($riskLevel, [self::RISK_HIGH, self::RISK_CRITICAL], true)) {
            self::notifyAdminsOfRiskTag($listingId, $riskLevel, $brokerId);
        }

        return $tagId;
    }

    /**
     * Get risk tag for a listing.
     */
    public static function getTagForListing(int $listingId): ?array
    {
        $tenantId = TenantContext::getId();

        $tag = DB::table('listing_risk_tags as rt')
            ->leftJoin('users as u', 'rt.tagged_by', '=', 'u.id')
            ->where('rt.listing_id', $listingId)
            ->where('rt.tenant_id', $tenantId)
            ->select(['rt.*', 'u.name as tagged_by_name'])
            ->first();

        return $tag ? (array) $tag : null;
    }

    /**
     * Remove risk tag from a listing.
     */
    public static function removeTag(int $listingId, ?int $removedBy = null): bool
    {
        $tenantId = TenantContext::getId();

        $existing = self::getTagForListing($listingId);
        if (!$existing) {
            return false;
        }

        $deleted = DB::table('listing_risk_tags')
            ->where('listing_id', $listingId)
            ->where('tenant_id', $tenantId)
            ->delete();

        return $deleted > 0;
    }

    /**
     * Get all tagged listings, optionally filtered by risk level.
     *
     * @return array{items: array, total: int, pages: int|float}
     */
    public static function getTaggedListings(?string $riskLevel = null, int $page = 1, int $perPage = 20): array
    {
        $tenantId = TenantContext::getId();
        $offset = ($page - 1) * $perPage;

        $query = DB::table('listing_risk_tags as rt')
            ->where('rt.tenant_id', $tenantId);

        if ($riskLevel && in_array($riskLevel, self::RISK_LEVELS, true)) {
            $query->where('rt.risk_level', $riskLevel);
        }

        $total = (clone $query)->count();

        $items = (clone $query)
            ->join('listings as l', 'rt.listing_id', '=', 'l.id')
            ->leftJoin('users as owner', 'l.user_id', '=', 'owner.id')
            ->leftJoin('users as tagger', 'rt.tagged_by', '=', 'tagger.id')
            ->select([
                'rt.*',
                'l.title as listing_title', 'l.type as listing_type', 'l.status as listing_status',
                'owner.name as owner_name', 'owner.avatar_url as owner_avatar',
                'tagger.name as tagged_by_name',
            ])
            ->orderByDesc('rt.created_at')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return [
            'items' => $items,
            'total' => $total,
            'pages' => ceil($total / $perPage),
        ];
    }

    /**
     * Get high-risk listings (high or critical level).
     */
    public static function getHighRiskListings(): array
    {
        $tenantId = TenantContext::getId();

        return DB::table('listing_risk_tags as rt')
            ->join('listings as l', 'rt.listing_id', '=', 'l.id')
            ->leftJoin('users as owner', 'l.user_id', '=', 'owner.id')
            ->where('rt.tenant_id', $tenantId)
            ->whereIn('rt.risk_level', ['high', 'critical'])
            ->select([
                'rt.*',
                'l.title as listing_title', 'l.type as listing_type',
                'owner.name as owner_name',
            ])
            ->orderByRaw("FIELD(rt.risk_level, 'critical', 'high')")
            ->orderByDesc('rt.created_at')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Notify admins when a high/critical risk tag is created.
     */
    private static function notifyAdminsOfRiskTag(int $listingId, string $riskLevel, int $brokerId): void
    {
        try {
            $listing = DB::table('listings as l')
                ->leftJoin('users as u', 'l.user_id', '=', 'u.id')
                ->where('l.id', $listingId)
                ->select(['l.title', 'u.name as owner_name'])
                ->first();

            if ($listing) {
                // Omit the $message argument so NotificationDispatcher::notifyAdmins()
                // renders each admin's bell notification via buildNotificationContent()
                // under that admin's preferred_language via LocaleContext::withLocale().
                // Passing a hardcoded English string here would override that per-locale
                // rendering and send every admin an English notification regardless of
                // their preferred_language setting.
                \App\Services\NotificationDispatcher::notifyAdmins(
                    'listing_risk_tagged',
                    [
                        'listing_id'    => $listingId,
                        'listing_title' => $listing->title ?? 'Unknown',
                        'owner_name'    => $listing->owner_name ?? 'Unknown',
                        'risk_level'    => $riskLevel,
                        'tagged_by'     => $brokerId,
                        'title'         => $listing->title ?? '',
                        'level'         => $riskLevel,
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::warning("Failed to notify admins of risk tag: " . $e->getMessage());
        }
    }
}
