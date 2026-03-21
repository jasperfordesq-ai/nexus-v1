<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\InsuranceCertificateService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class InsuranceCertificateServiceTest extends TestCase
{
    private InsuranceCertificateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InsuranceCertificateService();
    }

    public function test_getUserCertificates_returns_array(): void
    {
        DB::shouldReceive('table')->with('insurance_certificates')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([
            (object) ['id' => 1, 'user_id' => 10, 'status' => 'verified'],
        ]));

        $result = $this->service->getUserCertificates(10);
        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['id']);
    }

    public function test_getById_returns_null_when_not_found(): void
    {
        DB::shouldReceive('table')->with('insurance_certificates')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $this->assertNull($this->service->getById(999));
    }

    public function test_getById_returns_array_when_found(): void
    {
        DB::shouldReceive('table')->with('insurance_certificates')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['id' => 1, 'status' => 'pending']);

        $result = $this->service->getById(1);
        $this->assertIsArray($result);
        $this->assertSame(1, $result['id']);
    }

    public function test_create_returns_new_id(): void
    {
        DB::shouldReceive('table')->with('insurance_certificates')->andReturnSelf();
        DB::shouldReceive('insertGetId')->once()->andReturn(42);

        $id = $this->service->create(['user_id' => 10]);
        $this->assertSame(42, $id);
    }

    public function test_update_returns_true_on_success(): void
    {
        DB::shouldReceive('table')->with('insurance_certificates')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('update')->andReturn(1);

        $result = $this->service->update(1, ['status' => 'submitted']);
        $this->assertTrue($result);
    }

    public function test_update_returns_false_when_not_found(): void
    {
        DB::shouldReceive('table')->with('insurance_certificates')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('update')->andReturn(0);

        $this->assertFalse($this->service->update(999, ['status' => 'submitted']));
    }

    public function test_verify_updates_status(): void
    {
        DB::shouldReceive('table')->with('insurance_certificates')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('update')->andReturn(1);

        $this->assertTrue($this->service->verify(1, 5));
    }

    public function test_reject_updates_status_with_reason(): void
    {
        DB::shouldReceive('table')->with('insurance_certificates')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('update')->andReturn(1);

        $this->assertTrue($this->service->reject(1, 5, 'Expired document'));
    }

    public function test_delete_returns_false_when_not_found(): void
    {
        DB::shouldReceive('table')->with('insurance_certificates')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $this->assertFalse($this->service->delete(999));
    }

    public function test_getStats_returns_counts(): void
    {
        DB::shouldReceive('table')->with('insurance_certificates')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('selectRaw')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) [
            'total' => 10, 'pending' => 3, 'submitted' => 2, 'verified' => 4,
            'expired' => 1, 'rejected' => 0, 'revoked' => 0, 'expiring_soon' => 1,
        ]);

        $result = $this->service->getStats();
        $this->assertSame(10, $result['total']);
        $this->assertSame(3, $result['pending']);
    }
}
