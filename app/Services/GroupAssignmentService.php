<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GroupAssignmentService — Laravel DI wrapper for legacy \Nexus\Services\GroupAssignmentService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GroupAssignmentService
{
    public function __construct()
    {
    }

    /**
     * Forward any method call to the legacy static service.
     *
     * @param  string  $method
     * @param  array   $args
     * @return mixed
     */
    public function __call(string $method, array $args): mixed
    {
        return \Nexus\Services\GroupAssignmentService::$method(...$args);
    }
}
