<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\TeamDocumentService;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Mockery;

class TeamDocumentServiceTest extends TestCase
{
    private TeamDocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TeamDocumentService();
    }

    public function test_getDocuments_returns_paginated_results(): void
    {
        $tenantId = TenantContext::getId();
        $groupId = 10;

        DB::shouldReceive('table')->with('team_documents')->andReturnSelf();
        DB::shouldReceive('where')->with('tenant_id', $tenantId)->andReturnSelf();
        DB::shouldReceive('where')->with('group_id', $groupId)->andReturnSelf();
        DB::shouldReceive('orderByDesc')->with('id')->andReturnSelf();
        DB::shouldReceive('limit')->with(51)->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->getDocuments($groupId);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertFalse($result['has_more']);
    }

    public function test_getDocuments_with_cursor_applies_filter(): void
    {
        $tenantId = TenantContext::getId();
        $groupId = 10;
        $filters = ['cursor' => '100', 'limit' => 10];

        DB::shouldReceive('table')->with('team_documents')->andReturnSelf();
        DB::shouldReceive('where')->with('tenant_id', $tenantId)->andReturnSelf();
        DB::shouldReceive('where')->with('group_id', $groupId)->andReturnSelf();
        DB::shouldReceive('where')->with('id', '<', 100)->andReturnSelf();
        DB::shouldReceive('orderByDesc')->with('id')->andReturnSelf();
        DB::shouldReceive('limit')->with(11)->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->getDocuments($groupId, $filters);

        $this->assertEmpty($result['items']);
    }

    public function test_upload_returns_null_when_no_file_provided(): void
    {
        $result = $this->service->upload(1, 1, []);

        $this->assertNull($result);
        $this->assertNotEmpty($this->service->getErrors());
        $this->assertEquals('VALIDATION_ERROR', $this->service->getErrors()[0]['code']);
    }

    public function test_upload_returns_null_when_file_has_upload_error(): void
    {
        $fileData = [
            'tmp_name' => '/tmp/test.pdf',
            'error' => UPLOAD_ERR_INI_SIZE,
        ];

        $result = $this->service->upload(1, 1, $fileData);

        $this->assertNull($result);
        $this->assertEquals('VALIDATION_ERROR', $this->service->getErrors()[0]['code']);
    }

    public function test_delete_returns_false_when_document_not_found(): void
    {
        $tenantId = TenantContext::getId();

        DB::shouldReceive('table')->with('team_documents')->andReturnSelf();
        DB::shouldReceive('where')->with('id', 999)->andReturnSelf();
        DB::shouldReceive('where')->with('tenant_id', $tenantId)->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $result = $this->service->delete(999, 1);

        $this->assertFalse($result);
        $this->assertEquals('RESOURCE_NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function test_getErrors_returns_empty_array_initially(): void
    {
        $this->assertEmpty($this->service->getErrors());
    }
}
