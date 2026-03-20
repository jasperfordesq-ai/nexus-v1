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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupPermissionManager::hasGroupPermission().
     */
    public function hasGroupPermission($groupId, $userId, $permission)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupPermissionManager::getUserGroupRole().
     */
    public function getUserGroupRole($groupId, $userId)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupPermissionManager::isGlobalAdmin().
     */
    public function isGlobalAdmin($userId)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupPermissionManager::canCreateHub().
     */
    public function canCreateHub($userId)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }
}
