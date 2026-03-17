<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * VolunteerFormService — Laravel DI wrapper for legacy \Nexus\Services\VolunteerFormService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class VolunteerFormService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy VolunteerFormService::getCustomFields().
     */
    public function getCustomFields(?int $organizationId = null, string $appliesTo = 'application'): array
    {
        return \Nexus\Services\VolunteerFormService::getCustomFields($organizationId, $appliesTo);
    }

    /**
     * Delegates to legacy VolunteerFormService::createField().
     */
    public function createField(array $data): array
    {
        return \Nexus\Services\VolunteerFormService::createField($data);
    }

    /**
     * Delegates to legacy VolunteerFormService::updateField().
     */
    public function updateField(int $id, array $data): bool
    {
        return \Nexus\Services\VolunteerFormService::updateField($id, $data);
    }

    /**
     * Delegates to legacy VolunteerFormService::deleteField().
     */
    public function deleteField(int $id): bool
    {
        return \Nexus\Services\VolunteerFormService::deleteField($id);
    }

    /**
     * Delegates to legacy VolunteerFormService::saveFieldValues().
     */
    public function saveFieldValues(string $entityType, int $entityId, array $values): void
    {
        \Nexus\Services\VolunteerFormService::saveFieldValues($entityType, $entityId, $values);
    }

    /**
     * Get accessibility needs for a user within a tenant.
     *
     * @param int $userId
     * @param int $tenantId
     * @return array List of accessibility need records
     */
    public function getAccessibilityNeeds(int $userId, int $tenantId): array
    {
        try {
            $results = DB::select(
                "SELECT * FROM vol_accessibility_needs
                 WHERE user_id = ? AND tenant_id = ?
                 ORDER BY need_type ASC",
                [$userId, $tenantId]
            );

            return array_map(fn ($row) => (array) $row, $results);
        } catch (\Exception $e) {
            Log::error("VolunteerFormService::getAccessibilityNeeds error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Replace all accessibility needs for a user (delete then insert within transaction).
     *
     * @param int $userId
     * @param array $data Array of need records, each with need_type, description, accommodations_required, emergency_contact_name, emergency_contact_phone
     * @param int $tenantId
     * @return bool True on success
     */
    public function updateAccessibilityNeeds(int $userId, array $data, int $tenantId): bool
    {
        try {
            DB::transaction(function () use ($userId, $data, $tenantId) {
                // Delete existing needs for this user+tenant
                DB::delete(
                    "DELETE FROM vol_accessibility_needs WHERE user_id = ? AND tenant_id = ?",
                    [$userId, $tenantId]
                );

                // Insert new needs
                if (!empty($data)) {
                    $now = date('Y-m-d H:i:s');
                    foreach ($data as $need) {
                        DB::insert(
                            "INSERT INTO vol_accessibility_needs
                                (user_id, tenant_id, need_type, description,
                                 accommodations_required, emergency_contact_name,
                                 emergency_contact_phone, created_at, updated_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                            [
                                $userId,
                                $tenantId,
                                $need['need_type'] ?? '',
                                $need['description'] ?? null,
                                $need['accommodations_required'] ?? null,
                                $need['emergency_contact_name'] ?? null,
                                $need['emergency_contact_phone'] ?? null,
                                $now,
                                $now,
                            ]
                        );
                    }
                }
            });

            return true;
        } catch (\Exception $e) {
            Log::error("VolunteerFormService::updateAccessibilityNeeds error: " . $e->getMessage());
            return false;
        }
    }
}
