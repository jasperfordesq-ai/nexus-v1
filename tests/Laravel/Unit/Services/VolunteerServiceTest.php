<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\VolunteerService;
use App\Models\VolOpportunity;
use Illuminate\Database\Eloquent\Builder;
use Mockery;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class VolunteerServiceTest extends TestCase
{
    public function test_getOpportunities_returns_expected_structure(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('with')->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('orderByDesc')->andReturnSelf();
        $builder->shouldReceive('limit')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([]));
        $builder->shouldReceive('count')->andReturn(0);

        Mockery::mock('alias:' . VolOpportunity::class)
            ->shouldReceive('query')->andReturn($builder);

        $result = VolunteerService::getOpportunities();

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertEmpty($result['items']);
    }

    public function test_getById_returns_null_when_not_found(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('with')->andReturnSelf();
        $builder->shouldReceive('find')->with(999)->andReturn(null);

        Mockery::mock('alias:' . VolOpportunity::class)
            ->shouldReceive('query')->andReturn($builder);

        $this->assertNull(VolunteerService::getById(999));
    }
}
