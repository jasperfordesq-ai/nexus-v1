<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\PasswordHistoryService;
use Illuminate\Support\Facades\DB;
use Mockery;

class PasswordHistoryServiceTest extends TestCase
{
    private function mockHistoryQuery(array $hashes): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('orderByDesc')->andReturnSelf();
        $builder->shouldReceive('limit')->andReturnSelf();
        $builder->shouldReceive('pluck')->andReturn(collect($hashes));

        DB::shouldReceive('table')->with('user_password_history')->andReturn($builder);
    }

    public function test_isReused_true_when_password_matches_current_hash(): void
    {
        $current = password_hash('CorrectHorseBattery1!', PASSWORD_ARGON2ID);

        // Matches current hash — must return true without consulting history
        DB::shouldReceive('table')->never();

        $this->assertTrue(
            PasswordHistoryService::isReused(1, 2, 'CorrectHorseBattery1!', $current)
        );
    }

    public function test_isReused_true_when_password_in_history(): void
    {
        $old = password_hash('OldPassword123!', PASSWORD_ARGON2ID);
        $this->mockHistoryQuery([$old]);

        $current = password_hash('CurrentPassword456!', PASSWORD_ARGON2ID);

        $this->assertTrue(
            PasswordHistoryService::isReused(1, 2, 'OldPassword123!', $current)
        );
    }

    public function test_isReused_false_for_fresh_password(): void
    {
        $old = password_hash('OldPassword123!', PASSWORD_ARGON2ID);
        $this->mockHistoryQuery([$old]);

        $current = password_hash('CurrentPassword456!', PASSWORD_ARGON2ID);

        $this->assertFalse(
            PasswordHistoryService::isReused(1, 2, 'BrandNewPassword789!', $current)
        );
    }

    public function test_isReused_false_when_history_empty(): void
    {
        $this->mockHistoryQuery([]);

        $this->assertFalse(
            PasswordHistoryService::isReused(1, 2, 'AnyPassword123!', null)
        );
    }

    public function test_isReused_fails_open_when_history_table_unavailable(): void
    {
        DB::shouldReceive('table')
            ->with('user_password_history')
            ->andThrow(new \RuntimeException('table missing'));

        $this->assertFalse(
            PasswordHistoryService::isReused(1, 2, 'AnyPassword123!', null)
        );
    }

    public function test_isReused_depth_zero_skips_history_but_rejects_current(): void
    {
        config(['auth.password_history_depth' => 0]);

        DB::shouldReceive('table')->never();

        $current = password_hash('CurrentPassword456!', PASSWORD_ARGON2ID);

        $this->assertTrue(
            PasswordHistoryService::isReused(1, 2, 'CurrentPassword456!', $current)
        );
        $this->assertFalse(
            PasswordHistoryService::isReused(1, 2, 'SomethingElse789!', $current)
        );
    }

    public function test_record_skips_empty_hash(): void
    {
        DB::shouldReceive('table')->never();

        PasswordHistoryService::record(1, 2, null);
        PasswordHistoryService::record(1, 2, '');

        // Reaching here without a DB call is the assertion (see never() above)
        $this->assertTrue(true);
    }

    public function test_record_inserts_and_prunes(): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('insert')->once()->andReturn(true);
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('orderByDesc')->andReturnSelf();
        $builder->shouldReceive('limit')->andReturnSelf();
        $builder->shouldReceive('pluck')->andReturn(collect([10, 9, 8, 7, 6]));
        $builder->shouldReceive('whereNotIn')->andReturnSelf();
        $builder->shouldReceive('delete')->once()->andReturn(2);

        DB::shouldReceive('table')->with('user_password_history')->andReturn($builder);

        PasswordHistoryService::record(1, 2, password_hash('OldPassword123!', PASSWORD_ARGON2ID));

        // insert + delete expectations above are the assertions
        $this->assertTrue(true);
    }

    public function test_depth_defaults_to_five_and_never_negative(): void
    {
        config(['auth.password_history_depth' => null]);
        $this->assertSame(PasswordHistoryService::DEFAULT_DEPTH, PasswordHistoryService::depth());

        config(['auth.password_history_depth' => -3]);
        $this->assertSame(0, PasswordHistoryService::depth());

        config(['auth.password_history_depth' => 12]);
        $this->assertSame(12, PasswordHistoryService::depth());
    }
}
