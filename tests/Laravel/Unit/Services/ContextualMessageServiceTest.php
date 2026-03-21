<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\ContextualMessageService;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Mockery;

class ContextualMessageServiceTest extends TestCase
{
    private ContextualMessageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContextualMessageService();
    }

    public function test_sendWithContext_creates_message_without_context(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_sendWithContext_returns_null_on_failure(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_sendWithContext_ignores_invalid_context_type(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_sendWithContext_ignores_context_type_without_id(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_getContextInfo_returns_null_for_invalid_type(): void
    {
        $result = $this->service->getContextInfo('invalid', 1);
        $this->assertNull($result);
    }

    public function test_getContextInfo_returns_listing_context(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) [
            'id' => 1, 'title' => 'Test Listing', 'type' => 'offer',
            'description' => 'Description', 'user_name' => 'John',
        ]);

        $result = $this->service->getContextInfo('listing', 1);

        $this->assertNotNull($result);
        $this->assertSame('listing', $result['type']);
        $this->assertSame('Test Listing', $result['title']);
    }

    public function test_getContextInfo_returns_null_when_entity_not_found(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $result = $this->service->getContextInfo('listing', 999);
        $this->assertNull($result);
    }

    public function test_getContextInfoBatch_returns_keyed_results(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) [
            'id' => 1, 'title' => 'Test Event', 'start_time' => null, 'location' => 'Dublin',
        ]);

        $result = $this->service->getContextInfoBatch([
            ['type' => 'event', 'id' => 1],
        ]);

        $this->assertArrayHasKey('event:1', $result);
    }

    public function test_enrichMessagesWithContext_adds_context_info(): void
    {
        $messages = [
            ['id' => 1, 'context_type' => null, 'context_id' => null],
            ['id' => 2, 'context_type' => null, 'context_id' => null],
        ];

        $result = $this->service->enrichMessagesWithContext($messages);

        $this->assertNull($result[0]['context_info']);
        $this->assertNull($result[1]['context_info']);
    }

    public function test_enrichMessagesWithContext_returns_unchanged_when_no_contexts(): void
    {
        $messages = [
            ['id' => 1],
        ];

        $result = $this->service->enrichMessagesWithContext($messages);
        $this->assertCount(1, $result);
    }
}
