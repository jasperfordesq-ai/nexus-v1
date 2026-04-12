<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Smoke tests for GroupRecommendationsController.
 *
 * NOTE: This controller class exists but has no registered routes in routes/api.php.
 * Routing is handled by the distinct GroupRecommendController (already tested).
 * Placeholder test verifies the class is loadable.
 */
class GroupRecommendationsControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\Api\GroupRecommendationsController::class));
    }
}
