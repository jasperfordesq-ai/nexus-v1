<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\SeoService;
use Illuminate\Support\Facades\DB;

class SeoServiceTest extends TestCase
{
    private SeoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SeoService();
    }

    // ── getMetadata ──

    public function test_getMetadata_returns_defaults_when_not_found(): void
    {
        DB::shouldReceive('table->where->where->first')->andReturnNull();

        $result = $this->service->getMetadata($this->testTenantId, '/about');
        $this->assertNull($result['title']);
        $this->assertNull($result['description']);
        $this->assertNull($result['og_image']);
        $this->assertNull($result['canonical']);
    }

    public function test_getMetadata_returns_data_when_found(): void
    {
        $meta = (object) ['title' => 'About Us', 'description' => 'Our story', 'og_image' => null, 'canonical' => null];
        DB::shouldReceive('table->where->where->first')->andReturn($meta);

        $result = $this->service->getMetadata($this->testTenantId, '/about');
        $this->assertEquals('About Us', $result['title']);
    }

    // ── updateMetadata ──

    public function test_updateMetadata_filters_allowed_fields(): void
    {
        DB::shouldReceive('table->updateOrInsert')->once()->andReturn(true);

        $result = $this->service->updateMetadata($this->testTenantId, '/about', [
            'title' => 'New Title',
            'malicious_field' => 'should be ignored',
        ]);
        $this->assertTrue($result);
    }

    // ── getRedirects ──

    public function test_getRedirects_returns_array(): void
    {
        DB::shouldReceive('table->where->orderBy->get->map->all')->andReturn([]);
        $result = $this->service->getRedirects($this->testTenantId);
        $this->assertIsArray($result);
    }

    // ── createRedirect ──

    public function test_createRedirect_defaults_invalid_status_to_301(): void
    {
        DB::shouldReceive('table->insertGetId')->once()->andReturn(1);

        $result = $this->service->createRedirect($this->testTenantId, '/old', '/new', 999);
        $this->assertEquals(1, $result);
    }

    public function test_createRedirect_accepts_301_and_302(): void
    {
        DB::shouldReceive('table->insertGetId')->twice()->andReturn(1, 2);

        $this->assertEquals(1, $this->service->createRedirect($this->testTenantId, '/a', '/b', 301));
        $this->assertEquals(2, $this->service->createRedirect($this->testTenantId, '/c', '/d', 302));
    }
}
