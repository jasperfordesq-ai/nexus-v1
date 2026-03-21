<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FederationNeighborhoodService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FederationNeighborhoodServiceTest extends TestCase
{
    private FederationNeighborhoodService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FederationNeighborhoodService();
    }

    public function test_create_rejects_empty_name(): void
    {
        $result = $this->service->create('');
        $this->assertNull($result);
        $this->assertContains('Name is required', $this->service->getErrors());
    }

    public function test_create_returns_id_on_success(): void
    {
        DB::shouldReceive('insert')->once();
        DB::shouldReceive('getPdo->lastInsertId')->andReturn(5);
        Log::shouldReceive('info')->once();

        $result = $this->service->create('Test Neighborhood', 'Description', 'Europe');
        $this->assertEquals(5, $result);
    }

    public function test_create_returns_null_on_db_error(): void
    {
        DB::shouldReceive('insert')->andThrow(new \Exception('error'));
        Log::shouldReceive('error')->once();

        $result = $this->service->create('Test');
        $this->assertNull($result);
        $this->assertNotEmpty($this->service->getErrors());
    }

    public function test_update_rejects_empty_name(): void
    {
        $result = $this->service->update(1, ['name' => '']);
        $this->assertFalse($result);
    }

    public function test_update_returns_true_when_nothing_to_update(): void
    {
        $result = $this->service->update(1, []);
        $this->assertTrue($result);
    }

    public function test_update_returns_false_when_not_found(): void
    {
        DB::shouldReceive('update')->andReturn(0);

        $result = $this->service->update(999, ['name' => 'Updated']);
        $this->assertFalse($result);
    }

    public function test_delete_returns_false_when_not_found(): void
    {
        DB::shouldReceive('delete')->andReturn(0, 0);

        $result = $this->service->delete(999);
        $this->assertFalse($result);
    }

    public function test_delete_removes_tenants_and_neighborhood(): void
    {
        DB::shouldReceive('delete')->twice()->andReturn(2, 1);
        Log::shouldReceive('info')->once();

        $result = $this->service->delete(1);
        $this->assertTrue($result);
    }

    public function test_getById_returns_null_when_not_found(): void
    {
        DB::shouldReceive('selectOne')->andReturn(null);

        $this->assertNull($this->service->getById(999));
    }

    public function test_listAllStatic_returns_empty_on_error(): void
    {
        DB::shouldReceive('select')->andThrow(new \Exception('error'));
        Log::shouldReceive('error')->once();

        $this->assertEquals([], FederationNeighborhoodService::listAllStatic());
    }

    public function test_createStatic_delegates_to_instance(): void
    {
        DB::shouldReceive('insert')->once();
        DB::shouldReceive('getPdo->lastInsertId')->andReturn(7);
        Log::shouldReceive('info')->once();

        $result = FederationNeighborhoodService::createStatic('Static Test');
        $this->assertEquals(7, $result);
    }
}
