<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Models;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\PayPlan;

/**
 * PayPlan Model Tests
 *
 * Tests plan retrieval, slug lookup, tenant assignment,
 * feature checking, layout access, tier levels, and comparison.
 */
class PayPlanTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
    }

    // ==========================================
    // All Tests
    // ==========================================

    public function testAllReturnsArray(): void
    {
        $plans = PayPlan::all();
        $this->assertIsArray($plans);
    }

    public function testAllIncludesInactive(): void
    {
        $allPlans = PayPlan::all(false);
        $activePlans = PayPlan::all(true);
        $this->assertIsArray($allPlans);
        $this->assertIsArray($activePlans);
        $this->assertGreaterThanOrEqual(count($activePlans), count($allPlans));
    }

    // ==========================================
    // Find Tests
    // ==========================================

    public function testFindReturnsPlan(): void
    {
        $plans = PayPlan::all(false);
        if (!empty($plans)) {
            $plan = PayPlan::find($plans[0]['id']);
            $this->assertNotFalse($plan);
            $this->assertEquals($plans[0]['id'], $plan['id']);
        } else {
            $this->markTestSkipped('No plans in database');
        }
    }

    public function testFindReturnsFalseForNonExistent(): void
    {
        $plan = PayPlan::find(999999999);
        $this->assertFalse($plan);
    }

    // ==========================================
    // FindBySlug Tests
    // ==========================================

    public function testFindBySlugReturnsPlan(): void
    {
        $plans = PayPlan::all(false);
        if (!empty($plans)) {
            $plan = PayPlan::findBySlug($plans[0]['slug']);
            $this->assertNotFalse($plan);
        } else {
            $this->markTestSkipped('No plans in database');
        }
    }

    public function testFindBySlugReturnsFalseForNonExistent(): void
    {
        $plan = PayPlan::findBySlug('nonexistent-plan-slug-xyz');
        $this->assertFalse($plan);
    }

    // ==========================================
    // GetTierLevel Tests
    // ==========================================

    public function testGetTierLevelReturnsInt(): void
    {
        $tier = PayPlan::getTierLevel();
        $this->assertIsInt($tier);
        $this->assertGreaterThanOrEqual(0, $tier);
    }

    // ==========================================
    // GetTenantFeatures Tests
    // ==========================================

    public function testGetTenantFeaturesReturnsArray(): void
    {
        $features = PayPlan::getTenantFeatures();
        $this->assertIsArray($features);
    }

    // ==========================================
    // GetAllowedLayouts Tests
    // ==========================================

    public function testGetAllowedLayoutsReturnsArray(): void
    {
        $layouts = PayPlan::getAllowedLayouts();
        $this->assertIsArray($layouts);
    }

    // ==========================================
    // GetPlanLimits Tests
    // ==========================================

    public function testGetPlanLimitsReturnsStructure(): void
    {
        $limits = PayPlan::getPlanLimits();
        $this->assertIsArray($limits);
        $this->assertArrayHasKey('max_menus', $limits);
        $this->assertArrayHasKey('max_menu_items', $limits);
    }

    // ==========================================
    // GetComparison Tests
    // ==========================================

    public function testGetComparisonReturnsArray(): void
    {
        $comparison = PayPlan::getComparison();
        $this->assertIsArray($comparison);
    }

    public function testGetComparisonIncludesExpectedFields(): void
    {
        $comparison = PayPlan::getComparison();
        $this->assertIsArray($comparison);
        if (!empty($comparison)) {
            $this->assertArrayHasKey('id', $comparison[0]);
            $this->assertArrayHasKey('name', $comparison[0]);
            $this->assertArrayHasKey('slug', $comparison[0]);
            $this->assertArrayHasKey('tier_level', $comparison[0]);
            $this->assertArrayHasKey('features', $comparison[0]);
        }
    }

    // ==========================================
    // HasExpired Tests
    // ==========================================

    public function testHasExpiredReturnsBool(): void
    {
        $result = PayPlan::hasExpired();
        $this->assertIsBool($result);
    }

    // ==========================================
    // IsInTrial Tests
    // ==========================================

    public function testIsInTrialReturnsBool(): void
    {
        $result = PayPlan::isInTrial();
        $this->assertIsBool($result);
    }
}
