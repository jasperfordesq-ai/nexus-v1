<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\JobReferralService;
use App\Models\JobReferral;
use Illuminate\Support\Facades\Log;
use Mockery;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class JobReferralServiceTest extends TestCase
{
    // ── getOrCreate ─────────────────────────────────────────────

    public function test_getOrCreate_returns_existing_referral_for_same_user_and_vacancy(): void
    {
        $existing = Mockery::mock();
        $existing->shouldReceive('toArray')->andReturn([
            'id' => 1, 'vacancy_id' => 10, 'referrer_user_id' => 5, 'ref_token' => 'abc123',
        ]);

        $builder = Mockery::mock();
        $builder->shouldReceive('where')->with('tenant_id', $this->testTenantId)->andReturnSelf();
        $builder->shouldReceive('where')->with('vacancy_id', 10)->andReturnSelf();
        $builder->shouldReceive('where')->with('referrer_user_id', 5)->andReturnSelf();
        $builder->shouldReceive('where')->with('applied', false)->andReturnSelf();
        $builder->shouldReceive('first')->andReturn($existing);

        $mock = Mockery::mock('alias:' . JobReferral::class);
        $mock->shouldReceive('where')->andReturn($builder);

        $result = JobReferralService::getOrCreate(10, 5);
        $this->assertIsArray($result);
        $this->assertSame('abc123', $result['ref_token']);
    }

    public function test_getOrCreate_creates_new_referral_when_none_exists(): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('first')->andReturn(null);

        $newReferral = Mockery::mock();
        $newReferral->shouldReceive('toArray')->andReturn([
            'id' => 2, 'vacancy_id' => 10, 'referrer_user_id' => 5, 'ref_token' => 'new_token',
        ]);

        $mock = Mockery::mock('alias:' . JobReferral::class);
        $mock->shouldReceive('where')->andReturn($builder);
        $mock->shouldReceive('create')->once()->andReturn($newReferral);

        $result = JobReferralService::getOrCreate(10, 5);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('ref_token', $result);
    }

    public function test_getOrCreate_creates_new_referral_when_referrer_is_null(): void
    {
        $newReferral = Mockery::mock();
        $newReferral->shouldReceive('toArray')->andReturn([
            'id' => 3, 'vacancy_id' => 10, 'referrer_user_id' => null, 'ref_token' => 'anon_token',
        ]);

        $mock = Mockery::mock('alias:' . JobReferral::class);
        $mock->shouldReceive('create')->once()->andReturn($newReferral);

        // When referrerUserId is null, the service skips the existing check and creates directly
        $result = JobReferralService::getOrCreate(10, null);
        $this->assertIsArray($result);
    }

    public function test_getOrCreate_returns_empty_on_exception(): void
    {
        Log::shouldReceive('error')->once();

        $mock = Mockery::mock('alias:' . JobReferral::class);
        $mock->shouldReceive('where')->andThrow(new \Exception('DB error'));

        $result = JobReferralService::getOrCreate(10, 5);
        $this->assertSame([], $result);
    }

    // ── markApplied ─────────────────────────────────────────────

    public function test_markApplied_updates_referral_record(): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('where')->with('tenant_id', $this->testTenantId)->andReturnSelf();
        $builder->shouldReceive('where')->with('ref_token', 'abc123')->andReturnSelf();
        $builder->shouldReceive('where')->with('applied', false)->andReturnSelf();
        $builder->shouldReceive('update')->once()->with(Mockery::on(function ($data) {
            return $data['applied'] === true && $data['referred_user_id'] === 42;
        }))->andReturn(1);

        $mock = Mockery::mock('alias:' . JobReferral::class);
        $mock->shouldReceive('where')->andReturn($builder);

        // Should not throw
        JobReferralService::markApplied('abc123', 42);
        $this->assertTrue(true); // no exception
    }

    public function test_markApplied_logs_warning_on_exception(): void
    {
        Log::shouldReceive('warning')->once();

        $mock = Mockery::mock('alias:' . JobReferral::class);
        $mock->shouldReceive('where')->andThrow(new \Exception('DB error'));

        JobReferralService::markApplied('bad_token', 1);
        $this->assertTrue(true); // no exception propagated
    }

    // ── getStats ────────────────────────────────────────────────

    public function test_getStats_returns_counts(): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('count')->andReturn(10, 3); // total=10, applied=3

        $mock = Mockery::mock('alias:' . JobReferral::class);
        $mock->shouldReceive('where')->andReturn($builder);

        $result = JobReferralService::getStats(10);
        $this->assertArrayHasKey('total_shares', $result);
        $this->assertArrayHasKey('converted_applications', $result);
    }

    public function test_getStats_returns_zeros_on_exception(): void
    {
        Log::shouldReceive('error')->once();

        $mock = Mockery::mock('alias:' . JobReferral::class);
        $mock->shouldReceive('where')->andThrow(new \Exception('DB error'));

        $result = JobReferralService::getStats(10);
        $this->assertSame(['total_shares' => 0, 'converted_applications' => 0], $result);
    }

    public function test_getStats_returns_correct_structure(): void
    {
        $result = JobReferralService::getStats(999);
        $this->assertArrayHasKey('total_shares', $result);
        $this->assertArrayHasKey('converted_applications', $result);
        $this->assertIsInt($result['total_shares']);
        $this->assertIsInt($result['converted_applications']);
    }
}
