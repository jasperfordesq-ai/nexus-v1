<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;

/**
 * Smoke test for ResourcesController.
 *
 * This controller currently has no registered routes in routes/api.php
 * (resources are routed through ResourcePublicController / ResourceCategoryController).
 * Placeholder test verifies the class exists and is loadable.
 */
class ResourcesControllerTest extends TestCase
{
    public function test_controller_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\Api\ResourcesController::class));
    }
}
