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
 * GroupCustomFieldService — Tenant-defined metadata fields for groups.
 */
class GroupCustomFieldService
{
    /**
     * Get all custom fields for the tenant.
     */
    public static function getFields(): array
    {
        $tenantId = TenantContext::getId();

        return DB::table('group_custom_fields')
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($row) {
                $row->options = json_decode($row->options, true);
                return (array) $row;
            })
            ->toArray();
    }

    /**
     * Create a custom field.
     */
    public static function createField(array $data): int
    {
        $tenantId = TenantContext::getId();

        return DB::table('group_custom_fields')->insertGetId([
            'tenant_id' => $tenantId,
            'field_name' => $data['field_name'],
            'field_key' => $data['field_key'] ?? Str::slug($data['field_name'], '_'),
            'field_type' => $data['field_type'] ?? 'text',
            'options' => isset($data['options']) ? json_encode($data['options']) : null,
            'is_required' => $data['is_required'] ?? false,
            'is_searchable' => $data['is_searchable'] ?? false,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_at' => now(),
        ]);
    }

    /**
     * Delete a custom field and its values.
     */
    public static function deleteField(int $fieldId): bool
    {
        $tenantId = TenantContext::getId();

        DB::table('group_custom_field_values')->where('field_id', $fieldId)->delete();
        return DB::table('group_custom_fields')
            ->where('id', $fieldId)
            ->where('tenant_id', $tenantId)
            ->delete() > 0;
    }

    /**
     * Get custom field values for a group.
     */
    public static function getValues(int $groupId): array
    {
        $tenantId = TenantContext::getId();

        return DB::table('group_custom_field_values as v')
            ->join('group_custom_fields as f', 'v.field_id', '=', 'f.id')
            ->where('v.group_id', $groupId)
            ->where('f.tenant_id', $tenantId)
            ->select('f.field_key', 'f.field_name', 'f.field_type', 'v.field_value')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Set custom field values for a group.
     */
    public static function setValues(int $groupId, array $values): void
    {
        $tenantId = TenantContext::getId();

        // Get valid fields
        $fields = DB::table('group_custom_fields')
            ->where('tenant_id', $tenantId)
            ->pluck('id', 'field_key')
            ->toArray();

        foreach ($values as $key => $value) {
            if (!isset($fields[$key])) continue;

            DB::table('group_custom_field_values')->updateOrInsert(
                ['group_id' => $groupId, 'field_id' => $fields[$key]],
                ['field_value' => is_array($value) ? json_encode($value) : (string) $value]
            );
        }
    }
}
