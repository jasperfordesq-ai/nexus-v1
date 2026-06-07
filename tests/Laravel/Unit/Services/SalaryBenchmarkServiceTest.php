<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\SalaryBenchmarkService;
use App\Models\SalaryBenchmark;
use Illuminate\Support\Facades\Log;
use Mockery;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SalaryBenchmarkServiceTest extends TestCase
{
    private $benchmarkAlias;

    protected function setUp(): void
    {
        // App\Models\SalaryBenchmark may already be autoloaded by app boot or an
        // earlier test in the combined run, so the alias mock MUST be created
        // before parent::setUp() and tolerate the class already existing.
        // shouldIgnoreMissing() makes boot-time/static calls no-ops; per-test
        // expectations are layered on the shared instance in each test.
        $this->benchmarkAlias = Mockery::mock('alias:' . SalaryBenchmark::class)->shouldIgnoreMissing();
        parent::setUp();
    }

    // ── findForTitle ────────────────────────────────────────────

    public function test_findForTitle_returns_matching_benchmark(): void
    {
        $benchmark = Mockery::mock();
        $benchmark->role_keyword = 'developer';
        $benchmark->shouldReceive('toArray')->andReturn([
            'id' => 1, 'role_keyword' => 'developer', 'salary_min' => 40000, 'salary_max' => 80000,
        ]);

        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('orWhereNull')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([$benchmark]));

        $mock = $this->benchmarkAlias;
        $mock->shouldReceive('orderByRaw')->andReturn($builder);

        $result = SalaryBenchmarkService::findForTitle('Senior Developer');
        $this->assertIsArray($result);
        $this->assertSame('developer', $result['role_keyword']);
    }

    public function test_findForTitle_is_case_insensitive(): void
    {
        $benchmark = Mockery::mock();
        $benchmark->role_keyword = 'Manager';
        $benchmark->shouldReceive('toArray')->andReturn([
            'id' => 2, 'role_keyword' => 'Manager', 'salary_min' => 50000,
        ]);

        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('orWhereNull')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([$benchmark]));

        $mock = $this->benchmarkAlias;
        $mock->shouldReceive('orderByRaw')->andReturn($builder);

        // Title is lowercased, and role_keyword is lowered for comparison
        $result = SalaryBenchmarkService::findForTitle('PROJECT MANAGER');
        $this->assertIsArray($result);
    }

    public function test_findForTitle_returns_null_when_no_match(): void
    {
        $benchmark = Mockery::mock();
        $benchmark->role_keyword = 'developer';

        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('orWhereNull')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([$benchmark]));

        $mock = $this->benchmarkAlias;
        $mock->shouldReceive('orderByRaw')->andReturn($builder);

        // "Chef" doesn't contain "developer"
        $result = SalaryBenchmarkService::findForTitle('Chef');
        $this->assertNull($result);
    }

    public function test_findForTitle_returns_null_on_exception(): void
    {
        Log::shouldReceive('error')->once();

        $mock = $this->benchmarkAlias;
        $mock->shouldReceive('orderByRaw')->andThrow(new \Exception('DB error'));

        $result = SalaryBenchmarkService::findForTitle('Developer');
        $this->assertNull($result);
    }

    public function test_findForTitle_prefers_tenant_specific_over_global(): void
    {
        // The query orders by tenant_id IS NULL ASC, so tenant-specific comes first
        $tenantBenchmark = Mockery::mock();
        $tenantBenchmark->role_keyword = 'developer';
        $tenantBenchmark->shouldReceive('toArray')->andReturn([
            'id' => 1, 'role_keyword' => 'developer', 'tenant_id' => $this->testTenantId,
        ]);

        $globalBenchmark = Mockery::mock();
        $globalBenchmark->role_keyword = 'developer';
        $globalBenchmark->shouldReceive('toArray')->andReturn([
            'id' => 2, 'role_keyword' => 'developer', 'tenant_id' => null,
        ]);

        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('orWhereNull')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([$tenantBenchmark, $globalBenchmark]));

        $mock = $this->benchmarkAlias;
        $mock->shouldReceive('orderByRaw')->andReturn($builder);

        $result = SalaryBenchmarkService::findForTitle('Developer');
        $this->assertIsArray($result);
        $this->assertSame($this->testTenantId, $result['tenant_id']);
    }

    // ── list ────────────────────────────────────────────────────

    public function test_list_returns_benchmarks_array(): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('orWhereNull')->andReturnSelf();
        $builder->shouldReceive('orderBy')->with('role_keyword')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([]));
        $builder->shouldReceive('get->toArray')->andReturn([]);

        $mock = $this->benchmarkAlias;
        $mock->shouldReceive('where')->andReturn($builder);

        $result = SalaryBenchmarkService::list();
        $this->assertIsArray($result);
    }

    public function test_list_returns_empty_on_exception(): void
    {
        Log::shouldReceive('error')->once();

        $mock = $this->benchmarkAlias;
        $mock->shouldReceive('where')->andThrow(new \Exception('DB error'));

        $result = SalaryBenchmarkService::list();
        $this->assertSame([], $result);
    }
}
