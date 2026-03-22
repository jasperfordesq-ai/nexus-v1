<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Identity;

use Tests\Laravel\TestCase;
use App\Services\Identity\IdentityVerificationSessionService;
use Illuminate\Support\Facades\DB;

class IdentityVerificationSessionServiceTest extends TestCase
{
    public function test_get_by_id_returns_null_when_not_found(): void
    {
        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn(false);

        $result = IdentityVerificationSessionService::getById(999);

        $this->assertNull($result);
    }

    public function test_get_by_id_returns_array_when_found(): void
    {
        $row = ['id' => 1, 'tenant_id' => 2, 'user_id' => 5, 'status' => 'created'];

        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn($row);

        $result = IdentityVerificationSessionService::getById(1);

        $this->assertSame($row, $result);
    }

    public function test_find_by_provider_session_returns_null_when_not_found(): void
    {
        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn(false);

        $result = IdentityVerificationSessionService::findByProviderSession('stripe_identity', 'vs_unknown');

        $this->assertNull($result);
    }

    public function test_get_latest_for_user_returns_null_when_none(): void
    {
        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn(false);

        $result = IdentityVerificationSessionService::getLatestForUser(2, 99);

        $this->assertNull($result);
    }

    public function test_update_status_calls_db_for_terminal_passed_status(): void
    {
        DB::shouldReceive('statement')
            ->once()
            ->with(\Mockery::type('string'), \Mockery::on(function (array $params) {
                // First param is status, fifth param is completed_at (non-null for terminal)
                return $params[0] === 'passed' && $params[4] !== null;
            }))
            ->andReturn(true);

        IdentityVerificationSessionService::updateStatus(1, 'passed');
    }

    public function test_update_status_calls_db_for_terminal_failed_status(): void
    {
        DB::shouldReceive('statement')
            ->once()
            ->with(\Mockery::type('string'), \Mockery::on(function (array $params) {
                return $params[0] === 'failed' && $params[4] !== null;
            }))
            ->andReturn(true);

        IdentityVerificationSessionService::updateStatus(2, 'failed', null, null, 'Document mismatch');
    }

    public function test_update_status_calls_db_for_terminal_expired_status(): void
    {
        DB::shouldReceive('statement')
            ->once()
            ->with(\Mockery::type('string'), \Mockery::on(function (array $params) {
                return $params[0] === 'expired' && $params[4] !== null;
            }))
            ->andReturn(true);

        IdentityVerificationSessionService::updateStatus(3, 'expired');
    }

    public function test_update_status_calls_db_for_terminal_cancelled_status(): void
    {
        DB::shouldReceive('statement')
            ->once()
            ->with(\Mockery::type('string'), \Mockery::on(function (array $params) {
                return $params[0] === 'cancelled' && $params[4] !== null;
            }))
            ->andReturn(true);

        IdentityVerificationSessionService::updateStatus(4, 'cancelled');
    }

    public function test_update_status_does_not_set_completed_at_for_processing(): void
    {
        DB::shouldReceive('statement')
            ->once()
            ->with(\Mockery::type('string'), \Mockery::on(function (array $params) {
                // completed_at is index 4; must be null for non-terminal status
                return $params[0] === 'processing' && $params[4] === null;
            }))
            ->andReturn(true);

        IdentityVerificationSessionService::updateStatus(5, 'processing');
    }

    public function test_expire_abandoned_returns_row_count(): void
    {
        $stmt = \Mockery::mock();
        $stmt->shouldReceive('rowCount')->andReturn(3);
        DB::shouldReceive('statement')->andReturn($stmt);

        $count = IdentityVerificationSessionService::expireAbandoned(72);

        $this->assertSame(3, $count);
    }

    public function test_purge_old_sessions_returns_row_count(): void
    {
        $stmt = \Mockery::mock();
        $stmt->shouldReceive('rowCount')->andReturn(10);
        DB::shouldReceive('statement')->andReturn($stmt);

        $count = IdentityVerificationSessionService::purgeOldSessions(180);

        $this->assertSame(10, $count);
    }

    public function test_get_all_for_user_returns_array(): void
    {
        $rows = [
            ['id' => 1, 'status' => 'passed'],
            ['id' => 2, 'status' => 'failed'],
        ];

        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetchAll')->andReturn($rows);

        $result = IdentityVerificationSessionService::getAllForUser(2, 5);

        $this->assertCount(2, $result);
    }
}
