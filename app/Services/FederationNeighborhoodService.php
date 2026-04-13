<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FederationNeighborhoodService — Manages federation neighborhoods (geographic groupings of tenants).
 *
 * Neighborhoods allow admins to organize tenants into regional clusters
 * for easier federation discovery and management.
 */
class FederationNeighborhoodService
{
    /** @var array<string> */
    private array $errors = [];

    public function __construct()
    {
    }

    /**
     * Get accumulated errors from the last operation.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Static proxy: list all neighborhoods with their tenant counts.
     */
    public static function listAllStatic(): array
    {
        try {
            $rows = DB::select(
                "SELECT fn.id, fn.name, fn.description, fn.region, fn.created_by, fn.created_at, fn.updated_at,
                        (SELECT COUNT(*) FROM federation_neighborhood_tenants fnt WHERE fnt.neighborhood_id = fn.id) as tenant_count,
                        CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as created_by_name
                 FROM federation_neighborhoods fn
                 LEFT JOIN users u ON fn.created_by = u.id
                 ORDER BY fn.name ASC"
            );

            // PERF: Avoid N+1 by fetching ALL neighborhood->tenant rows in a single query,
            // then grouping in PHP. Previous implementation issued one query per neighborhood.
            $neighborhoodIds = array_map(fn($r) => (int) $r->id, $rows);
            $tenantsByNeighborhood = [];
            if (!empty($neighborhoodIds)) {
                $placeholders = implode(',', array_fill(0, count($neighborhoodIds), '?'));
                $allTenants = DB::select(
                    "SELECT fnt.neighborhood_id, fnt.tenant_id, t.name, t.slug
                     FROM federation_neighborhood_tenants fnt
                     JOIN tenants t ON t.id = fnt.tenant_id
                     WHERE fnt.neighborhood_id IN ($placeholders)
                     ORDER BY t.name ASC",
                    $neighborhoodIds
                );
                foreach ($allTenants as $t) {
                    $tenantsByNeighborhood[(int) $t->neighborhood_id][] = [
                        'tenant_id' => (int) $t->tenant_id,
                        'name' => $t->name,
                        'slug' => $t->slug,
                    ];
                }
            }

            return array_map(function ($row) use ($tenantsByNeighborhood) {
                $nid = (int) $row->id;
                return [
                    'id' => $nid,
                    'name' => $row->name,
                    'description' => $row->description,
                    'region' => $row->region,
                    'tenant_count' => (int) $row->tenant_count,
                    'created_by' => $row->created_by ? (int) $row->created_by : null,
                    'created_by_name' => $row->created_by_name ? trim($row->created_by_name) : null,
                    'tenants' => $tenantsByNeighborhood[$nid] ?? [],
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            }, $rows);
        } catch (\Exception $e) {
            Log::error('[FederationNeighborhood] listAllStatic failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Create a new neighborhood (instance method).
     */
    public function create(string $name, ?string $description = null, ?string $region = null, int $createdBy = 0): ?int
    {
        $this->errors = [];

        $name = trim($name);
        if (empty($name)) {
            $this->errors[] = 'Name is required';
            return null;
        }

        try {
            DB::insert(
                "INSERT INTO federation_neighborhoods (name, description, region, created_by, created_at)
                 VALUES (?, ?, ?, ?, NOW())",
                [$name, $description, $region, $createdBy ?: null]
            );

            $id = (int) DB::getPdo()->lastInsertId();

            Log::info('[FederationNeighborhood] Created', [
                'id' => $id,
                'name' => $name,
                'created_by' => $createdBy,
            ]);

            return $id;
        } catch (\Exception $e) {
            Log::error('[FederationNeighborhood] create failed', ['error' => $e->getMessage()]);
            $this->errors[] = 'Failed to create neighborhood';
            return null;
        }
    }

    /**
     * Static proxy: create a neighborhood.
     */
    public static function createStatic(string $name, ?string $description = null, ?string $region = null, int $createdBy = 0): ?int
    {
        $instance = new self();
        return $instance->create($name, $description, $region, $createdBy);
    }

    /**
     * Update a neighborhood.
     */
    public function update(int $id, array $data): bool
    {
        $this->errors = [];

        $updates = [];
        $params = [];

        if (array_key_exists('name', $data)) {
            $name = trim($data['name']);
            if (empty($name)) {
                $this->errors[] = 'Name cannot be empty';
                return false;
            }
            $updates[] = 'name = ?';
            $params[] = $name;
        }

        if (array_key_exists('description', $data)) {
            $updates[] = 'description = ?';
            $params[] = $data['description'];
        }

        if (array_key_exists('region', $data)) {
            $updates[] = 'region = ?';
            $params[] = $data['region'];
        }

        if (empty($updates)) {
            return true;
        }

        try {
            $params[] = $id;
            $updated = DB::update(
                "UPDATE federation_neighborhoods SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?",
                $params
            );

            if ($updated === 0) {
                $this->errors[] = 'Neighborhood not found';
                return false;
            }

            Log::info('[FederationNeighborhood] Updated', ['id' => $id]);
            return true;
        } catch (\Exception $e) {
            Log::error('[FederationNeighborhood] update failed', ['error' => $e->getMessage()]);
            $this->errors[] = 'Failed to update neighborhood';
            return false;
        }
    }

    /**
     * Delete a neighborhood and its tenant associations.
     */
    public function delete(int $id): bool
    {
        $this->errors = [];

        try {
            // Delete tenant associations first
            DB::delete("DELETE FROM federation_neighborhood_tenants WHERE neighborhood_id = ?", [$id]);

            $deleted = DB::delete("DELETE FROM federation_neighborhoods WHERE id = ?", [$id]);

            if ($deleted === 0) {
                $this->errors[] = 'Neighborhood not found';
                return false;
            }

            Log::info('[FederationNeighborhood] Deleted', ['id' => $id]);
            return true;
        } catch (\Exception $e) {
            Log::error('[FederationNeighborhood] delete failed', ['error' => $e->getMessage()]);
            $this->errors[] = 'Failed to delete neighborhood';
            return false;
        }
    }

    /**
     * Get a neighborhood by ID.
     */
    public function getById(int $id): ?array
    {
        try {
            $row = DB::selectOne(
                "SELECT fn.*, CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as created_by_name
                 FROM federation_neighborhoods fn
                 LEFT JOIN users u ON fn.created_by = u.id
                 WHERE fn.id = ?",
                [$id]
            );

            if (!$row) {
                return null;
            }

            $tenants = DB::select(
                "SELECT fnt.tenant_id, t.name, t.slug
                 FROM federation_neighborhood_tenants fnt
                 JOIN tenants t ON t.id = fnt.tenant_id
                 WHERE fnt.neighborhood_id = ?
                 ORDER BY t.name ASC",
                [$id]
            );

            return [
                'id' => (int) $row->id,
                'name' => $row->name,
                'description' => $row->description,
                'region' => $row->region,
                'created_by' => $row->created_by ? (int) $row->created_by : null,
                'created_by_name' => $row->created_by_name ? trim($row->created_by_name) : null,
                'tenants' => array_map(fn($t) => [
                    'tenant_id' => (int) $t->tenant_id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                ], $tenants),
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        } catch (\Exception $e) {
            Log::error('[FederationNeighborhood] getById failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
