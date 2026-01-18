<?php

namespace Nexus\Services;

use Nexus\Core\Database;

/**
 * FederationDirectoryService
 *
 * Manages the federation directory where admins can discover
 * and request partnerships with other timebanks.
 */
class FederationDirectoryService
{
    /**
     * Get all discoverable timebanks for the directory
     *
     * @param int $currentTenantId The viewing tenant (excluded from results)
     * @param array $filters Optional filters (region, category, search)
     * @return array
     */
    public static function getDiscoverableTimebanks(int $currentTenantId, array $filters = []): array
    {
        try {
            // Parameters in order: partnership check (x2), exclude self
            $params = [
                $currentTenantId,  // for fp.tenant_id = ?
                $currentTenantId,  // for fp.partner_tenant_id = ?
                $currentTenantId,  // for t.id != ?
            ];

            $sql = "
                SELECT
                    t.id,
                    t.name,
                    t.slug,
                    t.domain,
                    t.og_image_url as logo_url,
                    t.federation_public_description as description,
                    t.federation_categories as categories,
                    t.federation_region as region,
                    t.federation_contact_email as contact_email,
                    t.federation_contact_name as contact_name,
                    t.federation_member_count_public,
                    CASE
                        WHEN t.federation_member_count_public = 1
                        THEN (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id AND u.status = 'active')
                        ELSE NULL
                    END as member_count,
                    ftf_profiles.is_enabled as profiles_enabled,
                    ftf_listings.is_enabled as listings_enabled,
                    ftf_messaging.is_enabled as messaging_enabled,
                    ftf_transactions.is_enabled as transactions_enabled,
                    ftf_events.is_enabled as events_enabled,
                    ftf_groups.is_enabled as groups_enabled,
                    CASE
                        WHEN fp.id IS NOT NULL THEN fp.status
                        ELSE NULL
                    END as partnership_status,
                    fp.id as partnership_id
                FROM tenants t
                INNER JOIN federation_tenant_whitelist fw ON t.id = fw.tenant_id
                LEFT JOIN federation_tenant_features ftf_profiles
                    ON t.id = ftf_profiles.tenant_id AND ftf_profiles.feature_key = 'tenant_profiles_enabled'
                LEFT JOIN federation_tenant_features ftf_listings
                    ON t.id = ftf_listings.tenant_id AND ftf_listings.feature_key = 'tenant_listings_enabled'
                LEFT JOIN federation_tenant_features ftf_messaging
                    ON t.id = ftf_messaging.tenant_id AND ftf_messaging.feature_key = 'tenant_messaging_enabled'
                LEFT JOIN federation_tenant_features ftf_transactions
                    ON t.id = ftf_transactions.tenant_id AND ftf_transactions.feature_key = 'tenant_transactions_enabled'
                LEFT JOIN federation_tenant_features ftf_events
                    ON t.id = ftf_events.tenant_id AND ftf_events.feature_key = 'tenant_events_enabled'
                LEFT JOIN federation_tenant_features ftf_groups
                    ON t.id = ftf_groups.tenant_id AND ftf_groups.feature_key = 'tenant_groups_enabled'
                LEFT JOIN federation_partnerships fp ON (
                    (fp.tenant_id = ? AND fp.partner_tenant_id = t.id)
                    OR (fp.partner_tenant_id = ? AND fp.tenant_id = t.id)
                )
                WHERE t.id != ?
                AND (t.federation_discoverable = 1 OR t.federation_discoverable IS NULL)
            ";

            // Apply search filter
            if (!empty($filters['search'])) {
                $sql .= " AND (t.name LIKE ? OR t.federation_public_description LIKE ? OR t.federation_region LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            // Apply region filter
            if (!empty($filters['region'])) {
                $sql .= " AND t.federation_region = ?";
                $params[] = $filters['region'];
            }

            // Apply category filter
            if (!empty($filters['category'])) {
                $sql .= " AND t.federation_categories LIKE ?";
                $params[] = '%' . $filters['category'] . '%';
            }

            // Exclude already partnered (optional)
            if (!empty($filters['exclude_partnered'])) {
                $sql .= " AND fp.id IS NULL";
            }

            $sql .= " ORDER BY t.name ASC";

            // Apply limit
            if (!empty($filters['limit'])) {
                $sql .= " LIMIT ?";
                $params[] = (int)$filters['limit'];
            }

            return Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            error_log("FederationDirectoryService::getDiscoverableTimebanks error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all unique regions for filter dropdown
     *
     * @return array
     */
    public static function getAvailableRegions(): array
    {
        try {
            return Database::query("
                SELECT DISTINCT federation_region as region
                FROM tenants
                WHERE federation_region IS NOT NULL
                AND federation_region != ''
                ORDER BY federation_region
            ")->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get all unique categories for filter dropdown
     *
     * @return array
     */
    public static function getAvailableCategories(): array
    {
        try {
            $results = Database::query("
                SELECT DISTINCT federation_categories
                FROM tenants
                WHERE federation_categories IS NOT NULL
                AND federation_categories != ''
            ")->fetchAll(\PDO::FETCH_COLUMN);

            $categories = [];
            foreach ($results as $catString) {
                // Categories stored as comma-separated or JSON
                if (str_starts_with($catString, '[')) {
                    $decoded = json_decode($catString, true);
                    if (is_array($decoded)) {
                        $categories = array_merge($categories, $decoded);
                    }
                } else {
                    $cats = array_map('trim', explode(',', $catString));
                    $categories = array_merge($categories, $cats);
                }
            }

            return array_unique(array_filter($categories));
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get a single timebank's directory profile
     *
     * @param int $tenantId
     * @return array|null
     */
    public static function getTimebankProfile(int $tenantId): ?array
    {
        try {
            $tenant = Database::query("
                SELECT
                    t.id,
                    t.name,
                    t.slug,
                    t.domain,
                    t.og_image_url as logo_url,
                    t.federation_public_description as description,
                    t.federation_categories as categories,
                    t.federation_region as region,
                    t.federation_contact_email as contact_email,
                    t.federation_contact_name as contact_name,
                    t.federation_member_count_public,
                    t.federation_discoverable,
                    CASE
                        WHEN t.federation_member_count_public = 1
                        THEN (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id AND u.status = 'active')
                        ELSE NULL
                    END as member_count
                FROM tenants t
                WHERE t.id = ?
            ", [$tenantId])->fetch(\PDO::FETCH_ASSOC);

            return $tenant ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Update a tenant's directory profile
     *
     * @param int $tenantId
     * @param array $data
     * @return bool
     */
    public static function updateDirectoryProfile(int $tenantId, array $data): bool
    {
        try {
            $allowedFields = [
                'federation_public_description',
                'federation_categories',
                'federation_region',
                'federation_contact_email',
                'federation_contact_name',
                'federation_member_count_public',
                'federation_discoverable',
            ];

            $updates = [];
            $params = [];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = "{$field} = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($updates)) {
                return false;
            }

            $params[] = $tenantId;

            Database::query(
                "UPDATE tenants SET " . implode(', ', $updates) . " WHERE id = ?",
                $params
            );

            return true;
        } catch (\Exception $e) {
            error_log("FederationDirectoryService::updateDirectoryProfile error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get directory statistics
     *
     * @return array
     */
    public static function getDirectoryStats(): array
    {
        try {
            $stats = Database::query("
                SELECT
                    COUNT(DISTINCT t.id) as total_timebanks,
                    COUNT(DISTINCT CASE WHEN t.federation_discoverable = 1 THEN t.id END) as discoverable_timebanks,
                    COUNT(DISTINCT fp.id) as total_partnerships,
                    COUNT(DISTINCT CASE WHEN fp.status = 'active' THEN fp.id END) as active_partnerships,
                    COUNT(DISTINCT CASE WHEN fp.status = 'pending' THEN fp.id END) as pending_partnerships
                FROM tenants t
                LEFT JOIN federation_tenant_whitelist fw ON t.id = fw.tenant_id
                LEFT JOIN federation_partnerships fp ON t.id = fp.tenant_id OR t.id = fp.partner_tenant_id
            ")->fetch(\PDO::FETCH_ASSOC);

            return $stats ?: [
                'total_timebanks' => 0,
                'discoverable_timebanks' => 0,
                'total_partnerships' => 0,
                'active_partnerships' => 0,
                'pending_partnerships' => 0,
            ];
        } catch (\Exception $e) {
            return [
                'total_timebanks' => 0,
                'discoverable_timebanks' => 0,
                'total_partnerships' => 0,
                'active_partnerships' => 0,
                'pending_partnerships' => 0,
            ];
        }
    }
}
