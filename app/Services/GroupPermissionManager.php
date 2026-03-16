<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GroupPermissionManager — Laravel DI wrapper for legacy \Nexus\Services\GroupPermissionManager.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GroupPermissionManager
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GroupPermissionManager::hasPermission().
     */
    public function hasPermission($userId, $permission)
    {
        return \Nexus\Services\GroupPermissionManager::hasPermission($userId, $permission);
    }

    /**
     * Delegates to legacy GroupPermissionManager::hasGroupPermission().
     */
    public function hasGroupPermission($groupId, $userId, $permission)
    {
        return \Nexus\Services\GroupPermissionManager::hasGroupPermission($groupId, $userId, $permission);
    }

    /**
     * Delegates to legacy GroupPermissionManager::getUserGroupRole().
     */
    public function getUserGroupRole($groupId, $userId)
    {
        return \Nexus\Services\GroupPermissionManager::getUserGroupRole($groupId, $userId);
    }

    /**
     * Delegates to legacy GroupPermissionManager::isGlobalAdmin().
     */
    public function isGlobalAdmin($userId)
    {
        return \Nexus\Services\GroupPermissionManager::isGlobalAdmin($userId);
    }

    /**
     * Delegates to legacy GroupPermissionManager::canCreateHub().
     */
    public function canCreateHub($userId)
    {
        return \Nexus\Services\GroupPermissionManager::canCreateHub($userId);
    }
}
