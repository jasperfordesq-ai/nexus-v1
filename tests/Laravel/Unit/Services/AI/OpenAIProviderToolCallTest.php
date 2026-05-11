<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\AI;

use App\Services\AI\Providers\OpenAIProvider;
use Tests\Laravel\TestCase;

/**
 * Tests the OpenAIProvider message-translation logic that converts our
 * provider-neutral message shape into OpenAI's wire format and parses
 * tool_calls out of responses. The HTTP layer itself is not exercised.
 */
class OpenAIProviderToolCallTest extends TestCase
{
    public function test_translate_messages_handles_tool_call_assistant_and_tool_role(): void
    {
        $provider = new class(['api_key' => 'x']) extends OpenAIProvider {
            public function translateForTest(array $messages): array
            {
                $reflection = new \ReflectionClass(OpenAIProvider::class);
                $method = $reflection->getMethod('translateMessages');
                $method->setAccessible(true);
                return $method->invoke($this, $messages);
            }
        };

        $input = [
            ['role' => 'system', 'content' => 'You are helpful.'],
            ['role' => 'user', 'content' => 'Find me a gardener.'],
            [
                'role' => 'assistant',
                'content' => '',
                'tool_calls' => [
                    ['id' => 'call_1', 'name' => 'search_listings', 'arguments' => ['query' => 'gardener']],
                ],
            ],
            ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '{"ok":true,"results":[]}'],
        ];

        $out = $provider->translateForTest($input);

        $this->assertCount(4, $out);
        $this->assertSame('system', $out[0]['role']);
        $this->assertSame('user', $out[1]['role']);
        $this->assertSame('assistant', $out[2]['role']);
        $this->assertSame('function', $out[2]['tool_calls'][0]['type']);
        $this->assertSame('search_listings', $out[2]['tool_calls'][0]['function']['name']);
        $args = json_decode($out[2]['tool_calls'][0]['function']['arguments'], true);
        $this->assertSame(['query' => 'gardener'], $args);
        $this->assertSame('tool', $out[3]['role']);
        $this->assertSame('call_1', $out[3]['tool_call_id']);
    }
}
