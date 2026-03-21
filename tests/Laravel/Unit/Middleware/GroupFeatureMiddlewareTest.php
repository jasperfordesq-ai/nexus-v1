<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Middleware;

use App\Core\TenantContext;
use App\Middleware\GroupFeatureMiddleware;
use Tests\Laravel\TestCase;

/**
 * Tests for GroupFeatureMiddleware.
 *
 * This middleware uses static methods that delegate to GroupFeatureToggleService.
 * Since GroupFeatureToggleService is a legacy class that may not be available
 * in all environments, these tests verify the middleware's own logic where possible
 * and are marked as skipped when the dependency is unavailable.
 */
class GroupFeatureMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\App\Services\GroupFeatureToggleService::class)) {
            $this->markTestSkipped('GroupFeatureToggleService not available (legacy class removed during migration)');
        }
    }

    public function test_checkGroupsEnabled_returns_bool_or_array(): void
    {
        $result = GroupFeatureMiddleware::checkGroupsEnabled();

        $this->assertTrue(
            $result === true || is_array($result),
            'checkGroupsEnabled should return true or an error array'
        );
    }

    public function test_checkGroupsEnabled_error_array_structure(): void
    {
        $result = GroupFeatureMiddleware::checkGroupsEnabled();

        if (is_array($result)) {
            $this->assertArrayHasKey('error', $result);
            $this->assertArrayHasKey('message', $result);
            $this->assertArrayHasKey('redirect', $result);
            $this->assertTrue($result['error']);
        } else {
            $this->assertTrue($result);
        }
    }

    public function test_checkFeatures_returns_true_or_error_for_empty_array(): void
    {
        $result = GroupFeatureMiddleware::checkFeatures([]);
        $this->assertTrue($result);
    }

    public function test_checkAnyFeature_returns_false_for_empty_array(): void
    {
        $result = GroupFeatureMiddleware::checkAnyFeature([]);
        $this->assertFalse($result);
    }

    public function test_can_returns_boolean(): void
    {
        if (defined('\App\Services\GroupFeatureToggleService::FEATURE_GROUPS_MODULE')) {
            $result = GroupFeatureMiddleware::can(\App\Services\GroupFeatureToggleService::FEATURE_GROUPS_MODULE);
            $this->assertIsBool($result);
        } else {
            $this->markTestSkipped('FEATURE_GROUPS_MODULE constant not defined');
        }
    }

    public function test_gates_returns_associative_array(): void
    {
        $result = GroupFeatureMiddleware::gates([]);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
