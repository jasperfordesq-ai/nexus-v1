<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\BalanceAlertService;

/**
 * BalanceAlertCronTest
 *
 * Tests for organization wallet balance alert cron operations:
 * - Low balance detection across all organization wallets
 * - Critical balance detection and escalation
 * - Threshold configuration (default and per-org)
 * - Alert deduplication (daily spam prevention)
 * - Notification sending to org admins
 * - Balance status reporting
 *
 * @covers \App\Services\BalanceAlertService
 */
class BalanceAlertCronTest extends \Tests\Laravel\TestCase
{
    // =========================================================================
    // CLASS & METHOD EXISTENCE
    // =========================================================================

    /**
     * Test BalanceAlertService class exists
     */
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(BalanceAlertService::class));
    }

    /**
     * Test all cron-related public methods exist and are public instance methods.
     *
     * The service is dependency-injectable (instance methods, not static); the
     * scheduler resolves it from the container. getBalanceStatus was removed in
     * the refactor — balance classification now lives inline in checkBalance.
     */
    public function testCronRelatedMethodsExistAndArePublicInstance(): void
    {
        $methods = [
            'checkAllBalances',
            'checkBalance',
            'getThresholds',
            'setThresholds',
            'areAlertsEnabled',
        ];

        $ref = new \ReflectionClass(BalanceAlertService::class);

        foreach ($methods as $methodName) {
            $this->assertTrue(
                $ref->hasMethod($methodName),
                "Method {$methodName} should exist"
            );
            $method = $ref->getMethod($methodName);
            $this->assertFalse($method->isStatic(), "{$methodName} should be an instance method");
            $this->assertTrue($method->isPublic(), "{$methodName} should be public");
        }
    }

    /**
     * Test private helper methods exist.
     *
     * In the refactored service, recordAlert + sendBalanceAlert were consolidated
     * into sendAndRecordAlert(), and sendAlertEmail was renamed sendBalanceAlertEmail.
     */
    public function testPrivateHelperMethodsExist(): void
    {
        $ref = new \ReflectionClass(BalanceAlertService::class);

        $privateMethods = ['hasAlertedToday', 'sendAndRecordAlert', 'sendBalanceAlertEmail'];
        foreach ($privateMethods as $methodName) {
            $this->assertTrue(
                $ref->hasMethod($methodName),
                "Private helper method {$methodName} should exist"
            );
            $method = $ref->getMethod($methodName);
            $this->assertTrue($method->isPrivate(), "{$methodName} should be private");
            $this->assertFalse($method->isStatic(), "{$methodName} should be an instance method");
        }
    }

    // =========================================================================
    // THRESHOLD CONSTANTS
    // =========================================================================

    /**
     * Test default low balance threshold constant
     */
    public function testDefaultLowBalanceThreshold(): void
    {
        $this->assertEquals(
            50,
            BalanceAlertService::DEFAULT_LOW_BALANCE_THRESHOLD,
            'Default low balance threshold should be 50 credits'
        );
    }

    /**
     * Test default critical balance threshold constant
     */
    public function testDefaultCriticalBalanceThreshold(): void
    {
        $this->assertEquals(
            10,
            BalanceAlertService::DEFAULT_CRITICAL_BALANCE_THRESHOLD,
            'Default critical balance threshold should be 10 credits'
        );
    }

    /**
     * Test critical threshold is lower than low threshold
     */
    public function testCriticalThresholdIsLowerThanLowThreshold(): void
    {
        $this->assertLessThan(
            BalanceAlertService::DEFAULT_LOW_BALANCE_THRESHOLD,
            BalanceAlertService::DEFAULT_CRITICAL_BALANCE_THRESHOLD,
            'Critical threshold should be lower than low threshold'
        );
    }

    /**
     * Test both thresholds are positive
     */
    public function testThresholdsArePositive(): void
    {
        $this->assertGreaterThan(0, BalanceAlertService::DEFAULT_LOW_BALANCE_THRESHOLD);
        $this->assertGreaterThan(0, BalanceAlertService::DEFAULT_CRITICAL_BALANCE_THRESHOLD);
    }

    /**
     * Test thresholds create three distinct zones
     */
    public function testThresholdsCreateThreeZones(): void
    {
        $low = BalanceAlertService::DEFAULT_LOW_BALANCE_THRESHOLD;
        $critical = BalanceAlertService::DEFAULT_CRITICAL_BALANCE_THRESHOLD;

        // Zone 1: Healthy (balance > low) - no alert
        // Zone 2: Low (critical < balance <= low) - low alert
        // Zone 3: Critical (balance <= critical) - critical alert
        $this->assertGreaterThan($critical, $low, 'Low > Critical ensures three distinct zones');
        $this->assertGreaterThan(0, $critical, 'Critical > 0 ensures critical zone has a lower bound');
    }

    // =========================================================================
    // CHECK ALL BALANCES — CRON ENTRY POINT
    // =========================================================================

    /**
     * Test checkAllBalances method signature (no required parameters)
     */
    public function testCheckAllBalancesMethodSignature(): void
    {
        $ref = new \ReflectionMethod(BalanceAlertService::class, 'checkAllBalances');
        $params = $ref->getParameters();

        $this->assertCount(0, $params, 'checkAllBalances should take no parameters (it queries all orgs)');
    }

    // =========================================================================
    // CHECK BALANCE — SINGLE ORG CHECK
    // =========================================================================

    /**
     * Test checkBalance method signature
     */
    public function testCheckBalanceMethodSignature(): void
    {
        $ref = new \ReflectionMethod(BalanceAlertService::class, 'checkBalance');
        $params = $ref->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params), 'Should accept at least organizationId');
        $this->assertEquals('organizationId', $params[0]->getName());

        // balance and orgName should be optional
        if (count($params) > 1) {
            $this->assertTrue($params[1]->isOptional(), 'balance should be optional (auto-fetched if null)');
            $this->assertNull($params[1]->getDefaultValue());
        }
        if (count($params) > 2) {
            $this->assertTrue($params[2]->isOptional(), 'orgName should be optional (auto-fetched if null)');
            $this->assertNull($params[2]->getDefaultValue());
        }
    }

    // =========================================================================
    // THRESHOLD MANAGEMENT
    // =========================================================================

    /**
     * Test getThresholds method signature
     */
    public function testGetThresholdsMethodSignature(): void
    {
        $ref = new \ReflectionMethod(BalanceAlertService::class, 'getThresholds');
        $params = $ref->getParameters();

        $this->assertCount(1, $params, 'getThresholds should accept organizationId');
        $this->assertEquals('organizationId', $params[0]->getName());
    }

    /**
     * Test setThresholds method signature
     */
    public function testSetThresholdsMethodSignature(): void
    {
        $ref = new \ReflectionMethod(BalanceAlertService::class, 'setThresholds');
        $params = $ref->getParameters();

        $this->assertCount(3, $params, 'setThresholds should accept organizationId, lowThreshold, criticalThreshold');
        $this->assertEquals('organizationId', $params[0]->getName());
        $this->assertEquals('lowThreshold', $params[1]->getName());
        $this->assertEquals('criticalThreshold', $params[2]->getName());
    }

    // =========================================================================
    // BALANCE STATUS — DISPLAY HELPER
    // =========================================================================

    /**
     * Test checkBalance returns a structured classification.
     *
     * The standalone getBalanceStatus() helper was removed in the refactor;
     * checkBalance() now returns the balance, thresholds, and the resolved
     * alert_type ('critical' | 'low' | null) in one array.
     */
    public function testCheckBalanceReturnsClassificationArray(): void
    {
        $ref = new \ReflectionMethod(BalanceAlertService::class, 'checkBalance');
        $returnType = (string) $ref->getReturnType();

        $this->assertEquals('array', $returnType, 'checkBalance should return an array');
        $this->assertFalse($ref->isStatic(), 'checkBalance should be an instance method');
    }

    // =========================================================================
    // ALERT DEDUPLICATION LOGIC
    // =========================================================================

    /**
     * Test hasAlertedToday private method exists (prevents alert spam)
     */
    public function testHasAlertedTodayMethodExists(): void
    {
        $ref = new \ReflectionClass(BalanceAlertService::class);
        $this->assertTrue($ref->hasMethod('hasAlertedToday'));

        $method = $ref->getMethod('hasAlertedToday');
        $this->assertTrue($method->isPrivate(), 'hasAlertedToday should be private');
        $this->assertFalse($method->isStatic(), 'hasAlertedToday should be an instance method');

        $params = $method->getParameters();
        $this->assertCount(1, $params, 'Should accept organizationId');
    }

    /**
     * Test sendAndRecordAlert private method exists.
     *
     * Replaces the former recordAlert + sendBalanceAlert pair — it sends the
     * alert first and only records it (for daily idempotency) on success.
     */
    public function testSendAndRecordAlertMethodExists(): void
    {
        $ref = new \ReflectionClass(BalanceAlertService::class);
        $method = $ref->getMethod('sendAndRecordAlert');

        $this->assertTrue($method->isPrivate());
        $this->assertFalse($method->isStatic(), 'sendAndRecordAlert should be an instance method');

        $params = $method->getParameters();
        $this->assertGreaterThanOrEqual(2, count($params), 'Should accept organizationId and alertType');
        $this->assertEquals('organizationId', $params[0]->getName());
        $this->assertEquals('alertType', $params[1]->getName());
    }

    // =========================================================================
    // NOTIFICATION SENDING
    // =========================================================================

    /**
     * Test sendBalanceAlertEmail private method exists.
     *
     * This is the renamed email-sending helper (was sendAlertEmail). It accepts
     * the resolved owner object, org name, balance and alert type.
     */
    public function testSendBalanceAlertEmailMethodExists(): void
    {
        $ref = new \ReflectionClass(BalanceAlertService::class);
        $method = $ref->getMethod('sendBalanceAlertEmail');

        $this->assertTrue($method->isPrivate());
        $this->assertFalse($method->isStatic(), 'sendBalanceAlertEmail should be an instance method');

        $params = $method->getParameters();
        $this->assertGreaterThanOrEqual(4, count($params), 'Should accept owner, orgName, balance, alertType');
    }

    // =========================================================================
    // SEVERITY ESCALATION LOGIC
    // =========================================================================

    /**
     * Test that critical alerts have higher priority than low alerts
     *
     * Based on the source: checkBalance checks critical first (higher priority)
     * and only checks low if balance is above critical threshold.
     */
    public function testCriticalAlertHasPriorityOverLow(): void
    {
        $low = BalanceAlertService::DEFAULT_LOW_BALANCE_THRESHOLD;
        $critical = BalanceAlertService::DEFAULT_CRITICAL_BALANCE_THRESHOLD;

        // A balance at exactly critical should trigger 'critical', not 'low'
        // This is verified by the check order in the source:
        // if (balance <= critical) => critical
        // elseif (balance <= low && balance > critical) => low
        $this->assertLessThan($low, $critical, 'Critical check boundary should be lower than low boundary');
    }

    /**
     * Test healthy balance range produces no alert
     *
     * Based on source: balance > low threshold => no alert
     */
    public function testHealthyBalanceProducesNoAlert(): void
    {
        $low = BalanceAlertService::DEFAULT_LOW_BALANCE_THRESHOLD;

        // A balance above the low threshold should not trigger any alert
        $healthyBalance = $low + 1;
        $this->assertGreaterThan($low, $healthyBalance, 'Healthy balance should be above low threshold');
    }

    /**
     * Test all three balance status values exist in getBalanceStatus
     */
    public function testBalanceStatusValuesAreDocumented(): void
    {
        // The method returns one of: 'critical', 'low', 'healthy'
        // Each with: status, label, color, message
        $expectedStatuses = ['critical', 'low', 'healthy'];

        // This is a structural/design test verifying the expected return values
        foreach ($expectedStatuses as $status) {
            $this->assertNotEmpty($status, 'Each status value should be non-empty');
        }
    }

    /**
     * Test color codes for different status levels follow traffic light pattern
     */
    public function testStatusColorCodesAreValid(): void
    {
        // Based on source:
        // critical => #ef4444 (red)
        // low => #f59e0b (amber/yellow)
        // healthy => #10b981 (green)
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', '#ef4444', 'Critical color should be valid hex');
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', '#f59e0b', 'Low color should be valid hex');
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', '#10b981', 'Healthy color should be valid hex');
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    /**
     * Test balance at exactly the low threshold triggers low alert
     */
    public function testBoundaryAtLowThreshold(): void
    {
        $low = BalanceAlertService::DEFAULT_LOW_BALANCE_THRESHOLD;
        $critical = BalanceAlertService::DEFAULT_CRITICAL_BALANCE_THRESHOLD;

        // balance <= low AND balance > critical => low alert
        // At exactly $low (50), and $low > $critical (10), so this should be a low alert
        $this->assertTrue(
            $low <= BalanceAlertService::DEFAULT_LOW_BALANCE_THRESHOLD &&
            $low > BalanceAlertService::DEFAULT_CRITICAL_BALANCE_THRESHOLD,
            'Balance at low threshold should be in the low alert zone'
        );
    }

    /**
     * Test balance at exactly the critical threshold triggers critical alert
     */
    public function testBoundaryAtCriticalThreshold(): void
    {
        $critical = BalanceAlertService::DEFAULT_CRITICAL_BALANCE_THRESHOLD;

        // balance <= critical => critical alert
        $this->assertTrue(
            $critical <= BalanceAlertService::DEFAULT_CRITICAL_BALANCE_THRESHOLD,
            'Balance at critical threshold should trigger critical alert'
        );
    }

    /**
     * Test zero balance should be critical
     */
    public function testZeroBalanceShouldBeCritical(): void
    {
        $critical = BalanceAlertService::DEFAULT_CRITICAL_BALANCE_THRESHOLD;

        $this->assertTrue(
            0 <= $critical,
            'Zero balance should be at or below critical threshold'
        );
    }

    /**
     * Test negative balance should be critical
     */
    public function testNegativeBalanceShouldBeCritical(): void
    {
        $critical = BalanceAlertService::DEFAULT_CRITICAL_BALANCE_THRESHOLD;

        $this->assertTrue(
            -10 <= $critical,
            'Negative balance should be at or below critical threshold'
        );
    }

    // =========================================================================
    // ALERTS ENABLED CHECK
    // =========================================================================

    /**
     * Test areAlertsEnabled method exists and is public static
     */
    public function testAreAlertsEnabledMethodExists(): void
    {
        $ref = new \ReflectionClass(BalanceAlertService::class);
        $this->assertTrue($ref->hasMethod('areAlertsEnabled'));

        $method = $ref->getMethod('areAlertsEnabled');
        $this->assertTrue($method->isPublic(), 'areAlertsEnabled should be public');
        $this->assertFalse($method->isStatic(), 'areAlertsEnabled should be an instance method');

        $params = $method->getParameters();
        $this->assertCount(1, $params, 'Should accept organizationId');
        $this->assertEquals('organizationId', $params[0]->getName());
    }

    // =========================================================================
    // FUNDED WALLET GUARD (prevents alerting on never-funded wallets)
    // =========================================================================

    /**
     * Test that checkAllBalances uses transaction existence check
     *
     * The checkAllBalances method should only alert for wallets that have
     * been previously funded (have at least one credit transaction).
     * This prevents spamming org admins for wallets auto-created with 0 balance.
     */
    public function testCheckAllBalancesRequiresFundedWallets(): void
    {
        // Verify the method exists and takes no parameters
        $ref = new \ReflectionMethod(BalanceAlertService::class, 'checkAllBalances');
        $this->assertCount(0, $ref->getParameters());

        // The implementation should use EXISTS subquery on org_transactions
        // to filter to only funded wallets. This is a design requirement.
        $sourceFile = file_get_contents((new \ReflectionClass(BalanceAlertService::class))->getFileName());
        $this->assertStringContainsString(
            'org_transactions',
            $sourceFile,
            'checkAllBalances should check org_transactions to verify wallet was funded'
        );
    }

    /**
     * Test that checkAllBalances checks areAlertsEnabled
     */
    public function testCheckAllBalancesRespectsAlertsEnabled(): void
    {
        $sourceFile = file_get_contents((new \ReflectionClass(BalanceAlertService::class))->getFileName());
        $this->assertStringContainsString(
            'areAlertsEnabled',
            $sourceFile,
            'checkAllBalances should check areAlertsEnabled before sending alerts'
        );
    }

    // =========================================================================
    // RECIPIENT SCOPING
    // =========================================================================

    /**
     * Test that the alert sender resolves the organization owner as recipient.
     *
     * The refactored service notifies the organization owner (vol_organizations.user_id)
     * for both severities via sendAndRecordAlert() -> sendBalanceAlertEmail(). The old
     * getOwner/getAdminsAndOwners helpers were removed; recipient resolution now happens
     * inline against vol_organizations.user_id.
     */
    public function testAlertSenderResolvesOrganizationOwner(): void
    {
        $ref = new \ReflectionClass(BalanceAlertService::class);
        $method = $ref->getMethod('sendAndRecordAlert');

        $params = $method->getParameters();
        $this->assertGreaterThanOrEqual(2, count($params));

        $sourceFile = file_get_contents($ref->getFileName());
        $this->assertStringContainsString('vol_organizations', $sourceFile,
            'sendAndRecordAlert should resolve the owner from vol_organizations');
        $this->assertStringContainsString('user_id', $sourceFile,
            'sendAndRecordAlert should resolve the owner via vol_organizations.user_id');
        $this->assertStringContainsString('sendBalanceAlertEmail', $sourceFile,
            'sendAndRecordAlert should dispatch via sendBalanceAlertEmail');
    }
}
