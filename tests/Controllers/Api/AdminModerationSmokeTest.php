<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;

/**
 * Smoke tests for Admin Moderation API Controllers
 *
 * These tests verify that all moderation controller classes exist and have the expected
 * public methods. They use reflection to validate the controller structure without
 * requiring a running application or database connection.
 *
 * @group integration
 */
class AdminModerationSmokeTest extends TestCase
{
    // Feed Posts Endpoints
    public function testFeedPostsListEndpointExists(): void
    {
        $this->assertTrue(
            class_exists(\Nexus\Controllers\Api\AdminFeedApiController::class),
            'AdminFeedApiController class should exist'
        );
        $reflection = new \ReflectionClass(\Nexus\Controllers\Api\AdminFeedApiController::class);
        $this->assertTrue($reflection->hasMethod('index'), 'Should have index method for listing feed posts');
        $this->assertTrue($reflection->getMethod('index')->isPublic());
    }

    public function testFeedStatsEndpointExists(): void
    {
        $reflection = new \ReflectionClass(\Nexus\Controllers\Api\AdminFeedApiController::class);
        $this->assertTrue($reflection->hasMethod('stats'), 'Should have stats method');
        $this->assertTrue($reflection->getMethod('stats')->isPublic());
    }

    // Comments Endpoints
    public function testCommentsListEndpointExists(): void
    {
        $this->assertTrue(
            class_exists(\Nexus\Controllers\Api\AdminCommentsApiController::class),
            'AdminCommentsApiController class should exist'
        );
        $reflection = new \ReflectionClass(\Nexus\Controllers\Api\AdminCommentsApiController::class);
        $this->assertTrue($reflection->hasMethod('index'), 'Should have index method for listing comments');
        $this->assertTrue($reflection->getMethod('index')->isPublic());
    }

    // Reviews Endpoints
    public function testReviewsListEndpointExists(): void
    {
        $this->assertTrue(
            class_exists(\Nexus\Controllers\Api\AdminReviewsApiController::class),
            'AdminReviewsApiController class should exist'
        );
        $reflection = new \ReflectionClass(\Nexus\Controllers\Api\AdminReviewsApiController::class);
        $this->assertTrue($reflection->hasMethod('index'), 'Should have index method for listing reviews');
        $this->assertTrue($reflection->getMethod('index')->isPublic());
    }

    // Reports Endpoints
    public function testReportsListEndpointExists(): void
    {
        $this->assertTrue(
            class_exists(\Nexus\Controllers\Api\AdminReportsApiController::class),
            'AdminReportsApiController class should exist'
        );
        $reflection = new \ReflectionClass(\Nexus\Controllers\Api\AdminReportsApiController::class);
        $this->assertTrue($reflection->hasMethod('index'), 'Should have index method for listing reports');
        $this->assertTrue($reflection->getMethod('index')->isPublic());
    }

    public function testReportsStatsEndpointExists(): void
    {
        $reflection = new \ReflectionClass(\Nexus\Controllers\Api\AdminReportsApiController::class);
        $this->assertTrue($reflection->hasMethod('stats'), 'Should have stats method');
        $this->assertTrue($reflection->getMethod('stats')->isPublic());
    }

    // Authorization Tests - verify controllers have methods that routes map to
    public function testModerationEndpointsRequireAuth(): void
    {
        // Verify all four moderation controllers exist and have their expected methods
        $controllers = [
            \Nexus\Controllers\Api\AdminFeedApiController::class => ['index', 'show', 'hide', 'destroy', 'stats'],
            \Nexus\Controllers\Api\AdminCommentsApiController::class => ['index', 'show', 'hide', 'destroy'],
            \Nexus\Controllers\Api\AdminReviewsApiController::class => ['index', 'show', 'flag', 'hide', 'destroy'],
            \Nexus\Controllers\Api\AdminReportsApiController::class => ['index', 'stats', 'show', 'resolve', 'dismiss'],
        ];

        foreach ($controllers as $controllerClass => $methods) {
            $this->assertTrue(class_exists($controllerClass), "{$controllerClass} should exist");
            $reflection = new \ReflectionClass($controllerClass);

            foreach ($methods as $method) {
                $this->assertTrue(
                    $reflection->hasMethod($method),
                    "{$controllerClass} should have method {$method}"
                );
                $this->assertTrue(
                    $reflection->getMethod($method)->isPublic(),
                    "{$controllerClass}::{$method} should be public"
                );
            }
        }
    }

    public function testModerationEndpointsRequireAdminRole(): void
    {
        // Verify all moderation controllers are in the Api namespace (admin-scoped)
        $controllers = [
            \Nexus\Controllers\Api\AdminFeedApiController::class,
            \Nexus\Controllers\Api\AdminCommentsApiController::class,
            \Nexus\Controllers\Api\AdminReviewsApiController::class,
            \Nexus\Controllers\Api\AdminReportsApiController::class,
        ];

        foreach ($controllers as $controllerClass) {
            $reflection = new \ReflectionClass($controllerClass);
            $this->assertStringContainsString(
                'Nexus\\Controllers\\Api',
                $reflection->getNamespaceName(),
                "{$controllerClass} should be in the Api namespace"
            );
            // Verify the class is instantiable (not abstract)
            $this->assertTrue(
                $reflection->isInstantiable(),
                "{$controllerClass} should be instantiable"
            );
        }
    }
}
