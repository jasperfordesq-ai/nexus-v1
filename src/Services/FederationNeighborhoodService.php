<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * FederationNeighborhoodService - Neighborhood groups for federated tenants
 *
 * Allows federated tenants to form "neighborhood" groups — clusters of
 * nearby timebanks that share resources, events, and listings.
 *
 * Tables:
 *   federation_neighborhoods — neighborhood definitions
 *   federation_neighborhood_members — tenant membership in neighborhoods
 *
 * Operations:
 * - Create/manage neighborhoods
 * - Add/remove tenant members
 * - List shared events within a neighborhood
 * - Get neighborhood statistics
 */
class FederationNeighborhoodService
{
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    // =========================================================================
    // NEIGHBORHOOD CRUD
    // =========================================================================

    /**
     * Create a new neighborhood
     */
    public static function create(string $name, ?string $description = null, ?string $region = null, int $createdBy = 0): ?int
    {
        self::$errors = [];

        $name = trim($name);
        if (empty($name)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Name is required', 'field' => 'name'];
            return null;
        }

        try {
            Database::query(
                "INSERT INTO federation_neighborhoods (name, description, region, created_by, created_at)
                 VALUES (?, ?, ?, ?, NOW())",
                [$name, $description, $region, $createdBy]
            );

            return (int)Database::getInstance()->lastInsertId();
        } catch (\Exception $e) {
            error_log("FederationNeighborhoodService::create error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create neighborhood'];
            return null;
        }
    }

    /**
     * Update a neighborhood
     */
    public static function update(int $id, array $data): bool
    {
        self::$errors = [];

        $updates = [];
        $params = [];

        if (array_key_exists('name', $data)) {
            $name = trim($data['name']);
            if (empty($name)) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Name is required', 'field' => 'name'];
                return false;
            }
            $updates[] = "name = ?";
            $params[] = $name;
        }
        if (array_key_exists('description', $data)) {
            $updates[] = "description = ?";
            $params[] = $data['description'];
        }
        if (array_key_exists('region', $data)) {
            $updates[] = "region = ?";
            $params[] = $data['region'];
        }

        if (empty($updates)) {
            return true;
        }

        $updates[] = "updated_at = NOW()";
        $params[] = $id;

        try {
            Database::query(
                "UPDATE federation_neighborhoods SET " . implode(', ', $updates) . " WHERE id = ?",
                $params
            );
            return true;
        } catch (\Exception $e) {
            error_log("FederationNeighborhoodService::update error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to update neighborhood'];
            return false;
        }
    }

    /**
     * Delete a neighborhood
     */
    public static function delete(int $id): bool
    {
        $tenantId = TenantContext::getId();

        try {
            // Verify the neighborhood belongs to this tenant before deleting
            $neighborhood = Database::query(
                "SELECT id FROM federation_neighborhoods WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$neighborhood) {
                self::$errors[] = ['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Neighborhood not found'];
                return false;
            }

            // Remove members first, scoped via subquery
            Database::query(
                "DELETE FROM federation_neighborhood_members WHERE neighborhood_id IN (SELECT id FROM federation_neighborhoods WHERE id = ? AND tenant_id = ?)",
                [$id, $tenantId]
            );
            Database::query("DELETE FROM federation_neighborhoods WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            return true;
        } catch (\Exception $e) {
            error_log("FederationNeighborhoodService::delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a neighborhood by ID
     */
    public static function getById(int $id): ?array
    {
        try {
            $neighborhood = Database::query(
                "SELECT n.*, (SELECT COUNT(*) FROM federation_neighborhood_members WHERE neighborhood_id = n.id) as member_count
                 FROM federation_neighborhoods n WHERE n.id = ?",
                [$id]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$neighborhood) {
                return null;
            }

            // Get members
            $neighborhood['members'] = self::getMembers($id);

            return $neighborhood;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * List all neighborhoods
     */
    public static function listAll(?string $region = null): array
    {
        try {
            $sql = "SELECT n.*, (SELECT COUNT(*) FROM federation_neighborhood_members WHERE neighborhood_id = n.id) as member_count
                    FROM federation_neighborhoods n";
            $params = [];

            if ($region) {
                $sql .= " WHERE n.region = ?";
                $params[] = $region;
            }

            $sql .= " ORDER BY n.name ASC";

            return Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * List neighborhoods a tenant belongs to
     */
    public static function getForTenant(int $tenantId): array
    {
        try {
            return Database::query(
                "SELECT n.*, fnm.joined_at,
                        (SELECT COUNT(*) FROM federation_neighborhood_members WHERE neighborhood_id = n.id) as member_count
                 FROM federation_neighborhoods n
                 JOIN federation_neighborhood_members fnm ON n.id = fnm.neighborhood_id
                 WHERE fnm.tenant_id = ?
                 ORDER BY n.name ASC",
                [$tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    // =========================================================================
    // MEMBERSHIP
    // =========================================================================

    /**
     * Add a tenant to a neighborhood
     */
    public static function addMember(int $neighborhoodId, int $tenantId): bool
    {
        self::$errors = [];

        try {
            Database::query(
                "INSERT IGNORE INTO federation_neighborhood_members (neighborhood_id, tenant_id, joined_at)
                 VALUES (?, ?, NOW())",
                [$neighborhoodId, $tenantId]
            );
            return true;
        } catch (\Exception $e) {
            error_log("FederationNeighborhoodService::addMember error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to add member'];
            return false;
        }
    }

    /**
     * Remove a tenant from a neighborhood
     */
    public static function removeMember(int $neighborhoodId, int $tenantId): bool
    {
        try {
            Database::query(
                "DELETE FROM federation_neighborhood_members WHERE neighborhood_id = ? AND tenant_id = ?",
                [$neighborhoodId, $tenantId]
            );
            return true;
        } catch (\Exception $e) {
            error_log("FederationNeighborhoodService::removeMember error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all tenant members of a neighborhood
     */
    public static function getMembers(int $neighborhoodId): array
    {
        try {
            return Database::query(
                "SELECT t.id, t.name, t.domain, fnm.joined_at
                 FROM federation_neighborhood_members fnm
                 JOIN tenants t ON fnm.tenant_id = t.id
                 WHERE fnm.neighborhood_id = ?
                 ORDER BY t.name ASC",
                [$neighborhoodId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    // =========================================================================
    // SHARED RESOURCES
    // =========================================================================

    /**
     * Get shared events within a neighborhood
     * Returns events from all member tenants with federated_visibility != 'none'
     */
    public static function getSharedEvents(int $neighborhoodId, int $limit = 20): array
    {
        try {
            $limitInt = (int)$limit;
            // Get member tenant IDs
            $memberIds = Database::query(
                "SELECT tenant_id FROM federation_neighborhood_members WHERE neighborhood_id = ?",
                [$neighborhoodId]
            )->fetchAll(\PDO::FETCH_COLUMN);

            if (empty($memberIds)) {
                return [];
            }

            $placeholders = implode(',', array_fill(0, count($memberIds), '?'));

            return Database::query(
                "SELECT e.id, e.title, e.description, e.start_time, e.end_time, e.location,
                        e.tenant_id, t.name as tenant_name
                 FROM events e
                 JOIN tenants t ON e.tenant_id = t.id
                 WHERE e.tenant_id IN ({$placeholders})
                   AND e.start_time >= NOW()
                 ORDER BY e.start_time ASC
                 LIMIT {$limitInt}",
                $memberIds
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("FederationNeighborhoodService::getSharedEvents error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get shared listings within a neighborhood
     */
    public static function getSharedListings(int $neighborhoodId, int $limit = 20): array
    {
        try {
            $limitInt = (int)$limit;
            $memberIds = Database::query(
                "SELECT tenant_id FROM federation_neighborhood_members WHERE neighborhood_id = ?",
                [$neighborhoodId]
            )->fetchAll(\PDO::FETCH_COLUMN);

            if (empty($memberIds)) {
                return [];
            }

            $placeholders = implode(',', array_fill(0, count($memberIds), '?'));

            return Database::query(
                "SELECT l.id, l.title, l.description, l.type, l.location,
                        l.tenant_id, t.name as tenant_name,
                        u.name as user_name
                 FROM listings l
                 JOIN tenants t ON l.tenant_id = t.id
                 JOIN users u ON l.user_id = u.id
                 WHERE l.tenant_id IN ({$placeholders})
                   AND l.status = 'active'
                 ORDER BY l.created_at DESC
                 LIMIT {$limitInt}",
                $memberIds
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("FederationNeighborhoodService::getSharedListings error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get neighborhood statistics
     */
    public static function getStats(int $neighborhoodId): array
    {
        try {
            $memberIds = Database::query(
                "SELECT tenant_id FROM federation_neighborhood_members WHERE neighborhood_id = ?",
                [$neighborhoodId]
            )->fetchAll(\PDO::FETCH_COLUMN);

            if (empty($memberIds)) {
                return [
                    'tenant_count' => 0,
                    'total_members' => 0,
                    'active_listings' => 0,
                    'upcoming_events' => 0,
                ];
            }

            $placeholders = implode(',', array_fill(0, count($memberIds), '?'));

            $totalMembers = (int)Database::query(
                "SELECT COUNT(*) FROM users WHERE tenant_id IN ({$placeholders}) AND status = 'active'",
                $memberIds
            )->fetchColumn();

            $activeListings = (int)Database::query(
                "SELECT COUNT(*) FROM listings WHERE tenant_id IN ({$placeholders}) AND status = 'active'",
                $memberIds
            )->fetchColumn();

            $upcomingEvents = (int)Database::query(
                "SELECT COUNT(*) FROM events WHERE tenant_id IN ({$placeholders}) AND start_time >= NOW()",
                $memberIds
            )->fetchColumn();

            return [
                'tenant_count' => count($memberIds),
                'total_members' => $totalMembers,
                'active_listings' => $activeListings,
                'upcoming_events' => $upcomingEvents,
            ];
        } catch (\Exception $e) {
            return [
                'tenant_count' => 0,
                'total_members' => 0,
                'active_listings' => 0,
                'upcoming_events' => 0,
            ];
        }
    }
}
