<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * GroupCollectionService — Group bundles/collections for organized discovery.
 */
class GroupCollectionService
{
    public static function getAll(): array
    {
        $tenantId = TenantContext::getId();
        $collections = DB::table('group_collections')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->toArray();

        foreach ($collections as &$col) {
            $col = (array) $col;
            $col['groups'] = DB::table('group_collection_items as gci')
                ->join('groups as g', 'gci.group_id', '=', 'g.id')
                ->where('gci.collection_id', $col['id'])
                ->where('g.tenant_id', $tenantId)
                ->where('g.is_active', true)
                ->select('g.id', 'g.name', 'g.image_url', 'g.cached_member_count', 'g.visibility')
                ->orderBy('gci.sort_order')
                ->get()
                ->map(fn ($r) => (array) $r)
                ->toArray();
            $col['group_count'] = count($col['groups']);
        }

        return $collections;
    }

    public static function get(int $id): ?array
    {
        $tenantId = TenantContext::getId();
        $col = DB::table('group_collections')
            ->where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$col) return null;
        $col = (array) $col;
        $col['groups'] = DB::table('group_collection_items as gci')
            ->join('groups as g', 'gci.group_id', '=', 'g.id')
            ->where('gci.collection_id', $id)
            ->where('g.tenant_id', $tenantId)
            ->select('g.id', 'g.name', 'g.description', 'g.image_url', 'g.cached_member_count', 'g.visibility')
            ->orderBy('gci.sort_order')
            ->get()->map(fn ($r) => (array) $r)->toArray();
        return $col;
    }

    public static function create(array $data, int $userId): int
    {
        $tenantId = TenantContext::getId();
        return DB::table('group_collections')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function update(int $id, array $data): bool
    {
        $tenantId = TenantContext::getId();
        $update = ['updated_at' => now()];
        foreach (['name', 'description', 'image_url', 'sort_order', 'is_active'] as $f) {
            if (array_key_exists($f, $data)) $update[$f] = $data[$f];
        }
        return DB::table('group_collections')
            ->where('id', $id)->where('tenant_id', $tenantId)
            ->update($update) > 0;
    }

    public static function delete(int $id): bool
    {
        $tenantId = TenantContext::getId();
        DB::table('group_collection_items')->where('collection_id', $id)->delete();
        return DB::table('group_collections')
            ->where('id', $id)->where('tenant_id', $tenantId)->delete() > 0;
    }

    public static function setGroups(int $collectionId, array $groupIds): void
    {
        DB::table('group_collection_items')->where('collection_id', $collectionId)->delete();
        foreach ($groupIds as $i => $gid) {
            DB::table('group_collection_items')->insert([
                'collection_id' => $collectionId,
                'group_id' => $gid,
                'sort_order' => $i,
            ]);
        }
    }
}
