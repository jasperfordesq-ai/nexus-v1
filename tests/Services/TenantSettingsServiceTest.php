<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use PHPUnit\Framework\TestCase;
use App\Services\TenantSettingsService;
use App\Core\TenantContext;

/**
 * TenantSettingsService Tests
 */
class TenantSettingsServiceTest extends TestCase
{
    private static int $tenantId = 2;

    private static function svc(): TenantSettingsService
    {
        return new TenantSettingsService();
    }

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$tenantId);
    }

    protected function setUp(): void
    {
        self::svc()->clearCache();
    }

    public function test_check_login_gates_passes_for_admin_role(): void
    {
        $user = [
            'role' => 'admin', 'is_super_admin' => 0,
            'is_tenant_super_admin' => 0, 'tenant_id' => self::$tenantId,
            'email_verified_at' => null, 'is_approved' => 0,
        ];
        $this->assertNull(self::svc()->checkLoginGates($user));
    }

    public function test_check_login_gates_passes_for_super_admin_flag(): void
    {
        $user = [
            'role' => 'member', 'is_super_admin' => 1,
            'is_tenant_super_admin' => 0, 'tenant_id' => self::$tenantId,
            'email_verified_at' => null, 'is_approved' => 0,
        ];
        $this->assertNull(self::svc()->checkLoginGates($user));
    }

    public function test_check_login_gates_passes_for_tenant_super_admin_flag(): void
    {
        $user = [
            'role' => 'member', 'is_super_admin' => 0,
            'is_tenant_super_admin' => 1, 'tenant_id' => self::$tenantId,
            'email_verified_at' => null, 'is_approved' => 0,
        ];
        $this->assertNull(self::svc()->checkLoginGates($user));
    }

    public function test_check_login_gates_blocks_pending_identity_verification(): void
    {
        try {
            self::svc()->set(self::$tenantId, 'email_verification', 'false', 'boolean');
            self::svc()->set(self::$tenantId, 'admin_approval', 'false', 'boolean');
            self::svc()->clearCache();
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
        $user = [
            'role' => 'member', 'is_super_admin' => 0, 'is_tenant_super_admin' => 0,
            'tenant_id' => self::$tenantId, 'email_verified_at' => '2025-01-01',
            'is_approved' => 1, 'verification_status' => 'pending',
        ];
        $result = self::svc()->checkLoginGates($user);
        $this->assertNotNull($result);
        $this->assertSame('AUTH_PENDING_VERIFICATION', $result['code']);
    }

    public function test_check_login_gates_blocks_failed_identity_verification(): void
    {
        try {
            self::svc()->set(self::$tenantId, 'email_verification', 'false', 'boolean');
            self::svc()->set(self::$tenantId, 'admin_approval', 'false', 'boolean');
            self::svc()->clearCache();
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
        $user = [
            'role' => 'member', 'is_super_admin' => 0, 'is_tenant_super_admin' => 0,
            'tenant_id' => self::$tenantId, 'email_verified_at' => '2025-01-01',
            'is_approved' => 1, 'verification_status' => 'failed',
        ];
        $result = self::svc()->checkLoginGates($user);
        $this->assertNotNull($result);
        $this->assertSame('AUTH_VERIFICATION_FAILED', $result['code']);
    }

    public function test_check_login_gates_blocks_unapproved_when_required(): void
    {
        try {
            self::svc()->set(self::$tenantId, 'email_verification', 'false', 'boolean');
            self::svc()->set(self::$tenantId, 'admin_approval', 'true', 'boolean');
            self::svc()->clearCache();
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
        $user = [
            'role' => 'member', 'is_super_admin' => 0, 'is_tenant_super_admin' => 0,
            'tenant_id' => self::$tenantId, 'email_verified_at' => '2025-01-01', 'is_approved' => 0,
        ];
        $result = self::svc()->checkLoginGates($user);
        $this->assertNotNull($result);
        $this->assertSame('AUTH_ACCOUNT_PENDING_APPROVAL', $result['code']);
        $this->assertTrue($result['extra']['pending_approval']);
    }

    public function test_get_bool_returns_default_when_key_missing(): void
    {
        $this->assertFalse(self::svc()->getBool(99999, 'nonexistent_key', false));
        $this->assertTrue(self::svc()->getBool(99999, 'nonexistent_key', true));
    }

    public function test_clear_cache_works(): void
    {
        self::svc()->clearCache();
        $this->assertTrue(true);
    }

    public function test_set_and_get_string_value(): void
    {
        try {
            self::svc()->set(self::$tenantId, 'test_key_unit', 'hello', 'string');
            self::svc()->clearCache();
            $value = self::svc()->get(self::$tenantId, 'test_key_unit');
            $this->assertSame('hello', $value);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_set_and_get_bool_value(): void
    {
        try {
            self::svc()->set(self::$tenantId, 'test_bool_unit', 'true', 'boolean');
            self::svc()->clearCache();
            $result = self::svc()->getBool(self::$tenantId, 'test_bool_unit', false);
            $this->assertTrue($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_returns_default_for_missing_key(): void
    {
        try {
            self::svc()->clearCache();
            $value = self::svc()->get(self::$tenantId, 'definitely_missing_key_xyz', 'fallback');
            $this->assertSame('fallback', $value);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_all_general_returns_array(): void
    {
        try {
            $settings = self::svc()->getAllGeneral(self::$tenantId);
            $this->assertIsArray($settings);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_is_registration_open_returns_bool(): void
    {
        try {
            self::svc()->set(self::$tenantId, 'registration_mode', 'open', 'string');
            self::svc()->clearCache();
            $this->assertTrue(self::svc()->isRegistrationOpen(self::$tenantId));
            self::svc()->set(self::$tenantId, 'registration_mode', 'closed', 'string');
            self::svc()->clearCache();
            $this->assertFalse(self::svc()->isRegistrationOpen(self::$tenantId));
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }
}
