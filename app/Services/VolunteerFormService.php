<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\VolAccessibilityNeed;
use App\Models\VolCustomField;
use App\Models\VolCustomFieldValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * VolunteerFormService — custom fields, form values, and accessibility needs.
 *
 * Manages dynamic form fields for volunteer applications and profiles,
 * custom field value storage for entities, and accessibility needs tracking.
 *
 * All queries are tenant-scoped automatically via the HasTenantScope trait on models.
 */
class VolunteerFormService
{
    public function __construct()
    {
    }

    /**
     * Get active custom fields, optionally filtered by organization and applies_to context.
     *
     * @param int|null $organizationId Filter by organization (null = global fields only)
     * @param string $appliesTo Context: 'application', 'profile', 'shift', etc.
     * @return array List of custom field definitions ordered by display_order
     */
    public static function getCustomFields(?int $organizationId = null, string $appliesTo = 'application'): array
    {
        try {
            $query = VolCustomField::where('applies_to', $appliesTo)
                ->where('is_active', true);

            if ($organizationId !== null) {
                $query->where(function ($q) use ($organizationId) {
                    $q->where('organization_id', $organizationId)
                        ->orWhereNull('organization_id');
                });
            } else {
                $query->whereNull('organization_id');
            }

            return $query->orderBy('display_order')
                ->orderBy('id')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            Log::error('VolunteerFormService::getCustomFields error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Create a new custom field definition.
     *
     * @param array $data Field definition data
     * @return array The created field record
     */
    public static function createField(array $data): array
    {
        try {
            $field = VolCustomField::create([
                'tenant_id' => TenantContext::getId(),
                'organization_id' => $data['organization_id'] ?? null,
                'field_key' => $data['field_key'] ?? ($data['field_name'] ?? ''),
                'field_label' => $data['field_label'] ?? '',
                'field_type' => $data['field_type'] ?? 'text',
                'applies_to' => $data['applies_to'] ?? 'application',
                'is_required' => $data['is_required'] ?? 0,
                'field_options' => $data['field_options'] ?? ($data['options_json'] ?? null),
                'display_order' => $data['display_order'] ?? 0,
                'placeholder' => $data['placeholder'] ?? null,
                'help_text' => $data['help_text'] ?? null,
                'is_active' => 1,
            ]);

            return $field->fresh()->toArray();
        } catch (\Exception $e) {
            Log::error('VolunteerFormService::createField error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Update a custom field definition.
     *
     * @param int $id Field ID
     * @param array $data Fields to update
     * @return bool
     */
    public static function updateField(int $id, array $data): bool
    {
        try {
            $field = VolCustomField::find($id);
            if (!$field) {
                return false;
            }

            $allowedFields = [
                'field_key', 'field_label', 'field_type', 'applies_to',
                'is_required', 'field_options', 'display_order', 'placeholder', 'help_text',
            ];

            $updates = [];
            foreach ($allowedFields as $fieldName) {
                if (array_key_exists($fieldName, $data)) {
                    $updates[$fieldName] = $data[$fieldName];
                }
            }

            if (empty($updates)) {
                return false;
            }

            return $field->update($updates);
        } catch (\Exception $e) {
            Log::error('VolunteerFormService::updateField error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Soft-delete a custom field (set is_active = 0).
     *
     * @param int $id Field ID
     * @return bool
     */
    public static function deleteField(int $id): bool
    {
        try {
            $field = VolCustomField::find($id);
            if (!$field) {
                return false;
            }

            return $field->update(['is_active' => 0]);
        } catch (\Exception $e) {
            Log::error('VolunteerFormService::deleteField error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Save (upsert) custom field values for a given entity.
     *
     * @param string $entityType e.g. 'application', 'profile', 'shift'
     * @param int $entityId The entity ID
     * @param array $values Associative array [field_id => value]
     */
    public static function saveFieldValues(string $entityType, int $entityId, array $values): void
    {
        $tenantId = TenantContext::getId();

        try {
            foreach ($values as $fieldId => $value) {
                VolCustomFieldValue::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'custom_field_id' => (int) $fieldId,
                        'entity_type' => $entityType,
                        'entity_id' => $entityId,
                    ],
                    [
                        'field_value' => is_array($value) ? json_encode($value) : (string) $value,
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::error('VolunteerFormService::saveFieldValues error: ' . $e->getMessage());
        }
    }

    /**
     * Get accessibility needs for a user within a tenant.
     *
     * @param int $userId
     * @param int $tenantId
     * @return array List of accessibility need records
     */
    public static function getAccessibilityNeeds(int $userId, int $tenantId): array
    {
        try {
            return VolAccessibilityNeed::where('user_id', $userId)
                ->orderBy('need_type')
                ->get()
                ->map(fn ($row) => $row->toArray())
                ->toArray();
        } catch (\Exception $e) {
            Log::error('VolunteerFormService::getAccessibilityNeeds error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Replace all accessibility needs for a user (delete then insert within transaction).
     *
     * @param int $userId
     * @param array $data Array of need records
     * @param int $tenantId
     * @return bool True on success
     */
    public static function updateAccessibilityNeeds(int $userId, array $data, int $tenantId): bool
    {
        try {
            DB::transaction(function () use ($userId, $data, $tenantId) {
                // Delete existing needs for this user
                VolAccessibilityNeed::where('user_id', $userId)->delete();

                // Insert new needs
                if (!empty($data)) {
                    foreach ($data as $need) {
                        VolAccessibilityNeed::create([
                            'tenant_id' => $tenantId,
                            'user_id' => $userId,
                            'need_type' => $need['need_type'] ?? '',
                            'description' => $need['description'] ?? null,
                            'accommodations_required' => $need['accommodations_required'] ?? null,
                            'emergency_contact_name' => $need['emergency_contact_name'] ?? null,
                            'emergency_contact_phone' => $need['emergency_contact_phone'] ?? null,
                        ]);
                    }
                }
            });

            return true;
        } catch (\Exception $e) {
            Log::error('VolunteerFormService::updateAccessibilityNeeds error: ' . $e->getMessage());
            return false;
        }
    }
}
