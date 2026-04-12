<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Core\TenantContext;

/**
 * GroupTagService — Manages group tags/topics for discovery and categorization.
 */
class GroupTagService
{
    /**
     * Get all tags for the current tenant.
     */
    public static function getAll(array $filters = []): array
    {
        $tenantId = TenantContext::getId();

        $query = DB::table('group_tags')
            ->where('tenant_id', $tenantId);

        if (!empty($filters['search'])) {
            // Escape LIKE metacharacters (% and _) so a search for "50%" doesn't
            // become a "match anything containing 50" wildcard query.
            $search = addcslashes((string) $filters['search'], '\\%_');
            $query->where('name', 'LIKE', '%' . $search . '%');
        }

        // Whitelist order-by column and direction to block SQL injection via
        // a request that reaches this method with attacker-controlled filters
        // (currently no caller exposes these, but the latent hole shouldn't
        // exist in the service either).
        $allowedOrderBy = ['id', 'name', 'slug', 'usage_count', 'created_at'];
        $orderBy = in_array($filters['order_by'] ?? '', $allowedOrderBy, true)
            ? $filters['order_by']
            : 'usage_count';
        $direction = strtolower((string) ($filters['direction'] ?? 'desc'));
        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }
        $query->orderBy($orderBy, $direction);

        $limit = min($filters['limit'] ?? 100, 500);
        $query->limit($limit);

        return $query->get()->map(fn ($row) => (array) $row)->toArray();
    }

    /**
     * Get popular tags (top N by usage).
     */
    public static function getPopular(int $limit = 20): array
    {
        $tenantId = TenantContext::getId();

        return DB::table('group_tags')
            ->where('tenant_id', $tenantId)
            ->where('usage_count', '>', 0)
            ->orderByDesc('usage_count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Create a new tag.
     */
    public static function create(string $name, ?string $color = null): ?array
    {
        $tenantId = TenantContext::getId();
        $slug = Str::slug($name);

        // Check for existing
        $existing = DB::table('group_tags')
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->first();

        if ($existing) {
            return (array) $existing;
        }

        $id = DB::table('group_tags')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => $slug,
            'color' => $color,
            'created_at' => now(),
        ]);

        return [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'color' => $color,
            'usage_count' => 0,
        ];
    }

    /**
     * Delete a tag.
     */
    public static function delete(int $tagId): bool
    {
        $tenantId = TenantContext::getId();

        DB::table('group_tag_assignments')->where('tag_id', $tagId)->delete();
        return DB::table('group_tags')
            ->where('id', $tagId)
            ->where('tenant_id', $tenantId)
            ->delete() > 0;
    }

    /**
     * Get tags for a specific group.
     */
    public static function getForGroup(int $groupId): array
    {
        $tenantId = TenantContext::getId();

        return DB::table('group_tags as gt')
            ->join('group_tag_assignments as gta', 'gt.id', '=', 'gta.tag_id')
            ->where('gta.group_id', $groupId)
            ->where('gt.tenant_id', $tenantId)
            ->select('gt.*')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Set tags for a group (replaces existing).
     */
    public static function setForGroup(int $groupId, array $tagIds): void
    {
        $tenantId = TenantContext::getId();

        // Validate tags belong to tenant
        $validIds = DB::table('group_tags')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $tagIds)
            ->pluck('id')
            ->toArray();

        // Get current assignments
        $currentIds = DB::table('group_tag_assignments')
            ->where('group_id', $groupId)
            ->pluck('tag_id')
            ->toArray();

        $toAdd = array_diff($validIds, $currentIds);
        $toRemove = array_diff($currentIds, $validIds);

        // Remove old
        if (!empty($toRemove)) {
            DB::table('group_tag_assignments')
                ->where('group_id', $groupId)
                ->whereIn('tag_id', $toRemove)
                ->delete();

            DB::table('group_tags')
                ->whereIn('id', $toRemove)
                ->decrement('usage_count');
        }

        // Add new
        foreach ($toAdd as $tagId) {
            DB::table('group_tag_assignments')->insert([
                'group_id' => $groupId,
                'tag_id' => $tagId,
            ]);
        }

        if (!empty($toAdd)) {
            DB::table('group_tags')
                ->whereIn('id', $toAdd)
                ->increment('usage_count');
        }
    }

    /**
     * Find groups by tag.
     */
    public static function findGroupsByTag(int $tagId, int $limit = 20): array
    {
        $tenantId = TenantContext::getId();

        return DB::table('groups as g')
            ->join('group_tag_assignments as gta', 'g.id', '=', 'gta.group_id')
            ->where('gta.tag_id', $tagId)
            ->where('g.tenant_id', $tenantId)
            ->select('g.id', 'g.name', 'g.description', 'g.image_url', 'g.cached_member_count', 'g.visibility')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Suggest tags based on partial input.
     */
    public static function suggest(string $query, int $limit = 10): array
    {
        $tenantId = TenantContext::getId();

        return DB::table('group_tags')
            ->where('tenant_id', $tenantId)
            ->where('name', 'LIKE', '%' . $query . '%')
            ->orderByDesc('usage_count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }
}
