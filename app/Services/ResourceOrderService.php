<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ResourceOrderService — Laravel DI wrapper for legacy \Nexus\Services\ResourceOrderService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ResourceOrderService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ResourceOrderService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\ResourceOrderService::getErrors();
    }

    /**
     * Delegates to legacy ResourceOrderService::reorder().
     */
    public function reorder(array $items): bool
    {
        return \Nexus\Services\ResourceOrderService::reorder($items);
    }
}
