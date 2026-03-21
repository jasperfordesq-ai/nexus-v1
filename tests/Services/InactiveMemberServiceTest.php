<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\InactiveMemberService;
use App\Core\TenantContext;

/**
 * InactiveMemberService Tests
 */
class InactiveMemberServiceTest extends TestCase
{
    private InactiveMemberService $service;
    private static int $testTenantId = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
        $this->service = new InactiveMemberService();
    }

    public function test_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(InactiveMemberService::class, $this->service);
    }

    public function test_detect_inactive_returns_expected_keys(): void
    {
        $result = $this->service->detectInactive(self::$testTenantId);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('tenant_id', $result);
        $this->assertArrayHasKey('threshold_days', $result);
        $this->assertArrayHasKey('flagged_inactive', $result);
        $this->assertArrayHasKey('flagged_dormant', $result);
        $this->assertArrayHasKey('total_flagged', $result);
        $this->assertArrayHasKey('resolved', $result);
        $this->assertArrayHasKey('run_at', $result);
    }

    public function test_detect_inactive_default_threshold(): void
    {
        $result = $this->service->detectInactive(self::$testTenantId);
        $this->assertSame(90, $result['threshold_days']);
    }

    public function test_detect_inactive_custom_threshold(): void
    {
        $result = $this->service->detectInactive(self::$testTenantId, 30);
        $this->assertSame(30, $result['threshold_days']);
    }

    public function test_detect_inactive_tenant_id_in_result(): void
    {
        $result = $this->service->detectInactive(self::$testTenantId);
        $this->assertSame(self::$testTenantId, $result['tenant_id']);
    }

    public function test_get_inactive_members_returns_expected_structure(): void
    {
        $result = $this->service->getInactiveMembers(self::$testTenantId);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('members', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('threshold_days', $result);
        $this->assertIsArray($result['members']);
        $this->assertIsInt($result['total']);
    }

    public function test_get_inactive_members_respects_limit(): void
    {
        $result = $this->service->getInactiveMembers(self::$testTenantId, 90, null, 5);
        $this->assertLessThanOrEqual(5, count($result['members']));
    }

    public function test_get_inactive_members_with_flag_type_filter(): void
    {
        $result = $this->service->getInactiveMembers(self::$testTenantId, 90, 'dormant');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('members', $result);
    }

    public function test_get_inactive_members_invalid_flag_type_ignored(): void
    {
        $result = $this->service->getInactiveMembers(self::$testTenantId, 90, 'invalid_type');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('members', $result);
    }

    public function test_get_inactivity_stats_returns_expected_keys(): void
    {
        $result = $this->service->getInactivityStats(self::$testTenantId);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_active_members', $result);
        $this->assertArrayHasKey('total_flagged', $result);
        $this->assertArrayHasKey('inactive_count', $result);
        $this->assertArrayHasKey('dormant_count', $result);
        $this->assertArrayHasKey('at_risk_count', $result);
        $this->assertArrayHasKey('notified_count', $result);
        $this->assertArrayHasKey('inactivity_rate', $result);
    }

    public function test_get_inactivity_stats_values_are_numeric(): void
    {
        $result = $this->service->getInactivityStats(self::$testTenantId);
        $this->assertIsInt($result['total_active_members']);
        $this->assertIsInt($result['total_flagged']);
        $this->assertIsInt($result['inactive_count']);
        $this->assertIsInt($result['dormant_count']);
        $this->assertIsInt($result['at_risk_count']);
        $this->assertIsInt($result['notified_count']);
        $this->assertIsNumeric($result['inactivity_rate']);
    }

    public function test_mark_notified_with_empty_array_returns_zero(): void
    {
        $result = $this->service->markNotified(self::$testTenantId, []);
        $this->assertSame(0, $result);
    }

    public function test_mark_notified_with_nonexistent_users(): void
    {
        $result = $this->service->markNotified(self::$testTenantId, [999999, 999998]);
        $this->assertIsInt($result);
        $this->assertSame(0, $result);
    }
}
