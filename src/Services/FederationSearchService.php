<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationExternalPartnerService;
use Nexus\Services\FederationExternalApiClient;

/**
 * Federation Search Service
 *
 * Provides advanced search capabilities for federated members across
 * partner timebanks. Supports filtering by skills, location, service reach,
 * and full-text search.
 */
class FederationSearchService
{
    /**
     * Advanced search for federated members
     *
     * @param array $partnerTenantIds Array of partner tenant IDs to search
     * @param array $filters Search filters
     * @return array Search results with members and metadata
     */
    public static function searchMembers(array $partnerTenantIds, array $filters): array
    {
        if (empty($partnerTenantIds)) {
            return [
                'members' => [],
                'total' => 0,
                'filters_applied' => []
            ];
        }

        $placeholders = implode(',', array_fill(0, count($partnerTenantIds), '?'));
        $params = $partnerTenantIds;
        $filtersApplied = [];

        // Base query
        $sql = "SELECT u.id,
                       COALESCE(NULLIF(u.name, ''), TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')))) as name,
                       u.avatar_url, u.bio, u.tenant_id,
                       t.name as tenant_name,
                       fus.service_reach,
                       CASE WHEN fus.show_location_federated = 1 THEN u.location ELSE NULL END as location,
                       CASE WHEN fus.show_location_federated = 1 THEN u.latitude ELSE NULL END as latitude,
                       CASE WHEN fus.show_location_federated = 1 THEN u.longitude ELSE NULL END as longitude,
                       CASE WHEN fus.show_skills_federated = 1 THEN u.skills ELSE NULL END as skills,
                       fus.messaging_enabled_federated,
                       fus.transactions_enabled_federated,
                       fus.travel_radius_km
                FROM users u
                INNER JOIN tenants t ON u.tenant_id = t.id
                INNER JOIN federation_user_settings fus ON u.id = fus.user_id
                WHERE u.tenant_id IN ({$placeholders})
                AND u.status = 'active'
                AND fus.federation_optin = 1
                AND fus.appear_in_federated_search = 1";

        // Apply tenant filter
        if (!empty($filters['tenant_id']) && in_array($filters['tenant_id'], $partnerTenantIds)) {
            $sql .= " AND u.tenant_id = ?";
            $params[] = $filters['tenant_id'];
            $filtersApplied[] = 'tenant';
        }

        // Apply service reach filter
        if (!empty($filters['service_reach'])) {
            switch ($filters['service_reach']) {
                case 'remote_ok':
                    $sql .= " AND fus.service_reach IN ('remote_ok', 'travel_ok')";
                    $filtersApplied[] = 'service_reach';
                    break;
                case 'travel_ok':
                    $sql .= " AND fus.service_reach = 'travel_ok'";
                    $filtersApplied[] = 'service_reach';
                    break;
                case 'local_only':
                    $sql .= " AND fus.service_reach = 'local_only'";
                    $filtersApplied[] = 'service_reach';
                    break;
            }
        }

        // Apply skills filter (comma-separated list or single skill)
        if (!empty($filters['skills'])) {
            $skillTerms = is_array($filters['skills'])
                ? $filters['skills']
                : array_map('trim', explode(',', $filters['skills']));

            $skillConditions = [];
            foreach ($skillTerms as $skill) {
                if (!empty($skill)) {
                    $skillConditions[] = "(fus.show_skills_federated = 1 AND u.skills LIKE ?)";
                    $params[] = '%' . $skill . '%';
                }
            }

            if (!empty($skillConditions)) {
                $sql .= " AND (" . implode(' OR ', $skillConditions) . ")";
                $filtersApplied[] = 'skills';
            }
        }

        // Apply location filter (city/region text search)
        if (!empty($filters['location'])) {
            $sql .= " AND fus.show_location_federated = 1 AND u.location LIKE ?";
            $params[] = '%' . $filters['location'] . '%';
            $filtersApplied[] = 'location';
        }

        // Apply radius search (if coordinates provided)
        if (!empty($filters['latitude']) && !empty($filters['longitude']) && !empty($filters['radius_km'])) {
            // Haversine formula for distance calculation
            $sql .= " AND fus.show_location_federated = 1
                      AND u.latitude IS NOT NULL
                      AND u.longitude IS NOT NULL
                      AND (
                          6371 * acos(
                              cos(radians(?)) * cos(radians(u.latitude)) *
                              cos(radians(u.longitude) - radians(?)) +
                              sin(radians(?)) * sin(radians(u.latitude))
                          )
                      ) <= ?";
            $params[] = $filters['latitude'];
            $params[] = $filters['longitude'];
            $params[] = $filters['latitude'];
            $params[] = $filters['radius_km'];
            $filtersApplied[] = 'radius';
        }

        // Apply messaging availability filter
        if (!empty($filters['messaging_enabled'])) {
            $sql .= " AND fus.messaging_enabled_federated = 1";
            $filtersApplied[] = 'messaging_enabled';
        }

        // Apply transactions availability filter
        if (!empty($filters['transactions_enabled'])) {
            $sql .= " AND fus.transactions_enabled_federated = 1";
            $filtersApplied[] = 'transactions_enabled';
        }

        // Apply general text search (name, bio, skills)
        if (!empty($filters['search'])) {
            $sql .= " AND (
                u.first_name LIKE ? OR
                u.last_name LIKE ? OR
                u.name LIKE ? OR
                u.bio LIKE ? OR
                (fus.show_skills_federated = 1 AND u.skills LIKE ?)
            )";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $filtersApplied[] = 'search';
        }

        // Sorting
        $orderBy = " ORDER BY ";
        switch ($filters['sort'] ?? 'name') {
            case 'recent':
                $orderBy .= "u.created_at DESC";
                break;
            case 'active':
                $orderBy .= "u.last_active_at DESC";
                break;
            case 'name':
            default:
                $orderBy .= "u.first_name, u.last_name";
                break;
        }
        $sql .= $orderBy;

        // Pagination
        $limit = min((int)($filters['limit'] ?? 30), 100);
        $offset = max(0, (int)($filters['offset'] ?? 0));
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        try {
            $members = Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);

            // Parse skills into array for easier display
            foreach ($members as &$member) {
                if (!empty($member['skills'])) {
                    $member['skills_array'] = array_map('trim', explode(',', $member['skills']));
                } else {
                    $member['skills_array'] = [];
                }
            }

            return [
                'members' => $members,
                'total' => count($members),
                'filters_applied' => $filtersApplied,
                'has_more' => count($members) >= $limit
            ];
        } catch (\Exception $e) {
            error_log("FederationSearchService::searchMembers error: " . $e->getMessage());
            return [
                'members' => [],
                'total' => 0,
                'filters_applied' => [],
                'error' => 'Search failed'
            ];
        }
    }

    /**
     * Get available skills across all federated members for autocomplete
     *
     * @param array $partnerTenantIds Partner tenant IDs
     * @param string $query Partial skill name to search
     * @param int $limit Maximum number of results
     * @return array List of matching skills
     */
    public static function getAvailableSkills(array $partnerTenantIds, string $query = '', int $limit = 20): array
    {
        if (empty($partnerTenantIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($partnerTenantIds), '?'));
        $params = $partnerTenantIds;

        try {
            // Get all skills from federated users who share them
            $sql = "SELECT DISTINCT u.skills
                    FROM users u
                    INNER JOIN federation_user_settings fus ON u.id = fus.user_id
                    WHERE u.tenant_id IN ({$placeholders})
                    AND u.status = 'active'
                    AND fus.federation_optin = 1
                    AND fus.show_skills_federated = 1
                    AND u.skills IS NOT NULL
                    AND u.skills != ''";

            if (!empty($query)) {
                $sql .= " AND u.skills LIKE ?";
                $params[] = '%' . $query . '%';
            }

            $results = Database::query($sql, $params)->fetchAll(\PDO::FETCH_COLUMN);

            // Parse and deduplicate skills
            $allSkills = [];
            foreach ($results as $skillString) {
                $skills = array_map('trim', explode(',', $skillString));
                foreach ($skills as $skill) {
                    if (!empty($skill)) {
                        $skillLower = strtolower($skill);
                        if (!isset($allSkills[$skillLower])) {
                            $allSkills[$skillLower] = $skill;
                        }
                    }
                }
            }

            // Filter by query if provided
            if (!empty($query)) {
                $queryLower = strtolower($query);
                $allSkills = array_filter($allSkills, function($skill) use ($queryLower) {
                    return stripos($skill, $queryLower) !== false;
                });
            }

            // Sort and limit
            $skills = array_values($allSkills);
            sort($skills, SORT_NATURAL | SORT_FLAG_CASE);

            return array_slice($skills, 0, $limit);
        } catch (\Exception $e) {
            error_log("FederationSearchService::getAvailableSkills error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get available locations across all federated members for autocomplete
     *
     * @param array $partnerTenantIds Partner tenant IDs
     * @param string $query Partial location name to search
     * @param int $limit Maximum number of results
     * @return array List of matching locations
     */
    public static function getAvailableLocations(array $partnerTenantIds, string $query = '', int $limit = 20): array
    {
        if (empty($partnerTenantIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($partnerTenantIds), '?'));
        $params = $partnerTenantIds;

        try {
            $sql = "SELECT DISTINCT u.location
                    FROM users u
                    INNER JOIN federation_user_settings fus ON u.id = fus.user_id
                    WHERE u.tenant_id IN ({$placeholders})
                    AND u.status = 'active'
                    AND fus.federation_optin = 1
                    AND fus.show_location_federated = 1
                    AND u.location IS NOT NULL
                    AND u.location != ''";

            if (!empty($query)) {
                $sql .= " AND u.location LIKE ?";
                $params[] = '%' . $query . '%';
            }

            $sql .= " ORDER BY u.location LIMIT ?";
            $params[] = $limit;

            return Database::query($sql, $params)->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            error_log("FederationSearchService::getAvailableLocations error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get search statistics for display
     *
     * @param array $partnerTenantIds Partner tenant IDs
     * @return array Statistics about searchable members
     */
    public static function getSearchStats(array $partnerTenantIds): array
    {
        if (empty($partnerTenantIds)) {
            return [
                'total_members' => 0,
                'remote_available' => 0,
                'travel_available' => 0,
                'messaging_enabled' => 0,
                'transactions_enabled' => 0
            ];
        }

        $placeholders = implode(',', array_fill(0, count($partnerTenantIds), '?'));

        try {
            $result = Database::query("
                SELECT
                    COUNT(*) as total_members,
                    SUM(CASE WHEN fus.service_reach IN ('remote_ok', 'travel_ok') THEN 1 ELSE 0 END) as remote_available,
                    SUM(CASE WHEN fus.service_reach = 'travel_ok' THEN 1 ELSE 0 END) as travel_available,
                    SUM(CASE WHEN fus.messaging_enabled_federated = 1 THEN 1 ELSE 0 END) as messaging_enabled,
                    SUM(CASE WHEN fus.transactions_enabled_federated = 1 THEN 1 ELSE 0 END) as transactions_enabled
                FROM users u
                INNER JOIN federation_user_settings fus ON u.id = fus.user_id
                WHERE u.tenant_id IN ({$placeholders})
                AND u.status = 'active'
                AND fus.federation_optin = 1
                AND fus.appear_in_federated_search = 1
            ", $partnerTenantIds)->fetch(\PDO::FETCH_ASSOC);

            return [
                'total_members' => (int)($result['total_members'] ?? 0),
                'remote_available' => (int)($result['remote_available'] ?? 0),
                'travel_available' => (int)($result['travel_available'] ?? 0),
                'messaging_enabled' => (int)($result['messaging_enabled'] ?? 0),
                'transactions_enabled' => (int)($result['transactions_enabled'] ?? 0)
            ];
        } catch (\Exception $e) {
            error_log("FederationSearchService::getSearchStats error: " . $e->getMessage());
            return [
                'total_members' => 0,
                'remote_available' => 0,
                'travel_available' => 0,
                'messaging_enabled' => 0,
                'transactions_enabled' => 0
            ];
        }
    }

    /**
     * Search members from external federation partners via API
     *
     * @param int $tenantId Current tenant ID
     * @param array $filters Search filters
     * @return array Members from external partners
     */
    public static function searchExternalMembers(int $tenantId, array $filters): array
    {
        $externalPartners = FederationExternalPartnerService::getActivePartners($tenantId);

        if (empty($externalPartners)) {
            return [
                'members' => [],
                'total' => 0,
                'partners_queried' => 0,
                'errors' => []
            ];
        }

        $allMembers = [];
        $errors = [];
        $partnersQueried = 0;

        foreach ($externalPartners as $partner) {
            try {
                $client = new FederationExternalApiClient($partner);

                // Build API params from filters
                $apiParams = [];
                if (!empty($filters['search'])) {
                    $apiParams['q'] = $filters['search'];
                }
                if (!empty($filters['skills'])) {
                    $apiParams['skills'] = $filters['skills'];
                }
                if (!empty($filters['location'])) {
                    $apiParams['location'] = $filters['location'];
                }
                if (!empty($filters['service_reach'])) {
                    $apiParams['reach'] = $filters['service_reach'];
                }
                $apiParams['limit'] = $filters['limit'] ?? 30;

                $result = $client->searchMembers($apiParams);
                $partnersQueried++;

                if ($result['success'] && !empty($result['data'])) {
                    // Handle different response formats
                    $members = $result['data']['members'] ?? $result['data']['data'] ?? $result['data'] ?? [];

                    if (is_array($members)) {
                        foreach ($members as &$member) {
                            // Mark as external and add partner info
                            $member['is_external'] = true;
                            $member['external_partner_id'] = $partner['id'];
                            $member['external_partner_name'] = $partner['partner_name'] ?: $partner['name'];
                            $member['external_partner_url'] = $partner['base_url'];
                            $member['tenant_name'] = $partner['partner_name'] ?: $partner['name'];

                            // Ensure required fields exist
                            if (empty($member['name'])) {
                                $member['name'] = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
                            }

                            // Parse skills if string
                            if (!empty($member['skills']) && is_string($member['skills'])) {
                                $member['skills_array'] = array_map('trim', explode(',', $member['skills']));
                            } elseif (!empty($member['skills']) && is_array($member['skills'])) {
                                $member['skills_array'] = $member['skills'];
                            } else {
                                $member['skills_array'] = [];
                            }

                            $allMembers[] = $member;
                        }
                    }
                } else {
                    $errors[] = [
                        'partner' => $partner['name'],
                        'error' => $result['error'] ?? 'Unknown error'
                    ];
                }
            } catch (\Exception $e) {
                error_log("FederationSearchService::searchExternalMembers error for partner {$partner['id']}: " . $e->getMessage());
                $errors[] = [
                    'partner' => $partner['name'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'members' => $allMembers,
            'total' => count($allMembers),
            'partners_queried' => $partnersQueried,
            'errors' => $errors
        ];
    }

    /**
     * Combined search across internal tenant partners AND external partners
     *
     * @param array $partnerTenantIds Internal partner tenant IDs
     * @param int $tenantId Current tenant ID (for external partner lookup)
     * @param array $filters Search filters
     * @return array Combined search results
     */
    public static function searchAllFederatedMembers(array $partnerTenantIds, int $tenantId, array $filters): array
    {
        // Get internal members
        $internalResult = self::searchMembers($partnerTenantIds, $filters);
        $internalMembers = $internalResult['members'];

        // Mark internal members
        foreach ($internalMembers as &$member) {
            $member['is_external'] = false;
        }

        // Get external members
        $externalResult = self::searchExternalMembers($tenantId, $filters);
        $externalMembers = $externalResult['members'];

        // Merge results
        $allMembers = array_merge($internalMembers, $externalMembers);

        // Apply sorting to combined results
        $sort = $filters['sort'] ?? 'name';
        usort($allMembers, function($a, $b) use ($sort) {
            switch ($sort) {
                case 'recent':
                    return ($b['created_at'] ?? '') <=> ($a['created_at'] ?? '');
                case 'active':
                    return ($b['last_active_at'] ?? '') <=> ($a['last_active_at'] ?? '');
                case 'name':
                default:
                    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
            }
        });

        // Apply pagination to merged results (re-slice if needed)
        $limit = min((int)($filters['limit'] ?? 30), 100);
        $offset = max(0, (int)($filters['offset'] ?? 0));
        $paginatedMembers = array_slice($allMembers, $offset, $limit);

        return [
            'members' => $paginatedMembers,
            'total' => count($allMembers),
            'internal_count' => count($internalMembers),
            'external_count' => count($externalMembers),
            'filters_applied' => $internalResult['filters_applied'] ?? [],
            'has_more' => count($allMembers) > ($offset + $limit),
            'external_partners_queried' => $externalResult['partners_queried'],
            'external_errors' => $externalResult['errors']
        ];
    }

    /**
     * Search listings from external federation partners via API
     *
     * @param int $tenantId Current tenant ID
     * @param array $filters Search filters
     * @return array Listings from external partners
     */
    public static function searchExternalListings(int $tenantId, array $filters): array
    {
        $externalPartners = FederationExternalPartnerService::getActivePartnersForListings($tenantId);

        if (empty($externalPartners)) {
            return [
                'listings' => [],
                'total' => 0,
                'partners_queried' => 0,
                'errors' => []
            ];
        }

        $allListings = [];
        $errors = [];
        $partnersQueried = 0;

        foreach ($externalPartners as $partner) {
            try {
                $client = new FederationExternalApiClient($partner);

                // Build API params from filters
                $apiParams = [];
                if (!empty($filters['search'])) {
                    $apiParams['q'] = $filters['search'];
                }
                if (!empty($filters['type'])) {
                    $apiParams['type'] = $filters['type'];
                }
                if (!empty($filters['category'])) {
                    $apiParams['category'] = $filters['category'];
                }
                $apiParams['limit'] = $filters['limit'] ?? 30;

                $result = $client->searchListings($apiParams);
                $partnersQueried++;

                if ($result['success'] && !empty($result['data'])) {
                    // Handle different response formats
                    $listings = $result['data']['listings'] ?? $result['data']['data'] ?? $result['data'] ?? [];

                    if (is_array($listings)) {
                        foreach ($listings as &$listing) {
                            // Mark as external and add partner info
                            $listing['is_external'] = true;
                            $listing['external_partner_id'] = $partner['id'];
                            $listing['external_partner_name'] = $partner['partner_name'] ?: $partner['name'];
                            $listing['external_partner_url'] = $partner['base_url'];
                            $listing['tenant_name'] = $partner['partner_name'] ?: $partner['name'];

                            $allListings[] = $listing;
                        }
                    }
                } else {
                    $errors[] = [
                        'partner' => $partner['name'],
                        'error' => $result['error'] ?? 'Unknown error'
                    ];
                }
            } catch (\Exception $e) {
                error_log("FederationSearchService::searchExternalListings error for partner {$partner['id']}: " . $e->getMessage());
                $errors[] = [
                    'partner' => $partner['name'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'listings' => $allListings,
            'total' => count($allListings),
            'partners_queried' => $partnersQueried,
            'errors' => $errors
        ];
    }

    /**
     * Get members with specific skills for recommendations
     *
     * @param array $partnerTenantIds Partner tenant IDs
     * @param array $skills Skills to match
     * @param int $excludeUserId User ID to exclude (self)
     * @param int $limit Maximum results
     * @return array Matching members
     */
    public static function findMembersBySkills(
        array $partnerTenantIds,
        array $skills,
        int $excludeUserId = 0,
        int $limit = 10
    ): array {
        if (empty($partnerTenantIds) || empty($skills)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($partnerTenantIds), '?'));
        $params = $partnerTenantIds;

        // Build skill conditions
        $skillConditions = [];
        foreach ($skills as $skill) {
            $skill = trim($skill);
            if (!empty($skill)) {
                $skillConditions[] = "u.skills LIKE ?";
                $params[] = '%' . $skill . '%';
            }
        }

        if (empty($skillConditions)) {
            return [];
        }

        $sql = "SELECT u.id,
                       COALESCE(NULLIF(u.name, ''), TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')))) as name,
                       u.avatar_url, u.bio, u.tenant_id,
                       t.name as tenant_name,
                       fus.service_reach,
                       u.skills
                FROM users u
                INNER JOIN tenants t ON u.tenant_id = t.id
                INNER JOIN federation_user_settings fus ON u.id = fus.user_id
                WHERE u.tenant_id IN ({$placeholders})
                AND u.status = 'active'
                AND fus.federation_optin = 1
                AND fus.show_skills_federated = 1
                AND (" . implode(' OR ', $skillConditions) . ")";

        if ($excludeUserId > 0) {
            $sql .= " AND u.id != ?";
            $params[] = $excludeUserId;
        }

        $sql .= " ORDER BY u.first_name, u.last_name LIMIT ?";
        $params[] = $limit;

        try {
            return Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("FederationSearchService::findMembersBySkills error: " . $e->getMessage());
            return [];
        }
    }
}
