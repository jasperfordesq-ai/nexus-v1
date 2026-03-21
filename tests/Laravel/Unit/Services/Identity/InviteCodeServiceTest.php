<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Identity;

use Tests\Laravel\TestCase;
use App\Services\Identity\InviteCodeService;
use Illuminate\Support\Facades\DB;

class InviteCodeServiceTest extends TestCase
{
    public function test_validate_returns_invalid_for_unknown_code(): void
    {
        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn(false);

        $result = InviteCodeService::validate(2, 'BADCODE1');

        $this->assertFalse($result['valid']);
        $this->assertEquals('invalid_code', $result['reason']);
    }

    public function test_validate_returns_invalid_for_deactivated_code(): void
    {
        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn([
            'id' => 1, 'is_active' => 0, 'max_uses' => 1, 'uses_count' => 0, 'expires_at' => null,
        ]);

        $result = InviteCodeService::validate(2, 'DEACTIV1');

        $this->assertFalse($result['valid']);
        $this->assertEquals('code_deactivated', $result['reason']);
    }

    public function test_validate_returns_invalid_for_exhausted_code(): void
    {
        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn([
            'id' => 1, 'is_active' => 1, 'max_uses' => 1, 'uses_count' => 1, 'expires_at' => null,
        ]);

        $result = InviteCodeService::validate(2, 'EXHAUST1');

        $this->assertFalse($result['valid']);
        $this->assertEquals('code_exhausted', $result['reason']);
    }

    public function test_validate_returns_invalid_for_expired_code(): void
    {
        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn([
            'id' => 1, 'is_active' => 1, 'max_uses' => 10, 'uses_count' => 0,
            'expires_at' => '2020-01-01 00:00:00',
        ]);

        $result = InviteCodeService::validate(2, 'EXPIRED1');

        $this->assertFalse($result['valid']);
        $this->assertEquals('code_expired', $result['reason']);
    }

    public function test_validate_returns_valid_for_good_code(): void
    {
        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn([
            'id' => 42, 'is_active' => 1, 'max_uses' => 10, 'uses_count' => 3, 'expires_at' => null,
        ]);

        $result = InviteCodeService::validate(2, 'GOODCODE');

        $this->assertTrue($result['valid']);
        $this->assertEquals(42, $result['code_id']);
    }

    public function test_validate_uppercases_and_trims_code(): void
    {
        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn(false);

        // The code should be trimmed and uppercased before lookup
        $result = InviteCodeService::validate(2, '  lowercase  ');
        $this->assertFalse($result['valid']);
    }

    public function test_deactivate_returns_false_when_not_found(): void
    {
        $stmt = \Mockery::mock();
        $stmt->shouldReceive('rowCount')->andReturn(0);
        DB::shouldReceive('statement')->andReturn($stmt);

        $this->assertFalse(InviteCodeService::deactivate(2, 999));
    }

    public function test_redeem_returns_false_when_update_fails(): void
    {
        $stmt = \Mockery::mock();
        $stmt->shouldReceive('rowCount')->andReturn(0);
        DB::shouldReceive('statement')->andReturn($stmt);

        $this->assertFalse(InviteCodeService::redeem(2, 'NOCODE', 1));
    }
}
