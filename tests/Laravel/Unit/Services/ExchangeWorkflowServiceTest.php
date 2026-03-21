<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\ExchangeWorkflowService;

class ExchangeWorkflowServiceTest extends TestCase
{
    // ExchangeWorkflowService uses Eloquent models (ExchangeRequest, Listing, ExchangeHistory)
    // with complex state machine transitions. Best tested as integration tests.

    public function test_status_constants_are_defined(): void
    {
        $this->assertEquals('pending_provider', ExchangeWorkflowService::STATUS_PENDING_PROVIDER);
        $this->assertEquals('pending_broker', ExchangeWorkflowService::STATUS_PENDING_BROKER);
        $this->assertEquals('accepted', ExchangeWorkflowService::STATUS_ACCEPTED);
        $this->assertEquals('in_progress', ExchangeWorkflowService::STATUS_IN_PROGRESS);
        $this->assertEquals('pending_confirmation', ExchangeWorkflowService::STATUS_PENDING_CONFIRMATION);
        $this->assertEquals('completed', ExchangeWorkflowService::STATUS_COMPLETED);
        $this->assertEquals('disputed', ExchangeWorkflowService::STATUS_DISPUTED);
        $this->assertEquals('cancelled', ExchangeWorkflowService::STATUS_CANCELLED);
        $this->assertEquals('expired', ExchangeWorkflowService::STATUS_EXPIRED);
    }

    public function test_createRequest_returns_null_for_self_exchange(): void
    {
        $this->markTestIncomplete('Requires Eloquent model mocking for Listing::find()');
    }

    public function test_acceptRequest_rejects_wrong_provider(): void
    {
        $this->markTestIncomplete('Requires Eloquent model mocking for ExchangeRequest::find()');
    }

    public function test_cancelExchange_rejects_terminal_status(): void
    {
        $this->markTestIncomplete('Requires Eloquent model mocking for ExchangeRequest::find()');
    }

    public function test_getExchange_returns_null_when_not_found(): void
    {
        \Illuminate\Support\Facades\DB::shouldReceive('table->join->join->join->leftJoin->leftJoin->where->where->select->first')
            ->andReturn(null);

        $result = ExchangeWorkflowService::getExchange(999);
        $this->assertNull($result);
    }

    public function test_getStatistics_returns_expected_structure(): void
    {
        \Illuminate\Support\Facades\DB::shouldReceive('table->where->where->count')->andReturn(0);

        $result = ExchangeWorkflowService::getStatistics(30);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('completed', $result);
        $this->assertArrayHasKey('pending_broker', $result);
        $this->assertArrayHasKey('cancelled', $result);
        $this->assertArrayHasKey('disputed', $result);
        $this->assertArrayHasKey('days', $result);
    }

    public function test_checkComplianceRequirements_returns_empty_for_no_risk_tags(): void
    {
        \Illuminate\Support\Facades\DB::shouldReceive('table->where->where->first')->andReturn(null);

        $result = ExchangeWorkflowService::checkComplianceRequirements(1, 1);
        $this->assertEquals([], $result);
    }
}
