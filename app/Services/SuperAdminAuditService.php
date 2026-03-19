<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * SuperAdminAuditService — Laravel DI wrapper for legacy \Nexus\Services\SuperAdminAuditService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class SuperAdminAuditService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy SuperAdminAuditService::log().
     */
    public function log(string $actionType, string $targetType, ?int $targetId = null, ?string $targetName = null, ?array $oldValues = null, ?array $newValues = null, ?string $description = null): bool
    {
        return \Nexus\Services\SuperAdminAuditService::log($actionType, $targetType, $targetId, $targetName, $oldValues, $newValues, $description);
    }

    /**
     * Delegates to legacy SuperAdminAuditService::getLog().
     */
    public function getLog(array $filters = []): array
    {
        return \Nexus\Services\SuperAdminAuditService::getLog($filters);
    }

    /**
     * Delegates to legacy SuperAdminAuditService::getStats().
     */
    public function getStats(int $days = 30): array
    {
        return \Nexus\Services\SuperAdminAuditService::getStats($days);
    }

    /**
     * Delegates to legacy SuperAdminAuditService::getActionLabel().
     */
    public function getActionLabel(string $actionType): string
    {
        return \Nexus\Services\SuperAdminAuditService::getActionLabel($actionType);
    }

    /**
     * Delegates to legacy SuperAdminAuditService::getActionIcon().
     */
    public function getActionIcon(string $actionType): string
    {
        return \Nexus\Services\SuperAdminAuditService::getActionIcon($actionType);
    }
}
