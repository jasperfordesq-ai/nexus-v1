<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\Message;
use App\Services\MessageService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class MessageServiceTest extends TestCase
{
    private $messageAlias;

    protected function setUp(): void
    {
        parent::setUp();
        $this->messageAlias = Mockery::mock('alias:' . Message::class);
    }

    public function test_getConversations_returns_paginated_structure(): void
    {
        $this->app->instance('tenant.id', 2);

        DB::shouldReceive('table')->with('messages')->andReturnSelf();
        DB::shouldReceive('selectRaw')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('groupByRaw')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturn(collect([]));

        $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
        $query->shouldReceive('with')->andReturnSelf();
        $query->shouldReceive('whereIn')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->messageAlias->shouldReceive('query')->andReturn($query);

        $result = MessageService::getConversations(1);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
    }
}
