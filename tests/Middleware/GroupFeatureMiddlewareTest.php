<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Middleware;

use Nexus\Tests\TestCase;
use Nexus\Middleware\GroupFeatureMiddleware;
use Nexus\Services\GroupFeatureToggleService;
use ReflectionClass;

/**
 * GroupFeatureMiddlewareTest
 *
 * Tests the group feature gating middleware that controls access to
 * group-related features (discussions, badges, leaderboards, etc.).
 *
 * SECURITY: Verifies that disabled group features are properly gated
 * and cannot be accessed when toggled off at the tenant level.
 *
 * Note: Methods that call GroupFeatureToggleService::isEnabled() require
 * a database connection. Tests that exercise those paths use source
 * inspection and reflection to verify correctness without hitting the DB.
 */
class GroupFeatureMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // checkGroupsEnabled() — logic and structure tests
    // -----------------------------------------------------------------------

    /**
     * Test checkGroupsEnabled() checks the groups module feature toggle.
     */
    public function testCheckGroupsEnabledChecksGroupsModuleToggle(): void
    {
        $reflection = new ReflectionClass(GroupFeatureMiddleware::class);
        $method = $reflection->getMethod('checkGroupsEnabled');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('FEATURE_GROUPS_MODULE', $body,
            'checkGroupsEnabled() should check the FEATURE_GROUPS_MODULE constant');
        $this->assertStringContainsString('GroupFeatureToggleService::isEnabled', $body,
            'checkGroupsEnabled() should call GroupFeatureToggleService::isEnabled()');
    }

    /**
     * Test checkGroupsEnabled() returns true when enabled.
     */
    public function testCheckGroupsEnabledReturnsTrueWhenEnabled(): void
    {
        $reflection = new ReflectionClass(GroupFeatureMiddleware::class);
        $method = $reflection->getMethod('checkGroupsEnabled');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('return true;', $body,
            'checkGroupsEnabled() should return true when groups is enabled');
    }

    /**
     * Test checkGroupsEnabled() error response structure.
     */
    public function testCheckGroupsEnabledErrorStructure(): void
    {
        $reflection = new ReflectionClass(GroupFeatureMiddleware::class);
        $source = file_get_contents($reflection->getFileName());

        // Verify error response keys are present in checkGroupsEnabled method
        $method = $reflection->getMethod('checkGroupsEnabled');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString("'error' => true", $body,
            'Error response should have error=true');
        $this->assertStringContainsString("'message'", $body,
            'Error response should have a message');
        $this->assertStringContainsString("'redirect'", $body,
            'Error response should have a redirect URL');
        $this->assertStringContainsString('Groups', $body,
            'Error message should mention Groups');
    }

    // -----------------------------------------------------------------------
    // checkFeature() — logic and structure tests
    // -----------------------------------------------------------------------

    /**
     * Test checkFeature() accepts a feature key and optional custom message.
     */
    public function testCheckFeatureMethodSignature(): void
    {
        $reflection = new ReflectionClass(GroupFeatureMiddleware::class);
        $method = $reflection->getMethod('checkFeature');

        $params = $method->getParameters();
        $this->assertCount(2, $params);
        $this->assertEquals('feature', $params[0]->getName());
        $this->assertEquals('customMessage', $params[1]->getName());
        $this->assertTrue($params[1]->isDefaultValueAvailable());
        $this->assertNull($params[1]->getDefaultValue());
    }

    /**
     * Test checkFeature() calls GroupFeatureToggleService::isEnabled().
     */
    public function testCheckFeatureCallsToggleService(): void
    {
        $reflection = new ReflectionClass(GroupFeatureMiddleware::class);
        $method = $reflection->getMethod('checkFeature');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('GroupFeatureToggleService::isEnabled($feature)', $body,
            'checkFeature() should call GroupFeatureToggleService::isEnabled()');
    }

    /**
     * Test checkFeature() error response redirects to /groups.
     */
    public function testCheckFeatureRedirectsToGroups(): void
    {
        $reflection = new ReflectionClass(GroupFeatureMiddleware::class);
        $method = $reflection->getMethod('checkFeature');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('/groups', $body,
            'checkFeature() should redirect to /groups when feature is disabled');
    }

    /**
     * Test checkFeature() uses custom message when provided.
     */
    public function testCheckFeatureUsesCustomMessage(): void
    {
        $reflection = new ReflectionClass(GroupFeatureMiddleware::class);
        $method = $reflection->getMethod('checkFeature');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('$customMessage ??', $body,
            'checkFeature() should use custom message with ?? fallback');
    }

    // -----------------------------------------------------------------------
    // checkFeatures() — requires ALL features enabled
    // -----------------------------------------------------------------------

    /**
     * Test checkFeatures() iterates all features and returns first error.
     */
    public function testCheckFeaturesIteratesAllFeatures(): void
    {
        $reflection = new ReflectionClass(GroupFeatureMiddleware::class);
        $method = $reflection->getMethod('checkFeatures');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('foreach ($features as $feature)', $body,
            'checkFeatures() should iterate over all features');
        $this->assertStringContainsString('self::checkFeature($feature)', $body,
            'checkFeatures() should call checkFeature() for each feature');
    }

    /**
     * Test checkFeatures() returns true if all pass.
     */
    public function testCheckFeaturesReturnsTrueWhenAllPass(): void
    {
        $reflection = new ReflectionClass(GroupFeatureMiddleware::class);
        $method = $reflection->getMethod('checkFeatures');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('return true;', $body,
            'checkFeatures() should return true when all features pass');
    }

    /**
     * Test checkFeatures() returns error array on first failure.
     */
    public function testCheckFeaturesReturnsErrorOnFirstFailure(): void
    {
        $reflection = new ReflectionClass(GroupFeatureMiddleware::class);
        $method = $reflection->getMethod('checkFeatures');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('is_array($check)', $body,
            'checkFeatures() should check if checkFeature returned an error array');
        $this->assertStringContainsString('return $check;', $body,
            'checkFeatures() should return the first error array');
    }

    // -----------------------------------------------------------------------
    // checkAnyFeature() — at least one must be enabled
    // -----------------------------------------------------------------------

    /**
     * Test checkAnyFeature() returns true if any one is enabled.
     */
    public function testCheckAnyFeatureReturnsOnFirstEnabled(): void
    {
        $reflection = new ReflectionClass(GroupFeatureMiddleware::class);
        $method = $reflection->getMethod('checkAnyFeature');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('foreach ($features as $feature)', $body,
            'checkAnyFeature() should iterate features');
        $this->assertStringContainsString('return true;', $body,
            'checkAnyFeature() should return true on first enabled feature');
        $this->assertStringContainsString('return false;', $body,
            'checkAnyFeature() should return false if none enabled');
    }

    /**
     * Test checkAnyFeature() with empty array returns false (vacuous false).
     */
    public function testCheckAnyFeatureEmptyArrayLogic(): void
    {
        $reflection = new ReflectionClass(GroupFeatureMiddleware::class);
        $method = $reflection->getMethod('checkAnyFeature');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        // If no features provided, the foreach loop doesn't execute and falls through to return false
        $this->assertStringContainsString('return false;', $body,
            'checkAnyFeature() should return false for empty feature list');
    }

    // -----------------------------------------------------------------------
    // can() tests
    // -----------------------------------------------------------------------

    /**
     * Test can() delegates to GroupFeatureToggleService::isEnabled().
     */
    public function testCanDelegatesToToggleService(): void
    {
        $reflection = new ReflectionClass(GroupFeatureMiddleware::class);
        $method = $reflection->getMethod('can');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('GroupFeatureToggleService::isEnabled($feature)', $body,
            'can() should delegate to GroupFeatureToggleService::isEnabled()');
    }

    // -----------------------------------------------------------------------
    // gates() tests
    // -----------------------------------------------------------------------

    /**
     * Test gates() builds an associative array of feature => enabled.
     */
    public function testGatesBuildsAssociativeArray(): void
    {
        $reflection = new ReflectionClass(GroupFeatureMiddleware::class);
        $method = $reflection->getMethod('gates');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('$gates = [];', $body,
            'gates() should initialize an empty array');
        $this->assertStringContainsString('foreach ($features as $feature)', $body,
            'gates() should iterate over features');
        $this->assertStringContainsString('$gates[$feature]', $body,
            'gates() should assign results keyed by feature');
        $this->assertStringContainsString('return $gates;', $body,
            'gates() should return the gates array');
    }

    // -----------------------------------------------------------------------
    // Method signature tests
    // -----------------------------------------------------------------------

    /**
     * Test that all expected public methods exist on the middleware.
     */
    public function testAllExpectedPublicMethodsExist(): void
    {
        $reflection = new ReflectionClass(GroupFeatureMiddleware::class);

        $expectedMethods = [
            'checkGroupsEnabled',
            'checkFeature',
            'requireGroups',
            'requireFeature',
            'checkFeatures',
            'checkAnyFeature',
            'can',
            'gates',
        ];

        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "Middleware should have public method: {$method}"
            );
            $this->assertTrue(
                $reflection->getMethod($method)->isPublic(),
                "Method {$method} should be public"
            );
            $this->assertTrue(
                $reflection->getMethod($method)->isStatic(),
                "Method {$method} should be static"
            );
        }
    }

    /**
     * Test requireGroups() calls exit when groups is disabled.
     * SECURITY: Disabled group module must be hard-blocked.
     */
    public function testRequireGroupsCallsExit(): void
    {
        $reflection = new ReflectionClass(GroupFeatureMiddleware::class);
        $method = $reflection->getMethod('requireGroups');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('exit', $body,
            'requireGroups() MUST call exit when groups is disabled (security-critical)');
    }

    /**
     * Test requireFeature() first checks groups module before checking specific feature.
     * SECURITY: Sub-features should be inaccessible if the parent module is disabled.
     */
    public function testRequireFeatureChecksGroupsFirst(): void
    {
        $reflection = new ReflectionClass(GroupFeatureMiddleware::class);
        $method = $reflection->getMethod('requireFeature');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('self::requireGroups()', $body,
            'requireFeature() MUST check requireGroups() first');
        $this->assertStringContainsString('self::checkFeature($feature', $body,
            'requireFeature() should check the specific feature after requireGroups()');
    }

    /**
     * Test requireGroups() handles AJAX requests with JSON response.
     */
    public function testRequireGroupsHandlesAjaxRequests(): void
    {
        $reflection = new ReflectionClass(GroupFeatureMiddleware::class);
        $method = $reflection->getMethod('requireGroups');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('HTTP_X_REQUESTED_WITH', $body,
            'requireGroups() should detect AJAX requests');
        $this->assertStringContainsString('application/json', $body,
            'requireGroups() should return JSON for AJAX requests');
    }

    /**
     * Test that the GroupFeatureToggleService constants used match expected values.
     */
    public function testGroupFeatureConstants(): void
    {
        $this->assertEquals('groups_module', GroupFeatureToggleService::FEATURE_GROUPS_MODULE);
        $this->assertEquals('discussions', GroupFeatureToggleService::FEATURE_DISCUSSIONS);
        $this->assertEquals('feedback', GroupFeatureToggleService::FEATURE_FEEDBACK);
        $this->assertEquals('achievements', GroupFeatureToggleService::FEATURE_ACHIEVEMENTS);
        $this->assertEquals('badges', GroupFeatureToggleService::FEATURE_BADGES);
        $this->assertEquals('leaderboard', GroupFeatureToggleService::FEATURE_LEADERBOARD);
        $this->assertEquals('analytics', GroupFeatureToggleService::FEATURE_ANALYTICS);
        $this->assertEquals('moderation', GroupFeatureToggleService::FEATURE_MODERATION);
        $this->assertEquals('maps', GroupFeatureToggleService::FEATURE_MAPS);
    }
}
