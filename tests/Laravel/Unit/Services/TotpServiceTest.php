<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\TotpService;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

class TotpServiceTest extends TestCase
{
    public function test_generateSecret_returns_non_empty_string(): void
    {
        $secret = TotpService::generateSecret();

        $this->assertIsString($secret);
        $this->assertNotEmpty($secret);
    }

    public function test_getProvisioningUri_returns_otpauth_uri(): void
    {
        $secret = TotpService::generateSecret();
        $uri = TotpService::getProvisioningUri($secret, 'user@example.com');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('user%40example.com', $uri);
        $this->assertStringContainsString('Project+NEXUS', $uri);
    }

    public function test_getProvisioningUri_with_custom_issuer(): void
    {
        $secret = TotpService::generateSecret();
        $uri = TotpService::getProvisioningUri($secret, 'test@test.com', 'Custom Issuer');

        $this->assertStringContainsString('Custom+Issuer', $uri);
    }

    public function test_verifyCode_returns_bool(): void
    {
        $secret = TotpService::generateSecret();

        // Test with wrong code
        $this->assertFalse(TotpService::verifyCode($secret, '000000'));
    }

    public function test_isEnabled_returns_false_when_no_settings(): void
    {
        DB::shouldReceive('selectOne')->andReturn(null);

        $this->assertFalse(TotpService::isEnabled(1));
    }

    public function test_isEnabled_returns_true_when_enabled(): void
    {
        DB::shouldReceive('selectOne')->andReturn((object) ['is_enabled' => 1]);

        $this->assertTrue(TotpService::isEnabled(1));
    }

    public function test_checkRateLimit_returns_not_limited_when_no_attempts(): void
    {
        DB::shouldReceive('selectOne')->andReturn((object) ['attempts' => 0]);

        $result = TotpService::checkRateLimit(1);

        $this->assertFalse($result['limited']);
        $this->assertNull($result['retry_after']);
    }

    public function test_checkRateLimit_returns_limited_after_max_attempts(): void
    {
        DB::shouldReceive('selectOne')
            ->andReturn(
                (object) ['attempts' => 5],
                (object) ['oldest' => date('Y-m-d H:i:s', time() - 60)]
            );

        $result = TotpService::checkRateLimit(1);

        $this->assertTrue($result['limited']);
        $this->assertNotNull($result['retry_after']);
    }

    public function test_isSetupRequired_returns_true_when_no_user(): void
    {
        DB::shouldReceive('selectOne')->andReturn(null);

        $this->assertTrue(TotpService::isSetupRequired(1));
    }

    public function test_getBackupCodeCount_returns_zero_when_none(): void
    {
        DB::shouldReceive('selectOne')->andReturn((object) ['count' => 0]);

        $this->assertEquals(0, TotpService::getBackupCodeCount(1));
    }

    public function test_getTrustedDeviceCount_returns_integer(): void
    {
        DB::shouldReceive('selectOne')->andReturn((object) ['count' => 3]);

        $this->assertEquals(3, TotpService::getTrustedDeviceCount(1));
    }

    public function test_adminReset_requires_reason(): void
    {
        $result = TotpService::adminReset(1, 99, '');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('reason', $result['error']);
    }

    public function test_adminReset_requires_non_whitespace_reason(): void
    {
        $result = TotpService::adminReset(1, 99, '   ');

        $this->assertFalse($result['success']);
    }
}
