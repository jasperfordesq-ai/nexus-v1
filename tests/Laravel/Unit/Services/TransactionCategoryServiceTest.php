<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\TransactionCategoryService;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

class TransactionCategoryServiceTest extends TestCase
{
    private TransactionCategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TransactionCategoryService();
    }

    public function test_getAll_returns_array(): void
    {
        DB::shouldReceive('select')->andReturn([]);

        $result = $this->service->getAll();
        $this->assertIsArray($result);
    }

    public function test_getById_returns_null_when_not_found(): void
    {
        DB::shouldReceive('selectOne')->andReturn(null);

        $this->assertNull($this->service->getById(999));
    }

    public function test_create_returns_null_when_name_is_empty(): void
    {
        $this->assertNull($this->service->create(['name' => '']));
    }

    public function test_create_returns_null_when_name_is_whitespace(): void
    {
        $this->assertNull($this->service->create(['name' => '   ']));
    }

    public function test_update_returns_false_when_no_fields(): void
    {
        $result = $this->service->update(1, []);
        $this->assertFalse($result);
    }

    public function test_delete_returns_false_for_system_category(): void
    {
        $category = (object) ['is_system' => 1];
        DB::shouldReceive('selectOne')->andReturn($category);

        $this->assertFalse($this->service->delete(1));
    }

    public function test_delete_returns_false_when_not_found(): void
    {
        DB::shouldReceive('selectOne')->andReturn(null);

        $this->assertFalse($this->service->delete(999));
    }
}
