<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * VolunteerFormService - Custom fields, form values, and accessibility needs
 *
 * Manages dynamic form fields for volunteer applications and profiles:
 * - Custom field definitions (per-org or global)
 * - Custom field value storage for entities (applications, profiles, etc.)
 * - Accessibility needs tracking for volunteers
 *
 * Tables:
 *   vol_custom_fields — field definitions (text, select, checkbox, etc.)
 *   vol_custom_field_values — stored values for custom fields
 *   vol_accessibility_needs — per-user accessibility requirements
 */
class VolunteerFormService
{
    // =========================================================================
    // CUSTOM FIELD DEFINITIONS
    // =========================================================================

    /**
     * Get active custom fields, optionally filtered by organization and applies_to context
     *
     * @param int|null $organizationId Filter by organization (null = global fields only)
     * @param string $appliesTo Context: 'application', 'profile', 'shift', etc.
     * @return array List of custom field definitions ordered by display_order
     */
    public static function getCustomFields(?int $organizationId = null, string $appliesTo = 'application'): array
    {
        $tenantId = TenantContext::getId();

        try {
            $sql = "SELECT * FROM vol_custom_fields
                    WHERE tenant_id = ? AND applies_to = ? AND is_active = 1";
            $params = [$tenantId, $appliesTo];

            if ($organizationId !== null) {
                $sql .= " AND (organization_id = ? OR organization_id IS NULL)";
                $params[] = $organizationId;
            } else {
                $sql .= " AND organization_id IS NULL";
            }

            $sql .= " ORDER BY display_order ASC, id ASC";

            return Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("VolunteerFormService::getCustomFields error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create a new custom field definition
     *
     * @param array $data [field_key, field_label, field_type, applies_to, organization_id, is_required, field_options, display_order, placeholder, help_text]
     * @return array The created field record
     */
    public static function createField(array $data): array
    {
        $tenantId = TenantContext::getId();

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                "INSERT INTO vol_custom_fields
                    (tenant_id, organization_id, field_key, field_label, field_type,
                     applies_to, is_required, field_options, display_order,
                     placeholder, help_text, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())"
            );
            $stmt->execute([
                $tenantId,
                $data['organization_id'] ?? null,
                $data['field_key'] ?? ($data['field_name'] ?? ''),
                $data['field_label'] ?? '',
                $data['field_type'] ?? 'text',
                $data['applies_to'] ?? 'application',
                $data['is_required'] ?? 0,
                $data['field_options'] ?? ($data['options_json'] ?? null),
                $data['display_order'] ?? 0,
                $data['placeholder'] ?? null,
                $data['help_text'] ?? null,
            ]);

            $id = (int)$db->lastInsertId();

            $record = Database::query(
                "SELECT * FROM vol_custom_fields WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch(\PDO::FETCH_ASSOC);

            return $record ?: [];
        } catch (\Exception $e) {
            error_log("VolunteerFormService::createField error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update a custom field definition
     *
     * @param int $id Field ID
     * @param array $data Fields to update
     * @return bool
     */
    public static function updateField(int $id, array $data): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $sets = [];
            $params = [];

            $allowedFields = [
                'field_key', 'field_label', 'field_type', 'applies_to',
                'is_required', 'field_options', 'display_order', 'placeholder', 'help_text',
            ];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $sets[] = "{$field} = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($sets)) {
                return false;
            }

            $sets[] = "updated_at = NOW()";
            $params[] = $id;
            $params[] = $tenantId;

            $sql = "UPDATE vol_custom_fields SET " . implode(', ', $sets)
                 . " WHERE id = ? AND tenant_id = ?";

            Database::query($sql, $params);
            return true;
        } catch (\Exception $e) {
            error_log("VolunteerFormService::updateField error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Soft-delete a custom field (set is_active = 0)
     *
     * @param int $id Field ID
     * @return bool
     */
    public static function deleteField(int $id): bool
    {
        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "UPDATE vol_custom_fields SET is_active = 0, updated_at = NOW()
                 WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );
            return true;
        } catch (\Exception $e) {
            error_log("VolunteerFormService::deleteField error: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // CUSTOM FIELD VALUES
    // =========================================================================

    /**
     * Save (upsert) custom field values for a given entity
     *
     * @param string $entityType e.g. 'application', 'profile', 'shift'
     * @param int $entityId The entity ID (application_id, user_id, etc.)
     * @param array $values Associative array [field_id => value]
     */
    public static function saveFieldValues(string $entityType, int $entityId, array $values): void
    {
        $tenantId = TenantContext::getId();

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                "INSERT INTO vol_custom_field_values
                    (tenant_id, custom_field_id, entity_type, entity_id, field_value, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE field_value = VALUES(field_value), updated_at = NOW()"
            );

            foreach ($values as $fieldId => $value) {
                $stmt->execute([
                    $tenantId,
                    (int)$fieldId,
                    $entityType,
                    $entityId,
                    is_array($value) ? json_encode($value) : (string)$value,
                ]);
            }
        } catch (\Exception $e) {
            error_log("VolunteerFormService::saveFieldValues error: " . $e->getMessage());
        }
    }

    /**
     * Get all custom field values for a given entity
     *
     * @param string $entityType
     * @param int $entityId
     * @return array Associative array with field info and values
     */
    public static function getFieldValues(string $entityType, int $entityId): array
    {
        $tenantId = TenantContext::getId();

        try {
            return Database::query(
                "SELECT cfv.*, cf.field_key, cf.field_label, cf.field_type, cf.field_options
                 FROM vol_custom_field_values cfv
                 JOIN vol_custom_fields cf ON cfv.custom_field_id = cf.id
                 WHERE cfv.tenant_id = ? AND cfv.entity_type = ? AND cfv.entity_id = ?
                 ORDER BY cf.display_order ASC, cf.id ASC",
                [$tenantId, $entityType, $entityId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("VolunteerFormService::getFieldValues error: " . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // ACCESSIBILITY NEEDS
    // =========================================================================

    /**
     * Get all accessibility needs for a user
     *
     * @param int $userId
     * @return array List of accessibility need records
     */
    public static function getAccessibilityNeeds(int $userId): array
    {
        $tenantId = TenantContext::getId();

        try {
            return Database::query(
                "SELECT * FROM vol_accessibility_needs
                 WHERE user_id = ? AND tenant_id = ?
                 ORDER BY need_type ASC",
                [$userId, $tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("VolunteerFormService::getAccessibilityNeeds error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Replace all accessibility needs for a user (delete then insert within transaction)
     *
     * @param int $userId
     * @param array $needs Array of need records, each with [need_type, description, accommodations_required, emergency_contact_name, emergency_contact_phone]
     */
    public static function updateAccessibilityNeeds(int $userId, array $needs): void
    {
        $tenantId = TenantContext::getId();

        try {
            $db = Database::getConnection();
            $db->beginTransaction();

            // Delete existing needs for this user+tenant
            $db->prepare(
                "DELETE FROM vol_accessibility_needs WHERE user_id = ? AND tenant_id = ?"
            )->execute([$userId, $tenantId]);

            // Insert new needs
            if (!empty($needs)) {
                $stmt = $db->prepare(
                    "INSERT INTO vol_accessibility_needs
                        (user_id, tenant_id, need_type, description,
                         accommodations_required, emergency_contact_name,
                         emergency_contact_phone, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
                );

                foreach ($needs as $need) {
                    $stmt->execute([
                        $userId,
                        $tenantId,
                        $need['need_type'] ?? '',
                        $need['description'] ?? null,
                        $need['accommodations_required'] ?? null,
                        $need['emergency_contact_name'] ?? null,
                        $need['emergency_contact_phone'] ?? null,
                    ]);
                }
            }

            $db->commit();
        } catch (\Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("VolunteerFormService::updateAccessibilityNeeds error: " . $e->getMessage());
        }
    }
}
