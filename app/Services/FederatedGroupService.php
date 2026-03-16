<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * FederatedGroupService — Laravel DI wrapper for legacy \Nexus\Services\FederatedGroupService.
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
        return \Nexus\Services\FederatedGroupService::getPartnerGroups($tenantId, $page, $perPage, $search, $partnerTenantId);
    }

    /**
     * Delegates to legacy FederatedGroupService::getPartnerGroup().
     */
    public function getPartnerGroup(int $groupId, int $groupTenantId, int $userTenantId): ?array
    {
        return \Nexus\Services\FederatedGroupService::getPartnerGroup($groupId, $groupTenantId, $userTenantId);
    }

    /**
     * Delegates to legacy FederatedGroupService::joinGroup().
     */
    public function joinGroup(int $userId, int $userTenantId, int $groupId, int $groupTenantId): array
    {
        return \Nexus\Services\FederatedGroupService::joinGroup($userId, $userTenantId, $groupId, $groupTenantId);
    }

    /**
     * Delegates to legacy FederatedGroupService::leaveGroup().
     */
    public function leaveGroup(int $userId, int $userTenantId, int $groupId): array
    {
        return \Nexus\Services\FederatedGroupService::leaveGroup($userId, $userTenantId, $groupId);
    }

    /**
     * Delegates to legacy FederatedGroupService::isFederatedMember().
     */
    public function isFederatedMember(int $userId, int $userTenantId, int $groupId): ?array
    {
        return \Nexus\Services\FederatedGroupService::isFederatedMember($userId, $userTenantId, $groupId);
    }
}
