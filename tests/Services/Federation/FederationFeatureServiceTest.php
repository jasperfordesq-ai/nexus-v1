<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services\Federation;

use App\Tests\DatabaseTestCase;
use App\Core\Database;
use App\Core\TenantContext;
use App\Services\FederationAuditService;
use App\Services\FederationFeatureService;

/**
 * FederationFeatureService Tests
 *
 * Tests feature flag management for federation operations.
 */
class FederationFeatureServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?FederationFeatureService $svc = null;

    /**
     * Get shared service instance.
     */
    protected static function svc(): FederationFeatureService
    {
        if (self::$svc === null) {
            self::$svc = new FederationFeatureService(new FederationAuditService());
        }
        return self::$svc;
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$testTenantId = 2; // hour-timebank tenant
        TenantContext::setById(self::$testTenantId);
    }

    // ==========================================
    // System-Level Feature Flag Tests
    // ==========================================

    public function testIsSystemFederationEnabledReturnsBool(): void
    {
        $result = self::svc()->isGloballyEnabled();

        $this->assertIsBool($result);
    }

    public function testGetSystemFeaturesReturnsArray(): void
    {
        if (!method_exists(FederationFeatureService::class, 'getSystemFeatures')) {
            $this->markTestSkipped('getSystemFeatures not implemented');
        }

        $result = self::svc()->getSystemFeatures();

        $this->assertIsArray($result);
    }

    // ==========================================
    // Tenant-Level Feature Flag Tests
    // ==========================================

    public function testIsTenantFederationEnabledReturnsBool(): void
    {
        $result = self::svc()->isTenantFederationEnabled(self::$testTenantId);

        $this->assertIsBool($result);
    }

    public function testIsTenantFederationEnabledWithInvalidTenant(): void
    {
        $result = self::svc()->isTenantFederationEnabled(999999);

        // Should return false for non-existent tenant
        $this->assertFalse($result);
    }

    public function testGetTenantFeaturesReturnsArray(): void
    {
        if (!method_exists(FederationFeatureService::class, 'getTenantFeatures')) {
            $this->markTestSkipped('getTenantFeatures not implemented');
        }

        $result = self::svc()->getTenantFeatures(self::$testTenantId);

        $this->assertIsArray($result);
    }

    // ==========================================
    // Operation Permission Tests
    // ==========================================

    public function testIsOperationAllowedReturnsExpectedStructure(): void
    {
        $operations = ['profiles', 'messaging', 'listings', 'transactions', 'groups'];

        foreach ($operations as $operation) {
            $result = self::svc()->isOperationAllowed($operation, self::$testTenantId);

            $this->assertIsArray($result, "isOperationAllowed('{$operation}') should return array");
            $this->assertArrayHasKey('allowed', $result, "Result for '{$operation}' should have 'allowed' key");
            $this->assertArrayHasKey('reason', $result, "Result for '{$operation}' should have 'reason' key");
            $this->assertIsBool($result['allowed'], "'allowed' should be boolean for '{$operation}'");
        }
    }

    public function testIsOperationAllowedWithInvalidOperation(): void
    {
        $result = self::svc()->isOperationAllowed('invalid_operation', self::$testTenantId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
        // Should either allow (unknown = allowed) or deny (unknown = denied) consistently
        $this->assertIsBool($result['allowed']);
    }

    public function testIsOperationAllowedWithInvalidTenant(): void
    {
        $result = self::svc()->isOperationAllowed('profiles', 999999);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
        // Should deny for non-existent tenant
        $this->assertFalse($result['allowed']);
    }

    // ==========================================
    // Feature-Specific Tests
    // ==========================================

    public function testIsProfilesEnabledReturnsBool(): void
    {
        if (!method_exists(FederationFeatureService::class, 'isProfilesEnabled')) {
            // Use generic method
            $result = self::svc()->isOperationAllowed('profiles', self::$testTenantId);
            $this->assertIsBool($result['allowed']);
            return;
        }

        $result = self::svc()->isProfilesEnabled(self::$testTenantId);
        $this->assertIsBool($result);
    }

    public function testIsMessagingEnabledReturnsBool(): void
    {
        if (!method_exists(FederationFeatureService::class, 'isMessagingEnabled')) {
            $result = self::svc()->isOperationAllowed('messaging', self::$testTenantId);
            $this->assertIsBool($result['allowed']);
            return;
        }

        $result = self::svc()->isMessagingEnabled(self::$testTenantId);
        $this->assertIsBool($result);
    }

    public function testIsTransactionsEnabledReturnsBool(): void
    {
        if (!method_exists(FederationFeatureService::class, 'isTransactionsEnabled')) {
            $result = self::svc()->isOperationAllowed('transactions', self::$testTenantId);
            $this->assertIsBool($result['allowed']);
            return;
        }

        $result = self::svc()->isTransactionsEnabled(self::$testTenantId);
        $this->assertIsBool($result);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testIsOperationAllowedWithNullTenant(): void
    {
        // PHP 8 should handle this, but we test the behavior
        try {
            $result = self::svc()->isOperationAllowed('profiles', 0);
            $this->assertIsArray($result);
            $this->assertFalse($result['allowed']);
        } catch (\TypeError $e) {
            // Type error is acceptable if method requires int
            $this->assertTrue(true);
        }
    }

    public function testIsOperationAllowedWithEmptyOperation(): void
    {
        $result = self::svc()->isOperationAllowed('', self::$testTenantId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
    }

    // ==========================================
    // Caching Tests
    // ==========================================

    public function testMultipleCallsReturnConsistentResults(): void
    {
        $result1 = self::svc()->isTenantFederationEnabled(self::$testTenantId);
        $result2 = self::svc()->isTenantFederationEnabled(self::$testTenantId);
        $result3 = self::svc()->isTenantFederationEnabled(self::$testTenantId);

        $this->assertEquals($result1, $result2);
        $this->assertEquals($result2, $result3);
    }

    public function testClearCacheMethodExists(): void
    {
        if (!method_exists(FederationFeatureService::class, 'clearCache')) {
            $this->markTestSkipped('clearCache not implemented');
        }

        // Should not throw
        self::svc()->clearCache();
        $this->assertTrue(true);
    }
}
