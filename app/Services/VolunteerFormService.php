<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

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
}
