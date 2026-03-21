<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\JobVacancy;
use App\Services\JobVacancyService;
use Mockery;
use Tests\Laravel\TestCase;

class JobVacancyServiceTest extends TestCase
{
    private JobVacancyService $service;
    private $mockVacancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockVacancy = Mockery::mock(JobVacancy::class)->makePartial();
        $this->service = new JobVacancyService($this->mockVacancy);
    }

    public function test_getErrors_initially_empty(): void
    {
        $this->assertSame([], $this->service->getErrors());
    }

    public function test_getAll_returns_paginated_structure(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('with')->andReturnSelf();
        $query->shouldReceive('leftJoin')->andReturnSelf();
        $query->shouldReceive('select')->andReturnSelf();
        $query->shouldReceive('orderByRaw')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->andReturnSelf();
        $query->shouldReceive('limit')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAll();

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
    }

    public function test_getAll_with_filters(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('with')->andReturnSelf();
        $query->shouldReceive('leftJoin')->andReturnSelf();
        $query->shouldReceive('select')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('orderByRaw')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->andReturnSelf();
        $query->shouldReceive('limit')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAll(['status' => 'active', 'type' => 'full_time']);
        $this->assertIsArray($result);
    }
}
