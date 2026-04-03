<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FederationDirectoryService — Directory of discoverable timebanks for federation.
 *
 * Provides browsing, filtering, and profile management for the federation
 * directory using the federation_directory_profiles and tenants tables.
 */
class FederationDirectoryService
{
    public function __construct()
    {
    }

    /**
     * Get discoverable timebanks visible to a given tenant.
     *
     * Filters by search term, region, topic, and category. Excludes the requesting tenant.
     */
    public static function getDiscoverableTimebanks(int $currentTenantId, array $filters = []): array
    {
        try {
            $query = "SELECT t.id, t.name, t.slug, t.is_active, t.created_at,
                             fdp.display_name, fdp.tagline, fdp.description, fdp.logo_url,
                             fdp.country_code, fdp.region, fdp.city, fdp.member_count,
                             fdp.active_listings_count, fdp.total_hours_exchanged,
                             fdp.show_member_count, fdp.show_activity_stats, fdp.show_location,
                             (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id AND u.status = 'active') as live_member_count
                      FROM tenants t
                      LEFT JOIN federation_directory_profiles fdp ON fdp.tenant_id = t.id
                      WHERE t.is_active = 1 AND t.id != ?";
            $params = [$currentTenantId];

            // Check if tenant has opted into federation directory
            $query .= " AND fdp.tenant_id IS NOT NULL";

            if (!empty($filters['search'])) {
                $search = '%' . $filters['search'] . '%';
                $query .= " AND (t.name LIKE ? OR t.slug LIKE ? OR fdp.display_name LIKE ? OR fdp.tagline LIKE ? OR fdp.description LIKE ?)";
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
            }

            if (!empty($filters['region'])) {
                $query .= " AND (fdp.region = ? OR fdp.country_code = ?)";
                $params[] = $filters['region'];
                $params[] = $filters['region'];
            }

            if (!empty($filters['topic'])) {
                $query .= " AND t.id IN (
                    SELECT ftt.tenant_id FROM federation_tenant_topics ftt
                    JOIN federation_topics ft ON ft.id = ftt.topic_id
                    WHERE ft.slug = ?
                )";
                $params[] = $filters['topic'];
            }

            if (!empty($filters['exclude_partnered'])) {
                $query .= " AND t.id NOT IN (
                    SELECT CASE WHEN fp.tenant_id = ? THEN fp.partner_tenant_id ELSE fp.tenant_id END
                    FROM federation_partnerships fp
                    WHERE (fp.tenant_id = ? OR fp.partner_tenant_id = ?) AND fp.status = 'active'
                )";
                $params[] = $currentTenantId;
                $params[] = $currentTenantId;
                $params[] = $currentTenantId;
            }

            $query .= " ORDER BY t.name ASC LIMIT 100";

            $rows = DB::select($query, $params);

            // Batch-load topics for all returned tenant IDs
            $tenantIds = array_map(fn($r) => (int) $r->id, $rows);
            $topicsByTenant = self::getTopicsForTenants($tenantIds);

            return array_map(function ($row) use ($topicsByTenant) {
                $showMembers = (bool) ($row->show_member_count ?? true);
                $showActivity = (bool) ($row->show_activity_stats ?? false);
                $tid = (int) $row->id;

                return [
                    'id' => $tid,
                    'name' => $row->display_name ?: $row->name,
                    'slug' => $row->slug,
                    'tagline' => $row->tagline ?? null,
                    'description' => $row->description ?? null,
                    'logo_url' => $row->logo_url ?? null,
                    'country_code' => $row->country_code ?? null,
                    'region' => $row->region ?? null,
                    'city' => $row->city ?? null,
                    'member_count' => $showMembers ? (int) ($row->member_count ?: $row->live_member_count) : null,
                    'active_listings_count' => $showActivity ? (int) ($row->active_listings_count ?? 0) : null,
                    'total_hours_exchanged' => $showActivity ? (float) ($row->total_hours_exchanged ?? 0) : null,
                    'topics' => $topicsByTenant[$tid] ?? [],
                    'is_active' => (bool) $row->is_active,
                    'created_at' => $row->created_at,
                ];
            }, $rows);
        } catch (\Exception $e) {
            Log::error('[FederationDirectory] getDiscoverableTimebanks failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get available regions from the directory profiles.
     */
    public static function getAvailableRegions(): array
    {
        try {
            $rows = DB::select(
                "SELECT DISTINCT COALESCE(fdp.region, fdp.country_code) as region_name, COUNT(*) as count
                 FROM federation_directory_profiles fdp
                 JOIN tenants t ON t.id = fdp.tenant_id AND t.is_active = 1
                 WHERE fdp.region IS NOT NULL OR fdp.country_code IS NOT NULL
                 GROUP BY region_name
                 ORDER BY region_name ASC"
            );

            return array_map(fn($r) => [
                'name' => $r->region_name,
                'count' => (int) $r->count,
            ], $rows);
        } catch (\Exception $e) {
            Log::error('[FederationDirectory] getAvailableRegions failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get available categories from the directory (tenant-level categories).
     */
    public static function getAvailableCategories(): array
    {
        try {
            $rows = DB::select(
                "SELECT DISTINCT c.name, c.id, COUNT(DISTINCT l.tenant_id) as tenant_count
                 FROM categories c
                 JOIN listings l ON l.category_id = c.id AND l.status = 'active'
                 JOIN federation_directory_profiles fdp ON fdp.tenant_id = l.tenant_id
                 GROUP BY c.id, c.name
                 HAVING tenant_count > 1
                 ORDER BY c.name ASC
                 LIMIT 50"
            );

            return array_map(fn($r) => [
                'id' => (int) $r->id,
                'name' => $r->name,
                'tenant_count' => (int) $r->tenant_count,
            ], $rows);
        } catch (\Exception $e) {
            Log::error('[FederationDirectory] getAvailableCategories failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get a single timebank's federation directory profile.
     */
    public static function getTimebankProfile(int $tenantId): ?array
    {
        try {
            $row = DB::selectOne(
                "SELECT t.id, t.name, t.slug, t.is_active, t.created_at,
                        fdp.display_name, fdp.tagline, fdp.description, fdp.logo_url,
                        fdp.cover_image_url, fdp.website_url, fdp.country_code, fdp.region,
                        fdp.city, fdp.latitude, fdp.longitude, fdp.member_count,
                        fdp.active_listings_count, fdp.total_hours_exchanged,
                        fdp.show_member_count, fdp.show_activity_stats, fdp.show_location,
                        (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id AND u.status = 'active') as live_member_count
                 FROM tenants t
                 LEFT JOIN federation_directory_profiles fdp ON fdp.tenant_id = t.id
                 WHERE t.id = ?",
                [$tenantId]
            );

            if (!$row) {
                return null;
            }

            $showMembers = (bool) ($row->show_member_count ?? true);
            $showActivity = (bool) ($row->show_activity_stats ?? false);
            $showLocation = (bool) ($row->show_location ?? true);

            return [
                'id' => (int) $row->id,
                'name' => $row->display_name ?: $row->name,
                'slug' => $row->slug,
                'tagline' => $row->tagline ?? null,
                'description' => $row->description ?? null,
                'logo_url' => $row->logo_url ?? null,
                'cover_image_url' => $row->cover_image_url ?? null,
                'website_url' => $row->website_url ?? null,
                'country_code' => $showLocation ? ($row->country_code ?? null) : null,
                'region' => $showLocation ? ($row->region ?? null) : null,
                'city' => $showLocation ? ($row->city ?? null) : null,
                'latitude' => $showLocation && $row->latitude ? (float) $row->latitude : null,
                'longitude' => $showLocation && $row->longitude ? (float) $row->longitude : null,
                'member_count' => $showMembers ? (int) ($row->member_count ?: $row->live_member_count) : null,
                'active_listings_count' => $showActivity ? (int) ($row->active_listings_count ?? 0) : null,
                'total_hours_exchanged' => $showActivity ? (float) ($row->total_hours_exchanged ?? 0) : null,
                'show_member_count' => $showMembers,
                'show_activity_stats' => $showActivity,
                'show_location' => $showLocation,
                'is_active' => (bool) $row->is_active,
                'created_at' => $row->created_at,
            ];
        } catch (\Exception $e) {
            Log::error('[FederationDirectory] getTimebankProfile failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Update a tenant's federation directory profile.
     */
    public static function updateDirectoryProfile(int $tenantId, array $data): bool
    {
        try {
            $allowedFields = [
                'display_name', 'tagline', 'description', 'logo_url', 'cover_image_url',
                'website_url', 'country_code', 'region', 'city', 'latitude', 'longitude',
                'show_member_count', 'show_activity_stats', 'show_location',
            ];

            $updates = [];
            $params = [];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = "`{$field}` = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($updates)) {
                return true; // Nothing to update
            }

            // Check if profile exists
            $exists = DB::selectOne(
                "SELECT tenant_id FROM federation_directory_profiles WHERE tenant_id = ?",
                [$tenantId]
            );

            if ($exists) {
                $params[] = $tenantId;
                DB::update(
                    "UPDATE federation_directory_profiles SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE tenant_id = ?",
                    $params
                );
            } else {
                // Insert new profile
                $insertFields = [];
                $insertParams = [];
                foreach ($allowedFields as $field) {
                    if (array_key_exists($field, $data)) {
                        $insertFields[] = "`{$field}`";
                        $insertParams[] = $data[$field];
                    }
                }
                $insertFields[] = '`tenant_id`';
                $insertParams[] = $tenantId;

                $placeholders = implode(', ', array_fill(0, count($insertParams), '?'));
                DB::insert(
                    "INSERT INTO federation_directory_profiles (" . implode(', ', $insertFields) . ") VALUES ({$placeholders})",
                    $insertParams
                );
            }

            Log::info('[FederationDirectory] Profile updated', ['tenant_id' => $tenantId]);
            return true;
        } catch (\Exception $e) {
            Log::error('[FederationDirectory] updateDirectoryProfile failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Topic / Interest Tags
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get all available federation topics grouped by category.
     */
    public static function getAllTopics(): array
    {
        try {
            $rows = DB::select(
                "SELECT ft.id, ft.name, ft.slug, ft.icon, ft.category, ft.sort_order,
                        (SELECT COUNT(*) FROM federation_tenant_topics ftt WHERE ftt.topic_id = ft.id) as tenant_count
                 FROM federation_topics ft
                 ORDER BY ft.sort_order ASC, ft.name ASC"
            );

            return array_map(fn($r) => [
                'id' => (int) $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
                'icon' => $r->icon,
                'category' => $r->category,
                'tenant_count' => (int) $r->tenant_count,
            ], $rows);
        } catch (\Exception $e) {
            Log::error('[FederationDirectory] getAllTopics failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get topics with at least one tenant using them (for directory filtering).
     */
    public static function getActiveTopics(): array
    {
        try {
            $rows = DB::select(
                "SELECT ft.id, ft.name, ft.slug, ft.icon, ft.category,
                        COUNT(ftt.tenant_id) as tenant_count
                 FROM federation_topics ft
                 JOIN federation_tenant_topics ftt ON ftt.topic_id = ft.id
                 GROUP BY ft.id, ft.name, ft.slug, ft.icon, ft.category
                 ORDER BY tenant_count DESC, ft.sort_order ASC"
            );

            return array_map(fn($r) => [
                'id' => (int) $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
                'icon' => $r->icon,
                'category' => $r->category,
                'tenant_count' => (int) $r->tenant_count,
            ], $rows);
        } catch (\Exception $e) {
            Log::error('[FederationDirectory] getActiveTopics failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get topics selected by a specific tenant.
     */
    public static function getTenantTopics(int $tenantId): array
    {
        try {
            $rows = DB::select(
                "SELECT ft.id, ft.name, ft.slug, ft.icon, ft.category, ftt.is_primary
                 FROM federation_tenant_topics ftt
                 JOIN federation_topics ft ON ft.id = ftt.topic_id
                 WHERE ftt.tenant_id = ?
                 ORDER BY ftt.is_primary DESC, ft.sort_order ASC",
                [$tenantId]
            );

            return array_map(fn($r) => [
                'id' => (int) $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
                'icon' => $r->icon,
                'category' => $r->category,
                'is_primary' => (bool) $r->is_primary,
            ], $rows);
        } catch (\Exception $e) {
            Log::error('[FederationDirectory] getTenantTopics failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Set topics for a tenant (replaces existing selections).
     *
     * @param int   $tenantId
     * @param array $topicIds     Array of topic IDs to assign
     * @param array $primaryIds   Array of topic IDs to mark as primary (subset of $topicIds)
     */
    public static function setTenantTopics(int $tenantId, array $topicIds, array $primaryIds = []): bool
    {
        try {
            DB::beginTransaction();

            DB::delete("DELETE FROM federation_tenant_topics WHERE tenant_id = ?", [$tenantId]);

            if (!empty($topicIds)) {
                // Validate topic IDs exist
                $placeholders = implode(',', array_fill(0, count($topicIds), '?'));
                $validRows = DB::select(
                    "SELECT id FROM federation_topics WHERE id IN ({$placeholders})",
                    $topicIds
                );
                $validIds = array_map(fn($r) => (int) $r->id, $validRows);

                foreach ($validIds as $topicId) {
                    DB::insert(
                        "INSERT INTO federation_tenant_topics (tenant_id, topic_id, is_primary) VALUES (?, ?, ?)",
                        [$tenantId, $topicId, in_array($topicId, $primaryIds) ? 1 : 0]
                    );
                }
            }

            DB::commit();
            Log::info('[FederationDirectory] Tenant topics updated', ['tenant_id' => $tenantId, 'count' => count($topicIds)]);
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[FederationDirectory] setTenantTopics failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Batch-load topics for multiple tenants (used by directory listing).
     *
     * @return array<int, array> Map of tenant_id => array of topic objects
     */
    private static function getTopicsForTenants(array $tenantIds): array
    {
        if (empty($tenantIds)) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($tenantIds), '?'));
            $rows = DB::select(
                "SELECT ftt.tenant_id, ft.name, ft.slug, ft.icon, ft.category, ftt.is_primary
                 FROM federation_tenant_topics ftt
                 JOIN federation_topics ft ON ft.id = ftt.topic_id
                 WHERE ftt.tenant_id IN ({$placeholders})
                 ORDER BY ftt.is_primary DESC, ft.sort_order ASC",
                $tenantIds
            );

            $map = [];
            foreach ($rows as $row) {
                $tid = (int) $row->tenant_id;
                $map[$tid][] = [
                    'name' => $row->name,
                    'slug' => $row->slug,
                    'icon' => $row->icon,
                    'category' => $row->category,
                    'is_primary' => (bool) $row->is_primary,
                ];
            }
            return $map;
        } catch (\Exception $e) {
            Log::error('[FederationDirectory] getTopicsForTenants failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
