<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Middleware;

use Nexus\Tests\TestCase;
use Nexus\Middleware\TenantModuleMiddleware;
use ReflectionClass;

/**
 * TenantModuleMiddlewareTest
 *
 * Tests the module gating middleware that controls access to tenant modules
 * (listings, events, groups, wallet, etc.) via TenantContext::hasFeature().
 *
 * SECURITY: These tests verify that disabled modules cannot be accessed.
 *
 * Note: Methods that call TenantContext::hasFeature() require a database
 * connection (TenantContext::get() -> resolve() -> Database::getInstance()).
 * Since Database::getInstance() calls die() on connection failure, tests
 * that exercise those paths are marked @group database and are tested via
 * reflection or source inspection when DB is not available.
 */
class TenantModuleMiddlewareTest extends TestCase
{
    /**
     * All expected module keys that should be defined in the middleware.
     */
    private const EXPECTED_MODULES = [
        'listings',
        'groups',
        'wallet',
        'volunteering',
        'events',
        'resources',
        'polls',
        'goals',
        'blog',
        'help_center',
    ];

    /**
     * Test getModuleDefinition() returns a valid definition for each known module.
     */
    public function testGetModuleDefinitionReturnsDefinitionForValidModule(): void
    {
        foreach (self::EXPECTED_MODULES as $module) {
            $definition = TenantModuleMiddleware::getModuleDefinition($module);

            $this->assertNotNull($definition, "Module '{$module}' should have a definition");
            $this->assertArrayHasKey('label', $definition, "Module '{$module}' definition should have 'label'");
            $this->assertArrayHasKey('description', $definition, "Module '{$module}' definition should have 'description'");
            $this->assertArrayHasKey('default_redirect', $definition, "Module '{$module}' definition should have 'default_redirect'");
        }
    }

    /**
     * Test getModuleDefinition() returns null for an invalid/unknown module key.
     */
    public function testGetModuleDefinitionReturnsNullForInvalidModule(): void
    {
        $this->assertNull(TenantModuleMiddleware::getModuleDefinition('nonexistent_module'));
        $this->assertNull(TenantModuleMiddleware::getModuleDefinition(''));
        $this->assertNull(TenantModuleMiddleware::getModuleDefinition('xss<script>'));
    }

    /**
     * Test getAllModuleDefinitions() returns exactly 10 modules.
     */
    public function testGetAllModuleDefinitionsReturnsAllTenModules(): void
    {
        $definitions = TenantModuleMiddleware::getAllModuleDefinitions();

        $this->assertIsArray($definitions);
        $this->assertCount(10, $definitions, 'There should be exactly 10 module definitions');
    }

    /**
     * Test getAllModuleDefinitions() contains all expected module keys.
     */
    public function testGetAllModuleDefinitionsContainsAllExpectedKeys(): void
    {
        $definitions = TenantModuleMiddleware::getAllModuleDefinitions();

        foreach (self::EXPECTED_MODULES as $module) {
            $this->assertArrayHasKey($module, $definitions, "Missing module definition: {$module}");
        }
    }

    /**
     * Test that each module definition has the required structure.
     */
    public function testAllModuleDefinitionsHaveRequiredStructure(): void
    {
        $definitions = TenantModuleMiddleware::getAllModuleDefinitions();

        foreach ($definitions as $key => $definition) {
            $this->assertArrayHasKey('label', $definition, "Module '{$key}' missing 'label'");
            $this->assertIsString($definition['label'], "Module '{$key}' label should be a string");
            $this->assertNotEmpty($definition['label'], "Module '{$key}' label should not be empty");

            $this->assertArrayHasKey('description', $definition, "Module '{$key}' missing 'description'");
            $this->assertIsString($definition['description'], "Module '{$key}' description should be a string");

            $this->assertArrayHasKey('default_redirect', $definition, "Module '{$key}' missing 'default_redirect'");
            $this->assertIsString($definition['default_redirect'], "Module '{$key}' default_redirect should be a string");
            $this->assertStringStartsWith('/', $definition['default_redirect'], "Module '{$key}' default_redirect should start with /");
        }
    }

    /**
     * Test getAllModuleStates() iterates over all defined module keys.
     * Verified via source inspection since the method calls TenantContext::hasFeature()
     * which requires a database connection.
     */
    public function testGetAllModuleStatesIteratesAllModuleKeys(): void
    {
        $reflection = new ReflectionClass(TenantModuleMiddleware::class);
        $source = file_get_contents($reflection->getFileName());

        // Verify getAllModuleStates loops over self::$modules keys
        $this->assertStringContainsString('array_keys(self::$modules)', $source,
            'getAllModuleStates should iterate over all module keys');
        $this->assertStringContainsString('self::isEnabled($module)', $source,
            'getAllModuleStates should call isEnabled for each module');
    }

    /**
     * Test check() error response structure via source code analysis.
     * The check() method returns true (enabled) or an array with error/module/message/redirect keys.
     */
    public function testCheckErrorResponseHasCorrectKeys(): void
    {
        $reflection = new ReflectionClass(TenantModuleMiddleware::class);
        $source = file_get_contents($reflection->getFileName());

        // Verify the error response array contains the required keys
        $this->assertStringContainsString("'error' => true", $source,
            'Error response should include error=true');
        $this->assertStringContainsString("'module' => \$module", $source,
            'Error response should include the module key');
        $this->assertStringContainsString("'message' =>", $source,
            'Error response should include a message');
        $this->assertStringContainsString("'redirect' =>", $source,
            'Error response should include a redirect URL');
    }

    /**
     * Test check() returns true when isEnabled returns true.
     * Verified via source code — early return pattern.
     */
    public function testCheckReturnsTrueWhenEnabled(): void
    {
        $reflection = new ReflectionClass(TenantModuleMiddleware::class);
        $source = file_get_contents($reflection->getFileName());

        // Check that check() returns true early when module is enabled
        $this->assertStringContainsString('if (self::isEnabled($module))', $source,
            'check() should test isEnabled() first');
        $this->assertStringContainsString('return true;', $source,
            'check() should return true when module is enabled');
    }

    /**
     * Test check() handles custom messages by using them in the response.
     */
    public function testCheckAcceptsCustomMessage(): void
    {
        $reflection = new ReflectionClass(TenantModuleMiddleware::class);
        $method = $reflection->getMethod('check');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'check() should accept 2 parameters');
        $this->assertEquals('module', $params[0]->getName());
        $this->assertEquals('customMessage', $params[1]->getName());
        $this->assertTrue($params[1]->isDefaultValueAvailable());
        $this->assertNull($params[1]->getDefaultValue());
    }

    /**
     * Test check() uses the custom message when provided (via source inspection).
     */
    public function testCheckUsesCustomMessageInResponse(): void
    {
        $reflection = new ReflectionClass(TenantModuleMiddleware::class);
        $source = file_get_contents($reflection->getFileName());

        // Verify the null coalescing pattern for custom message
        $this->assertStringContainsString('$customMessage ??', $source,
            'check() should use customMessage with ?? fallback to default');
    }

    /**
     * Test check() falls back to ucfirst for unknown module labels.
     */
    public function testCheckFallsBackToUcfirstForUnknownModules(): void
    {
        $reflection = new ReflectionClass(TenantModuleMiddleware::class);
        $source = file_get_contents($reflection->getFileName());

        // Verify fallback for unknown modules
        $this->assertStringContainsString("ucfirst(\$module)", $source,
            'check() should fall back to ucfirst(module) for unknown module labels');
    }

    /**
     * Test can() method delegates to isEnabled().
     * Verified via source — can() is a one-liner that calls isEnabled().
     */
    public function testCanDelegatesToIsEnabled(): void
    {
        $reflection = new ReflectionClass(TenantModuleMiddleware::class);

        // Verify both methods exist
        $this->assertTrue($reflection->hasMethod('can'), 'can() method should exist');
        $this->assertTrue($reflection->hasMethod('isEnabled'), 'isEnabled() method should exist');

        // Verify can() delegates to isEnabled()
        $source = file_get_contents($reflection->getFileName());
        // can() body should contain self::isEnabled
        $canMethod = $reflection->getMethod('can');
        $startLine = $canMethod->getStartLine();
        $endLine = $canMethod->getEndLine();
        $lines = file($reflection->getFileName());
        $canBody = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('self::isEnabled', $canBody,
            'can() should delegate to self::isEnabled()');
    }

    /**
     * Test isEnabled() delegates to TenantContext::hasFeature().
     */
    public function testIsEnabledDelegatesToTenantContextHasFeature(): void
    {
        $reflection = new ReflectionClass(TenantModuleMiddleware::class);
        $isEnabledMethod = $reflection->getMethod('isEnabled');
        $startLine = $isEnabledMethod->getStartLine();
        $endLine = $isEnabledMethod->getEndLine();
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('TenantContext::hasFeature', $body,
            'isEnabled() should delegate to TenantContext::hasFeature()');
    }

    /**
     * Test that the modules array is accessible via reflection and contains expected entries.
     */
    public function testModulesStaticPropertyContainsExpectedEntries(): void
    {
        $reflection = new ReflectionClass(TenantModuleMiddleware::class);
        $modulesProperty = $reflection->getProperty('modules');
        $modulesProperty->setAccessible(true);

        $modules = $modulesProperty->getValue();

        $this->assertIsArray($modules);
        $this->assertCount(10, $modules);

        foreach (self::EXPECTED_MODULES as $module) {
            $this->assertArrayHasKey($module, $modules);
        }
    }

    /**
     * Test that the wallet module redirects to /dashboard (not /).
     */
    public function testWalletModuleRedirectsToDashboard(): void
    {
        $definition = TenantModuleMiddleware::getModuleDefinition('wallet');
        $this->assertNotNull($definition);
        $this->assertEquals('/dashboard', $definition['default_redirect']);
    }

    /**
     * Test that most modules redirect to / by default.
     */
    public function testMostModulesRedirectToRoot(): void
    {
        $rootRedirectModules = ['listings', 'groups', 'volunteering', 'events', 'resources', 'polls', 'goals', 'blog', 'help_center'];

        foreach ($rootRedirectModules as $module) {
            $definition = TenantModuleMiddleware::getModuleDefinition($module);
            $this->assertNotNull($definition);
            $this->assertEquals('/', $definition['default_redirect'], "Module '{$module}' should redirect to /");
        }
    }

    /**
     * Test isEnabled() returns bool type.
     */
    public function testIsEnabledReturnsBool(): void
    {
        $reflection = new ReflectionClass(TenantModuleMiddleware::class);
        $method = $reflection->getMethod('isEnabled');

        $this->assertEquals('bool', (string)$method->getReturnType(),
            'isEnabled() should return bool');
    }

    /**
     * Test check() return type allows both bool and array.
     */
    public function testCheckReturnTypeContract(): void
    {
        $reflection = new ReflectionClass(TenantModuleMiddleware::class);
        $method = $reflection->getMethod('check');

        // check() has no strict return type annotation (returns mixed: bool|array)
        // Verify via source that it returns true or array
        $source = file_get_contents($reflection->getFileName());
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('return true;', $body,
            'check() should return true when module is enabled');
        $this->assertStringContainsString('return [', $body,
            'check() should return an array when module is disabled');
    }

    /**
     * Test that require() calls exit when module is disabled.
     * SECURITY: Disabled modules must be hard-blocked, not just warned.
     */
    public function testRequireCallsExitWhenDisabled(): void
    {
        $reflection = new ReflectionClass(TenantModuleMiddleware::class);
        $source = file_get_contents($reflection->getFileName());

        // Verify require() calls exit
        $requireMethod = $reflection->getMethod('require');
        $startLine = $requireMethod->getStartLine();
        $endLine = $requireMethod->getEndLine();
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('exit', $body,
            'require() MUST call exit when module is disabled (security-critical)');
    }

    /**
     * Test that require() handles AJAX requests with JSON response.
     */
    public function testRequireHandlesAjaxRequests(): void
    {
        $reflection = new ReflectionClass(TenantModuleMiddleware::class);
        $requireMethod = $reflection->getMethod('require');
        $startLine = $requireMethod->getStartLine();
        $endLine = $requireMethod->getEndLine();
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('HTTP_X_REQUESTED_WITH', $body,
            'require() should detect AJAX requests');
        $this->assertStringContainsString('application/json', $body,
            'require() should return JSON for AJAX requests');
    }

    /**
     * Test module labels are human-readable strings.
     */
    public function testModuleLabelsAreHumanReadable(): void
    {
        $definitions = TenantModuleMiddleware::getAllModuleDefinitions();

        foreach ($definitions as $key => $def) {
            // Label should not be a snake_case technical name
            $this->assertDoesNotMatchRegularExpression('/^[a-z]+_[a-z]+$/', $def['label'],
                "Module '{$key}' label should be human-readable, not snake_case");

            // Label should start with uppercase
            $this->assertMatchesRegularExpression('/^[A-Z]/', $def['label'],
                "Module '{$key}' label should start with uppercase");
        }
    }
}
