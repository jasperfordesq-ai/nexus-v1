<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * ListingRiskTagService
 *
 * Manages risk assessments/tags for listings.
 * Brokers can flag listings with risk levels for safeguarding purposes.
 *
 * Risk Levels:
 * - low: Minor concern, monitor only
 * - medium: Moderate concern, copy messages to broker
 * - high: Significant concern, require broker approval for exchanges
 * - critical: Severe concern, immediate action required
 */
class ListingRiskTagService
{
    /**
     * Risk level constants
     */
    public const RISK_LOW = 'low';
    public const RISK_MEDIUM = 'medium';
    public const RISK_HIGH = 'high';
    public const RISK_CRITICAL = 'critical';

    /**
     * Valid risk levels
     */
    public const RISK_LEVELS = [
        self::RISK_LOW,
        self::RISK_MEDIUM,
        self::RISK_HIGH,
        self::RISK_CRITICAL,
    ];

    /**
     * Risk categories
     */
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
     * Tag a listing with a risk assessment
     *
     * @param int $listingId Listing ID
     * @param array $data Tag data (risk_level, risk_category, risk_notes, requires_approval)
     * @param int $brokerId User ID of broker tagging the listing
     * @return int|null Tag ID or null on failure
     */
    public static function tagListing(int $listingId, array $data, int $brokerId): ?int
    {
        $tenantId = TenantContext::getId();

        // Validate risk level
        $riskLevel = $data['risk_level'] ?? self::RISK_LOW;
        if (!in_array($riskLevel, self::RISK_LEVELS, true)) {
            $riskLevel = self::RISK_LOW;
        }

        // Validate category
        $category = $data['risk_category'] ?? null;
        if ($category && !array_key_exists($category, self::CATEGORIES)) {
            $category = 'other';
        }

        // Check if listing already has a tag
        $existing = self::getTagForListing($listingId);

        if ($existing) {
            // Update existing tag
            Database::query(
                "UPDATE listing_risk_tags
                 SET risk_level = ?, risk_category = ?, risk_notes = ?,
                     requires_approval = ?, tagged_by = ?, updated_at = NOW()
                 WHERE id = ?",
                [
                    $riskLevel,
                    $category,
                    $data['risk_notes'] ?? null,
                    ($data['requires_approval'] ?? false) ? 1 : 0,
                    $brokerId,
                    $existing['id'],
                ]
            );

            // Log update
            AuditLogService::log('listing_risk_tag_updated', [
                'listing_id' => $listingId,
                'old_risk_level' => $existing['risk_level'],
                'new_risk_level' => $riskLevel,
                'broker_id' => $brokerId,
            ]);

            return $existing['id'];
        }

        // Create new tag
        Database::query(
            "INSERT INTO listing_risk_tags
             (tenant_id, listing_id, risk_level, risk_category, risk_notes, requires_approval, tagged_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $tenantId,
                $listingId,
                $riskLevel,
                $category,
                $data['risk_notes'] ?? null,
                ($data['requires_approval'] ?? false) ? 1 : 0,
                $brokerId,
            ]
        );

        $tagId = Database::lastInsertId();

        // Log creation
        AuditLogService::log('listing_risk_tag_created', [
            'listing_id' => $listingId,
            'tag_id' => $tagId,
            'risk_level' => $riskLevel,
            'broker_id' => $brokerId,
        ]);

        // Notify admins if high/critical
        if (in_array($riskLevel, [self::RISK_HIGH, self::RISK_CRITICAL], true)) {
            self::notifyAdminsOfRiskTag($listingId, $riskLevel, $brokerId);
        }

        return $tagId;
    }

    /**
     * Get risk tag for a listing
     *
     * @param int $listingId Listing ID
     * @return array|null Tag data or null if not tagged
     */
    public static function getTagForListing(int $listingId): ?array
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT rt.*, u.name as tagged_by_name
             FROM listing_risk_tags rt
             LEFT JOIN users u ON rt.tagged_by = u.id
             WHERE rt.listing_id = ? AND rt.tenant_id = ?",
            [$listingId, $tenantId]
        );

        $tag = $stmt->fetch();
        return $tag ?: null;
    }

    /**
     * Remove risk tag from a listing
     *
     * @param int $listingId Listing ID
     * @param int|null $removedBy User ID who removed the tag
     * @return bool Success
     */
    public static function removeTag(int $listingId, ?int $removedBy = null): bool
    {
        $tenantId = TenantContext::getId();

        $existing = self::getTagForListing($listingId);
        if (!$existing) {
            return false;
        }

        $result = Database::query(
            "DELETE FROM listing_risk_tags WHERE listing_id = ? AND tenant_id = ?",
            [$listingId, $tenantId]
        );

        if ($result) {
            AuditLogService::log('listing_risk_tag_removed', [
                'listing_id' => $listingId,
                'previous_risk_level' => $existing['risk_level'],
                'removed_by' => $removedBy,
            ]);
        }

        return $result !== false;
    }

    /**
     * Get all tagged listings, optionally filtered by risk level
     *
     * @param string|null $riskLevel Filter by risk level (null = all)
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array ['items' => [...], 'total' => int, 'pages' => int]
     */
    public static function getTaggedListings(?string $riskLevel = null, int $page = 1, int $perPage = 20): array
    {
        $tenantId = TenantContext::getId();
        $offset = ($page - 1) * $perPage;

        $whereClause = "rt.tenant_id = ?";
        $params = [$tenantId];

        if ($riskLevel && in_array($riskLevel, self::RISK_LEVELS, true)) {
            $whereClause .= " AND rt.risk_level = ?";
            $params[] = $riskLevel;
        }

        // Get total count
        $countStmt = Database::query(
            "SELECT COUNT(*) as total FROM listing_risk_tags rt WHERE $whereClause",
            $params
        );
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        // Get items
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = Database::query(
            "SELECT rt.*,
                    l.title as listing_title, l.type as listing_type, l.status as listing_status,
                    owner.name as owner_name, owner.avatar_url as owner_avatar,
                    tagger.name as tagged_by_name
             FROM listing_risk_tags rt
             JOIN listings l ON rt.listing_id = l.id
             LEFT JOIN users owner ON l.user_id = owner.id
             LEFT JOIN users tagger ON rt.tagged_by = tagger.id
             WHERE $whereClause
             ORDER BY rt.created_at DESC
             LIMIT ? OFFSET ?",
            $params
        );

        return [
            'items' => $stmt->fetchAll() ?: [],
            'total' => $total,
            'pages' => ceil($total / $perPage),
        ];
    }

    /**
     * Get high-risk listings (high or critical level)
     *
     * @return array Array of tagged listings
     */
    public static function getHighRiskListings(): array
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT rt.*,
                    l.title as listing_title, l.type as listing_type,
                    owner.name as owner_name
             FROM listing_risk_tags rt
             JOIN listings l ON rt.listing_id = l.id
             LEFT JOIN users owner ON l.user_id = owner.id
             WHERE rt.tenant_id = ? AND rt.risk_level IN ('high', 'critical')
             ORDER BY FIELD(rt.risk_level, 'critical', 'high'), rt.created_at DESC",
            [$tenantId]
        );

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Check if a listing requires broker approval for exchanges
     *
     * @param int $listingId Listing ID
     * @return bool True if broker approval required
     */
    public static function requiresApproval(int $listingId): bool
    {
        $tag = self::getTagForListing($listingId);

        if (!$tag) {
            return false;
        }

        // Explicit requires_approval flag
        if ($tag['requires_approval']) {
            return true;
        }

        // High/Critical automatically require approval if config says so
        if (BrokerControlConfigService::doesHighRiskRequireApproval()) {
            return in_array($tag['risk_level'], [self::RISK_HIGH, self::RISK_CRITICAL], true);
        }

        return false;
    }

    /**
     * Get risk level for a listing
     *
     * @param int $listingId Listing ID
     * @return string|null Risk level or null if not tagged
     */
    public static function getRiskLevel(int $listingId): ?string
    {
        $tag = self::getTagForListing($listingId);
        return $tag['risk_level'] ?? null;
    }

    /**
     * Check if a listing is high risk (high or critical)
     *
     * @param int $listingId Listing ID
     * @return bool True if high or critical risk
     */
    public static function isHighRisk(int $listingId): bool
    {
        $level = self::getRiskLevel($listingId);
        return in_array($level, [self::RISK_HIGH, self::RISK_CRITICAL], true);
    }

    /**
     * Get risk tag statistics
     *
     * @return array Statistics array
     */
    public static function getStatistics(): array
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN risk_level = 'low' THEN 1 ELSE 0 END) as low_count,
                SUM(CASE WHEN risk_level = 'medium' THEN 1 ELSE 0 END) as medium_count,
                SUM(CASE WHEN risk_level = 'high' THEN 1 ELSE 0 END) as high_count,
                SUM(CASE WHEN risk_level = 'critical' THEN 1 ELSE 0 END) as critical_count,
                SUM(CASE WHEN requires_approval = 1 THEN 1 ELSE 0 END) as requiring_approval
             FROM listing_risk_tags
             WHERE tenant_id = ?",
            [$tenantId]
        );

        return $stmt->fetch() ?: [
            'total' => 0,
            'low_count' => 0,
            'medium_count' => 0,
            'high_count' => 0,
            'critical_count' => 0,
            'requiring_approval' => 0,
        ];
    }

    /**
     * Notify admins when a high/critical risk tag is created
     *
     * @param int $listingId Listing ID
     * @param string $riskLevel Risk level
     * @param int $brokerId Broker who tagged it
     */
    private static function notifyAdminsOfRiskTag(int $listingId, string $riskLevel, int $brokerId): void
    {
        try {
            // Get listing details
            $stmt = Database::query(
                "SELECT l.title, u.name as owner_name
                 FROM listings l
                 LEFT JOIN users u ON l.user_id = u.id
                 WHERE l.id = ?",
                [$listingId]
            );
            $listing = $stmt->fetch();

            if ($listing) {
                NotificationDispatcher::notifyAdmins(
                    'listing_risk_tagged',
                    [
                        'listing_id' => $listingId,
                        'listing_title' => $listing['title'] ?? 'Unknown',
                        'owner_name' => $listing['owner_name'] ?? 'Unknown',
                        'risk_level' => $riskLevel,
                        'tagged_by' => $brokerId,
                    ],
                    "Listing '{$listing['title']}' tagged as {$riskLevel} risk"
                );
            }
        } catch (\Exception $e) {
            // Log error but don't fail the main operation
            error_log("Failed to notify admins of risk tag: " . $e->getMessage());
        }
    }
}
