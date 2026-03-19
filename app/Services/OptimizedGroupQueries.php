<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * OptimizedGroupQueries — Laravel DI wrapper for legacy \Nexus\Services\OptimizedGroupQueries.
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
    public function getLeafGroups($tenantId = null, $typeId = null, $limit = 100)
    {
        return \Nexus\Services\OptimizedGroupQueries::getLeafGroups($tenantId, $typeId, $limit);
    }

    /**
     * Delegates to legacy OptimizedGroupQueries::getGroupHierarchyTree().
     */
    public function getGroupHierarchyTree($groupId)
    {
        return \Nexus\Services\OptimizedGroupQueries::getGroupHierarchyTree($groupId);
    }

    /**
     * Delegates to legacy OptimizedGroupQueries::getAncestors().
     */
    public function getAncestors($groupId)
    {
        return \Nexus\Services\OptimizedGroupQueries::getAncestors($groupId);
    }

    /**
     * Delegates to legacy OptimizedGroupQueries::getDescendants().
     */
    public function getDescendants($groupId, $maxDepth = null)
    {
        return \Nexus\Services\OptimizedGroupQueries::getDescendants($groupId, $maxDepth);
    }

    /**
     * Delegates to legacy OptimizedGroupQueries::getGroupDepth().
     */
    public function getGroupDepth($groupId)
    {
        return \Nexus\Services\OptimizedGroupQueries::getGroupDepth($groupId);
    }
}
