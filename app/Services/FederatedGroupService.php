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
class FederatedGroupService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederatedGroupService::getPartnerGroups().
     */
    public function getPartnerGroups(int $tenantId, int $page = 1, int $perPage = 12, ?string $search = null, ?int $partnerTenantId = null): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederatedGroupService::getPartnerGroup().
     */
    public function getPartnerGroup(int $groupId, int $groupTenantId, int $userTenantId): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy FederatedGroupService::joinGroup().
     */
    public function joinGroup(int $userId, int $userTenantId, int $groupId, int $groupTenantId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederatedGroupService::leaveGroup().
     */
    public function leaveGroup(int $userId, int $userTenantId, int $groupId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederatedGroupService::isFederatedMember().
     */
    public function isFederatedMember(int $userId, int $userTenantId, int $groupId): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }
}
