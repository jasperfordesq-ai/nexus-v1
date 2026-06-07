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

/**
 * MatchApprovalWorkflowService unit tests.
 *
 * These tests mock the DB + Log facades and exercise the service's static
 * methods. The service grew substantially since the original tests were
 * written — submit now runs a safeguarding-restriction check and a broker
 * notification fan-out, and approve/reject now read the row before updating
 * and dispatch a notification. The mocks below cover the full current happy
 * path: any unmocked DB terminal would fall through to the catch block and
 * trip Log::error, so the stubs deliberately satisfy every facade call the
 * current code makes. NotificationDispatcher fan-out is suppressed by
 * returning an empty broker collection from the users `get()` query.
 *
 * Vetting gating (SafeguardingTriggerService) uses Eloquent, which bypasses
 * the DB facade mock and reads the clean nexus_ci DB directly, returning no
 * required vetting types — so that gate is a no-op here.
 */
class MatchApprovalWorkflowServiceTest extends TestCase
{
    public function test_submitForApproval_returns_id_on_success(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('orWhere')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('raw')->andReturn('');
        // Listing owner lookup
        DB::shouldReceive('first')->andReturn((object) ['user_id' => 20]);
        // Safeguarding restriction check
        DB::shouldReceive('exists')->andReturn(false);
        // Insert the pending approval
        DB::shouldReceive('insertGetId')->once()->andReturn(42);
        // Name lookups for notifications
        DB::shouldReceive('value')->andReturn('Listing Title');
        // Broker fan-out — empty so dispatch loop is skipped
        DB::shouldReceive('get')->andReturn(collect([]));

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
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('orWhere')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['user_id' => 20]);
        DB::shouldReceive('exists')->andReturn(false);
        DB::shouldReceive('insertGetId')->andThrow(new \Exception('Duplicate entry'));

        Log::shouldReceive('info')->once();

        $result = MatchApprovalWorkflowService::submitForApproval(10, 1, []);
        $this->assertNull($result);
    }

    public function test_approveMatch_returns_true_on_success(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        // Row lookup before update
        DB::shouldReceive('first')->andReturn((object) [
            'id' => 1, 'user_id' => 20, 'listing_id' => 5, 'match_score' => 80,
        ]);
        DB::shouldReceive('update')->andReturn(1);
        // Listing title lookup for the approval notification
        DB::shouldReceive('value')->andReturn('Listing Title');

        Log::shouldReceive('info')->once();
        // Notification dispatch runs real code paths; any failure there is
        // swallowed by the service's inner try/catch as a warning and must
        // NOT fail this unit test of the approval state transition.
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        $this->assertTrue(MatchApprovalWorkflowService::approveMatch(1, 5));
    }

    public function test_approveMatch_returns_false_when_not_found(): void
    {
        DB::shouldReceive('table')->with('match_approvals')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $this->assertFalse(MatchApprovalWorkflowService::approveMatch(999, 5));
    }

    public function test_rejectMatch_returns_true_on_success(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) [
            'id' => 1, 'user_id' => 20, 'listing_id' => 5, 'match_score' => 80,
        ]);
        DB::shouldReceive('update')->andReturn(1);
        DB::shouldReceive('value')->andReturn('Listing Title');

        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        $this->assertTrue(MatchApprovalWorkflowService::rejectMatch(1, 5, 'Not relevant'));
    }

    public function test_bulkApprove_empty_ids_returns_zero(): void
    {
        $this->assertSame(0, MatchApprovalWorkflowService::bulkApprove([], 5));
    }

    public function test_bulkApprove_returns_affected_count(): void
    {
        // Pending-rows lookup, then the bulk UPDATE
        DB::shouldReceive('select')->once()->andReturn([]);
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
        // Both select() calls (status counts + top reviewers) return empty;
        // the method only needs to assemble the result structure without error.
        DB::shouldReceive('select')->andReturn([]);
        DB::shouldReceive('selectOne')->andReturn((object) ['avg_hours' => 2.5]);

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
