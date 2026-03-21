<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\AiChatService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiChatServiceTest extends TestCase
{
    private AiChatService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AiChatService();
    }

    public function test_chat_returns_error_when_api_key_missing(): void
    {
        config(['services.openai.key' => null]);

        $result = $this->service->chat(1, 'Hello');

        $this->assertTrue($result['error']);
        $this->assertSame('AI chat is not configured.', $result['reply']);
    }

    public function test_chat_calls_openai_and_saves_message(): void
    {
        config(['services.openai.key' => 'test-key']);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Hello there!']]],
            ]),
        ]);

        DB::shouldReceive('table')->with('ai_chat_messages')->andReturnSelf();
        DB::shouldReceive('insert')->once();

        $result = $this->service->chat(1, 'Hi');

        $this->assertFalse($result['error']);
        $this->assertSame('Hello there!', $result['reply']);
    }

    public function test_chat_returns_error_on_http_failure(): void
    {
        config(['services.openai.key' => 'test-key']);

        Http::fake([
            'api.openai.com/*' => Http::response([], 500),
        ]);

        Log::shouldReceive('error')->once();

        $result = $this->service->chat(1, 'Hi');

        $this->assertTrue($result['error']);
    }

    public function test_streamChat_returns_config_array(): void
    {
        $result = $this->service->streamChat(1, 'Hello', ['model' => 'gpt-4']);

        $this->assertSame('gpt-4', $result['model']);
        $this->assertTrue($result['stream']);
        $this->assertSame(1024, $result['max_tokens']);
    }

    public function test_streamChat_clamps_max_tokens_to_4096(): void
    {
        $result = $this->service->streamChat(1, 'Hello', ['max_tokens' => 10000]);

        $this->assertSame(4096, $result['max_tokens']);
    }

    public function test_getHistory_returns_array(): void
    {
        DB::shouldReceive('table')->with('ai_chat_messages')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->getHistory(1);
        $this->assertIsArray($result);
    }

    public function test_getHistory_clamps_limit_to_200(): void
    {
        DB::shouldReceive('table')->with('ai_chat_messages')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('limit')->with(200)->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $this->service->getHistory(1, 500);
        $this->assertTrue(true);
    }
}
