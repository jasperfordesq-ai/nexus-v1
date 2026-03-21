<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * GroupPolicyRepository — CRUD for tenant-scoped group policies.
 *
 * Native Laravel implementation (replaces legacy wrapper).
 * Backed by the `group_policies` table with columns:
 *   id, tenant_id, policy_key, policy_value, category, value_type, description, created_at, updated_at
 */
class GroupPolicyRepository
{
    const CATEGORY_CREATION = 'creation';
    const CATEGORY_MEMBERSHIP = 'membership';
    const CATEGORY_CONTENT = 'content';
    const CATEGORY_MODERATION = 'moderation';
    const CATEGORY_NOTIFICATIONS = 'notifications';
    const CATEGORY_FEATURES = 'features';

    const TYPE_BOOLEAN = 'boolean';
    const TYPE_NUMBER = 'number';
    const TYPE_STRING = 'string';
    const TYPE_JSON = 'json';
    const TYPE_LIST = 'list';

    public function __construct()
    {
    }

    /**
     * Set (upsert) a policy value.
     *
     * @param string $key Policy identifier
     * @param mixed $value Policy value (will be JSON-encoded for complex types)
     * @param string $category One of the CATEGORY_* constants
     * @param string $type One of the TYPE_* constants
     * @param string|null $description Human-readable description
     * @param int|null $tenantId Tenant ID (defaults to current tenant)
     * @return bool
     */
    public function setPolicy($key, $value, $category = self::CATEGORY_FEATURES, $type = self::TYPE_STRING, $description = null, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $storedValue = $this->encodeValue($value, $type);

        try {
            $existing = DB::selectOne(
                "SELECT id FROM group_policies WHERE tenant_id = ? AND policy_key = ?",
                [$tenantId, $key]
            );

            if ($existing) {
                DB::update(
                    "UPDATE group_policies SET policy_value = ?, category = ?, value_type = ?, description = ?, updated_at = NOW()
                     WHERE tenant_id = ? AND policy_key = ?",
                    [$storedValue, $category, $type, $description, $tenantId, $key]
                );
            } else {
                DB::insert(
                    "INSERT INTO group_policies (tenant_id, policy_key, policy_value, category, value_type, description, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    [$tenantId, $key, $storedValue, $category, $type, $description]
                );
            }

            // Invalidate cache
            Cache::forget("group_policies:{$tenantId}");

            return true;
        } catch (\Throwable $e) {
            Log::error('GroupPolicyRepository::setPolicy failed', [
                'key' => $key,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get a single policy value.
     *
     * @param string $key Policy identifier
     * @param mixed $default Default value if policy not found
     * @param int|null $tenantId Tenant ID (defaults to current tenant)
     * @return mixed
     */
    public function getPolicy($key, $default = null, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            $row = DB::selectOne(
                "SELECT policy_value, value_type FROM group_policies WHERE tenant_id = ? AND policy_key = ?",
                [$tenantId, $key]
            );

            if (!$row) {
                return $default;
            }

            return $this->decodeValue($row->policy_value, $row->value_type);
        } catch (\Throwable $e) {
            Log::error('GroupPolicyRepository::getPolicy failed', [
                'key' => $key,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return $default;
        }
    }

    /**
     * Get all policies in a given category.
     *
     * @param string $category One of the CATEGORY_* constants
     * @param int|null $tenantId Tenant ID (defaults to current tenant)
     * @return array<string, array{value: mixed, type: string, description: string|null}>
     */
    public function getPoliciesByCategory($category, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            $rows = DB::select(
                "SELECT policy_key, policy_value, value_type, description
                 FROM group_policies
                 WHERE tenant_id = ? AND category = ?
                 ORDER BY policy_key ASC",
                [$tenantId, $category]
            );

            $result = [];
            foreach ($rows as $row) {
                $result[$row->policy_key] = [
                    'value' => $this->decodeValue($row->policy_value, $row->value_type),
                    'type' => $row->value_type,
                    'description' => $row->description,
                ];
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('GroupPolicyRepository::getPoliciesByCategory failed', [
                'category' => $category,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get all policies grouped by category.
     *
     * @param int|null $tenantId Tenant ID (defaults to current tenant)
     * @return array<string, array<string, array{value: mixed, type: string, description: string|null}>>
     */
    public function getAllPolicies($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        // Check cache
        $cacheKey = "group_policies:{$tenantId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $rows = DB::select(
                "SELECT policy_key, policy_value, category, value_type, description
                 FROM group_policies
                 WHERE tenant_id = ?
                 ORDER BY category ASC, policy_key ASC",
                [$tenantId]
            );

            $result = [];
            foreach ($rows as $row) {
                $result[$row->category][$row->policy_key] = [
                    'value' => $this->decodeValue($row->policy_value, $row->value_type),
                    'type' => $row->value_type,
                    'description' => $row->description,
                ];
            }

            Cache::put($cacheKey, $result, 3600); // 1 hour

            return $result;
        } catch (\Throwable $e) {
            Log::error('GroupPolicyRepository::getAllPolicies failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Delete a policy.
     *
     * @param string $key Policy identifier
     * @param int|null $tenantId Tenant ID (defaults to current tenant)
     * @return bool
     */
    public function deletePolicy($key, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            $affected = DB::delete(
                "DELETE FROM group_policies WHERE tenant_id = ? AND policy_key = ?",
                [$tenantId, $key]
            );

            // Invalidate cache
            Cache::forget("group_policies:{$tenantId}");

            return $affected > 0;
        } catch (\Throwable $e) {
            Log::error('GroupPolicyRepository::deletePolicy failed', [
                'key' => $key,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // =========================================================================
    // Value encoding/decoding
    // =========================================================================

    /**
     * Encode a value for storage based on its type.
     */
    private function encodeValue($value, string $type): string
    {
        return match ($type) {
            self::TYPE_BOOLEAN => $value ? 'true' : 'false',
            self::TYPE_NUMBER => (string) $value,
            self::TYPE_JSON, self::TYPE_LIST => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };
    }

    /**
     * Decode a stored value based on its type.
     */
    private function decodeValue(string $storedValue, string $type): mixed
    {
        return match ($type) {
            self::TYPE_BOOLEAN => in_array(strtolower($storedValue), ['true', '1', 'yes'], true),
            self::TYPE_NUMBER => is_numeric($storedValue) ? (str_contains($storedValue, '.') ? (float) $storedValue : (int) $storedValue) : 0,
            self::TYPE_JSON => json_decode($storedValue, true) ?? $storedValue,
            self::TYPE_LIST => json_decode($storedValue, true) ?? explode(',', $storedValue),
            default => $storedValue,
        };
    }
}
