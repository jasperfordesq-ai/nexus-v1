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
class OptimizedGroupQueries
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy OptimizedGroupQueries::getLeafGroups().
     */
    public static function getLeafGroups($tenantId = null, $typeId = null, $limit = 100)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy OptimizedGroupQueries::getGroupHierarchyTree().
     */
    public static function getGroupHierarchyTree($groupId)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy OptimizedGroupQueries::getAncestors().
     */
    public static function getAncestors($groupId)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy OptimizedGroupQueries::getDescendants().
     */
    public static function getDescendants($groupId, $maxDepth = null)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy OptimizedGroupQueries::getGroupDepth().
     */
    public static function getGroupDepth($groupId)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }
}
