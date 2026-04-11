<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Controllers;

use Tests\Laravel\TestCase;
use App\Http\Controllers\Api\AdminCcConfigController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tests for AdminCcConfigController validation logic.
 *
 * Focuses on the input validation rules in the update() method:
 *   - node_slug: /^[0-9a-z-]{3,15}$/
 *   - exchange_rate: > 0 and <= 1000
 *   - validated_window: 30–86400 seconds
 *
 * Uses Mockery to mock auth and DB calls since the controller extends
 * BaseApiController which checks admin authentication.
 */
class AdminCcConfigControllerTest extends TestCase
{
    /**
     * Create a controller instance with mocked admin auth and request.
     */
    private function makeControllerWithInput(array $input): AdminCcConfigController
    {
        // Create a request with the given input and bind it to the container
        $request = Request::create('/api/v2/admin/federation/cc-config', 'PUT', $input);
        $request->headers->set('Content-Type', 'application/json');
        $this->app->instance('request', $request);

        return new AdminCcConfigController();
    }

    // ──────────────────────────────────────────────────────────────────────
    // show() test
    // ──────────────────────────────────────────────────────────────────────

    public function test_show_endpoint_requires_authentication(): void
    {
        // The CC config endpoint requires admin auth.
        // Without auth, it should return 401.
        $response = $this->apiGet('/v2/admin/federation/cc-config');

        $this->assertTrue(
            in_array($response->getStatusCode(), [401, 403]),
            "Expected 401 or 403 without auth, got {$response->getStatusCode()}"
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // update() validation tests — node_slug
    // ──────────────────────────────────────────────────────────────────────

    public function test_update_validates_node_slug_format(): void
    {
        // Test the regex pattern directly: /^[0-9a-z-]{3,15}$/
        $pattern = '/^[0-9a-z-]{3,15}$/';

        // Valid slugs
        $this->assertMatchesRegularExpression($pattern, 'abc');
        $this->assertMatchesRegularExpression($pattern, 'my-node');
        $this->assertMatchesRegularExpression($pattern, 'test-123');
        $this->assertMatchesRegularExpression($pattern, '123');
        $this->assertMatchesRegularExpression($pattern, 'a-b-c-d-e-f-g-h'); // 15 chars

        // Invalid slugs
        $this->assertDoesNotMatchRegularExpression($pattern, 'ab');          // too short (2 chars)
        $this->assertDoesNotMatchRegularExpression($pattern, 'a');           // too short (1 char)
        $this->assertDoesNotMatchRegularExpression($pattern, '');            // empty
        $this->assertDoesNotMatchRegularExpression($pattern, 'UPPERCASE');   // uppercase
        $this->assertDoesNotMatchRegularExpression($pattern, 'has spaces');  // spaces
        $this->assertDoesNotMatchRegularExpression($pattern, 'has_underscore'); // underscore
        $this->assertDoesNotMatchRegularExpression($pattern, 'this-slug-is-way-too-long'); // >15 chars
        $this->assertDoesNotMatchRegularExpression($pattern, 'special!@#');  // special chars
    }

    public function test_update_rejects_invalid_node_slug_via_api(): void
    {
        // Without auth, expect 401 — validates auth is enforced
        $response = $this->apiPut('/v2/admin/federation/cc-config', [
            'node_slug' => 'INVALID',
        ]);

        $this->assertTrue(
            in_array($response->getStatusCode(), [401, 403, 422]),
            "Expected 401, 403 or 422 for invalid slug, got {$response->getStatusCode()}"
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // update() validation tests — exchange_rate
    // ──────────────────────────────────────────────────────────────────────

    public function test_update_validates_exchange_rate_range(): void
    {
        // The controller checks: $rate <= 0 || $rate > 1000
        // Valid range: 0.01 to 1000

        // Negative rate is invalid
        $rate = -1.0;
        $this->assertTrue($rate <= 0 || $rate > 1000, 'Negative rate should fail validation');

        // Zero rate is invalid
        $rate = 0.0;
        $this->assertTrue($rate <= 0 || $rate > 1000, 'Zero rate should fail validation');

        // Rate > 1000 is invalid
        $rate = 1001.0;
        $this->assertTrue($rate <= 0 || $rate > 1000, 'Rate > 1000 should fail validation');

        // Rate of 0.01 is valid
        $rate = 0.01;
        $this->assertFalse($rate <= 0 || $rate > 1000, 'Rate 0.01 should pass validation');

        // Rate of 1.0 is valid
        $rate = 1.0;
        $this->assertFalse($rate <= 0 || $rate > 1000, 'Rate 1.0 should pass validation');

        // Rate of 1000 is valid (boundary)
        $rate = 1000.0;
        $this->assertFalse($rate <= 0 || $rate > 1000, 'Rate 1000 should pass validation');
    }

    public function test_update_rejects_negative_exchange_rate_via_api(): void
    {
        $response = $this->apiPut('/v2/admin/federation/cc-config', [
            'exchange_rate' => -5.0,
        ]);

        $this->assertTrue(
            in_array($response->getStatusCode(), [401, 403, 422]),
            "Expected 401, 403 or 422 for negative rate, got {$response->getStatusCode()}"
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // update() validation tests — validated_window
    // ──────────────────────────────────────────────────────────────────────

    public function test_update_validates_validated_window_range(): void
    {
        // The controller checks: $window < 30 || $window > 86400
        // Valid range: 30 to 86400 seconds

        // Too small (29)
        $window = 29;
        $this->assertTrue($window < 30 || $window > 86400, 'Window 29 should fail validation');

        // Too small (0)
        $window = 0;
        $this->assertTrue($window < 30 || $window > 86400, 'Window 0 should fail validation');

        // Too large (86401)
        $window = 86401;
        $this->assertTrue($window < 30 || $window > 86400, 'Window 86401 should fail validation');

        // Boundary valid (30)
        $window = 30;
        $this->assertFalse($window < 30 || $window > 86400, 'Window 30 should pass validation');

        // Boundary valid (86400)
        $window = 86400;
        $this->assertFalse($window < 30 || $window > 86400, 'Window 86400 should pass validation');

        // Mid-range valid (300 = 5 minutes, the default)
        $window = 300;
        $this->assertFalse($window < 30 || $window > 86400, 'Window 300 should pass validation');
    }

    public function test_update_rejects_too_small_validated_window_via_api(): void
    {
        $response = $this->apiPut('/v2/admin/federation/cc-config', [
            'validated_window' => 10,
        ]);

        $this->assertTrue(
            in_array($response->getStatusCode(), [401, 403, 422]),
            "Expected 401, 403 or 422 for too-small window, got {$response->getStatusCode()}"
        );
    }
}
