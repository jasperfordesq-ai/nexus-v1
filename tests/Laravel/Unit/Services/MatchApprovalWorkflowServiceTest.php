<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\MatchApprovalWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Laravel\TestCase;

class MatchApprovalWorkflowServiceTest extends TestCase
{
    public function test_submitForApproval_returns_id_on_success(): void
    {
        DB::shouldReceive('table')->with('listings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['user_id' => 20]);

        DB::shouldReceive('table')->with('match_approvals')->andReturnSelf();
        DB::shouldReceive('insertGetId')->once()->andReturn(42);

        $result = MatchApprovalWorkflowService::submitForApproval(10, 1, ['match_score' => 80]);
        $this->assertSame(42, $result);
    }

    public function test_submitForApproval_returns_null_for_self_match(): void
    {
        DB::shouldReceive('table')->with('listings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['user_id' => 10]);

        $result = MatchApprovalWorkflowService::submitForApproval(10, 1, []);
        $this->assertNull($result);
    }

    public function test_submitForApproval_returns_null_when_listing_not_found(): void
    {
        DB::shouldReceive('table')->with('listings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        Log::shouldReceive('warning')->once();

        $result = MatchApprovalWorkflowService::submitForApproval(10, 999, []);
        $this->assertNull($result);
    }

    public function test_submitForApproval_handles_duplicate(): void
    {
        DB::shouldReceive('table')->with('listings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['user_id' => 20]);

        DB::shouldReceive('table')->with('match_approvals')->andReturnSelf();
        DB::shouldReceive('insertGetId')->andThrow(new \Exception('Duplicate entry'));

        Log::shouldReceive('info')->once();

        $result = MatchApprovalWorkflowService::submitForApproval(10, 1, []);
        $this->assertNull($result);
    }

    public function test_approveMatch_returns_true_on_success(): void
    {
        DB::shouldReceive('table')->with('match_approvals')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('update')->andReturn(1);

        Log::shouldReceive('info')->once();

        $this->assertTrue(MatchApprovalWorkflowService::approveMatch(1, 5));
    }

    public function test_approveMatch_returns_false_when_not_found(): void
    {
        DB::shouldReceive('table')->with('match_approvals')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('update')->andReturn(0);

        $this->assertFalse(MatchApprovalWorkflowService::approveMatch(999, 5));
    }

    public function test_rejectMatch_returns_true_on_success(): void
    {
        DB::shouldReceive('table')->with('match_approvals')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('update')->andReturn(1);

        Log::shouldReceive('info')->once();

        $this->assertTrue(MatchApprovalWorkflowService::rejectMatch(1, 5, 'Not relevant'));
    }

    public function test_bulkApprove_empty_ids_returns_zero(): void
    {
        $this->assertSame(0, MatchApprovalWorkflowService::bulkApprove([], 5));
    }

    public function test_bulkApprove_returns_affected_count(): void
    {
        DB::shouldReceive('update')->once()->andReturn(3);
        Log::shouldReceive('info')->once();

        $result = MatchApprovalWorkflowService::bulkApprove([1, 2, 3], 5);
        $this->assertSame(3, $result);
    }

    public function test_bulkReject_empty_ids_returns_zero(): void
    {
        $this->assertSame(0, MatchApprovalWorkflowService::bulkReject([], 5));
    }

    public function test_getStatistics_returns_structure(): void
    {
        DB::shouldReceive('select')->andReturn(
            [(object) ['status' => 'pending', 'cnt' => 5], (object) ['status' => 'approved', 'cnt' => 10]],
        );
        DB::shouldReceive('selectOne')->andReturn((object) ['avg_hours' => 2.5]);
        DB::shouldReceive('select')->andReturn([]);

        $result = MatchApprovalWorkflowService::getStatistics(30);

        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('pending', $result);
        $this->assertArrayHasKey('approval_rate', $result);
        $this->assertArrayHasKey('avg_review_hours', $result);
    }

    public function test_getStatistics_handles_error(): void
    {
        DB::shouldReceive('select')->andThrow(new \Exception('Error'));
        Log::shouldReceive('error')->once();

        $result = MatchApprovalWorkflowService::getStatistics();
        $this->assertSame(0, $result['total']);
    }
}
