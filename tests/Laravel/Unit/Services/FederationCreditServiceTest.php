<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FederationCreditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FederationCreditServiceTest extends TestCase
{
    private FederationCreditService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FederationCreditService();
    }

    public function test_createAgreementStatic_rejects_same_tenant(): void
    {
        $result = FederationCreditService::createAgreementStatic(1, 1);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('same tenant', $result['error']);
    }

    public function test_createAgreementStatic_rejects_existing_active(): void
    {
        $existing = (object) ['id' => 1, 'status' => 'active'];
        DB::shouldReceive('selectOne')->andReturn($existing);

        $result = FederationCreditService::createAgreementStatic(1, 2);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already exists', $result['error']);
    }

    public function test_createAgreementStatic_succeeds(): void
    {
        DB::shouldReceive('selectOne')->andReturn(null);
        DB::shouldReceive('insert')->once();
        DB::shouldReceive('getPdo->lastInsertId')->andReturn(5);
        Log::shouldReceive('info')->once();

        $result = FederationCreditService::createAgreementStatic(1, 2, 1.5, 100.0, 10);
        $this->assertTrue($result['success']);
        $this->assertEquals(5, $result['id']);
        $this->assertEquals(1.5, $result['exchange_rate']);
    }

    public function test_approveAgreement_fails_when_not_found(): void
    {
        DB::shouldReceive('selectOne')->andReturn(null);

        $result = $this->service->approveAgreement(999, 1);
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($this->service->getErrors());
    }

    public function test_approveAgreement_succeeds(): void
    {
        // Test tenant is 2; give an agreement where tenant 2 is a party
        $agreement = (object) [
            'id' => 1,
            'status' => 'pending',
            'from_tenant_id' => 2,
            'to_tenant_id' => 3,
        ];
        DB::shouldReceive('selectOne')->andReturn($agreement);
        DB::shouldReceive('update')->once();
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $result = $this->service->approveAgreement(1, 5);
        $this->assertTrue($result['success']);
        $this->assertEquals('active', $result['status']);
    }

    public function test_updateAgreementStatus_rejects_invalid_status(): void
    {
        $result = $this->service->updateAgreementStatus(1, 'invalid');
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($this->service->getErrors());
    }

    public function test_updateAgreementStatus_succeeds(): void
    {
        // updateAgreementStatus now does selectOne first to verify tenant is a party,
        // then update. Test tenant is 2.
        $agreement = (object) [
            'id' => 1,
            'status' => 'active',
            'from_tenant_id' => 2,
            'to_tenant_id' => 3,
        ];
        DB::shouldReceive('selectOne')->andReturn($agreement);
        DB::shouldReceive('update')->andReturn(1);
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $result = $this->service->updateAgreementStatus(1, 'suspended');
        $this->assertTrue($result['success']);
    }

    public function test_updateAgreementStatus_rejects_unauthorized_tenant(): void
    {
        $agreement = (object) [
            'id' => 1,
            'status' => 'active',
            'from_tenant_id' => 99,
            'to_tenant_id' => 100,
        ];
        DB::shouldReceive('selectOne')->andReturn($agreement);

        $result = $this->service->updateAgreementStatus(1, 'suspended');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Unauthorized', $result['error']);
    }

    public function test_updateAgreementStatus_fails_when_not_found(): void
    {
        DB::shouldReceive('selectOne')->andReturn(null);

        $result = $this->service->updateAgreementStatus(999, 'active');
        $this->assertFalse($result['success']);
    }

    public function test_getAgreement_returns_null_when_not_found(): void
    {
        DB::shouldReceive('selectOne')->andReturn(null);

        $this->assertNull($this->service->getAgreement(1, 2));
    }

    public function test_getAgreement_returns_array_when_found(): void
    {
        $row = (object) [
            'id' => 1, 'from_tenant_id' => 1, 'from_tenant_name' => 'T1',
            'to_tenant_id' => 2, 'to_tenant_name' => 'T2',
            'exchange_rate' => 1.0, 'max_monthly_credits' => null,
            'status' => 'active', 'created_at' => '2026-01-01', 'updated_at' => null,
        ];
        DB::shouldReceive('selectOne')->andReturn($row);

        $result = $this->service->getAgreement(1, 2);
        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
    }

    public function test_listAgreementsStatic_returns_empty_on_error(): void
    {
        DB::shouldReceive('select')->andThrow(new \Exception('error'));
        Log::shouldReceive('error')->once();

        $this->assertEquals([], FederationCreditService::listAgreementsStatic(1));
    }

    public function test_getErrors_returns_accumulated_errors(): void
    {
        $this->assertIsArray($this->service->getErrors());
    }
}
