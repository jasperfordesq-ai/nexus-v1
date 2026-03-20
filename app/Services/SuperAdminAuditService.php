<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy SuperAdminAuditService::getLog().
     */
    public function getLog(array $filters = []): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy SuperAdminAuditService::getStats().
     */
    public function getStats(int $days = 30): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy SuperAdminAuditService::getActionLabel().
     */
    public function getActionLabel(string $actionType): string
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return '';
    }

    /**
     * Delegates to legacy SuperAdminAuditService::getActionIcon().
     */
    public function getActionIcon(string $actionType): string
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return '';
    }
}
