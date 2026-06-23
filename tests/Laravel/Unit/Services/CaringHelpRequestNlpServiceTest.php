<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\CaringHelpRequestNlpService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Laravel\TestCase;

class CaringHelpRequestNlpServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /** Build a fake OpenAI tool-call response carrying the given arguments. */
    private function fakeOpenAi(array $args): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'tool_calls' => [[
                            'function' => ['arguments' => json_encode($args)],
                        ]],
                    ],
                ]],
            ], 200),
        ]);
    }

    public function test_constants_match_caring_workflow(): void
    {
        $this->assertContains('transport', CaringHelpRequestNlpService::CATEGORIES);
        $this->assertCount(6, CaringHelpRequestNlpService::CATEGORIES);
        $this->assertSame(['phone', 'message', 'either'], CaringHelpRequestNlpService::CONTACT_PREFERENCES);
    }

    public function test_empty_transcript_returns_all_null(): void
    {
        $result = CaringHelpRequestNlpService::extract('   ');
        $this->assertNull($result['category']);
        $this->assertNull($result['when']);
        $this->assertNull($result['contact_preference']);
        $this->assertSame('', $result['raw_text']);
    }

    public function test_missing_api_key_falls_back_without_request(): void
    {
        config(['services.openai.api_key' => null]);
        Http::fake();

        $result = CaringHelpRequestNlpService::extract('I need a lift to the clinic');

        $this->assertNull($result['category']);
        $this->assertNull($result['contact_preference']);
        $this->assertSame('I need a lift to the clinic', $result['raw_text']);
        Http::assertNothingSent();
    }

    public function test_extracts_valid_intent(): void
    {
        config(['services.openai.api_key' => 'sk-test']);
        $this->fakeOpenAi([
            'category' => 'transport',
            'when' => '2026-07-01T15:00:00Z',
            'contact_preference' => 'phone',
        ]);

        $result = CaringHelpRequestNlpService::extract('lift to the doctor tomorrow at 3pm, call me');

        $this->assertSame('transport', $result['category']);
        $this->assertSame('phone', $result['contact_preference']);
        $this->assertNotNull($result['when']);
        $this->assertStringContainsString('2026-07-01', $result['when']);
        $this->assertSame('lift to the doctor tomorrow at 3pm, call me', $result['raw_text']);
    }

    public function test_invalid_enum_values_are_sanitized_to_null(): void
    {
        config(['services.openai.api_key' => 'sk-test']);
        $this->fakeOpenAi([
            'category' => 'spaceship',          // not a valid category
            'when' => 'not-a-real-date',         // unparseable
            'contact_preference' => 'telepathy', // not a valid preference
        ]);

        $result = CaringHelpRequestNlpService::extract('something vague');

        $this->assertNull($result['category']);
        $this->assertNull($result['when']);
        $this->assertNull($result['contact_preference']);
        $this->assertSame('something vague', $result['raw_text']);
    }

    public function test_api_error_falls_back(): void
    {
        config(['services.openai.api_key' => 'sk-test']);
        Http::fake(['api.openai.com/*' => Http::response('upstream error', 500)]);

        $result = CaringHelpRequestNlpService::extract('I would like some company this week');

        $this->assertNull($result['category']);
        $this->assertSame('I would like some company this week', $result['raw_text']);
    }

    public function test_result_is_cached_so_repeat_calls_skip_the_api(): void
    {
        config(['services.openai.api_key' => 'sk-test']);
        $this->fakeOpenAi([
            'category' => 'shopping',
            'when' => null,
            'contact_preference' => 'message',
        ]);

        $first = CaringHelpRequestNlpService::extract('help me with the weekly shop');
        $second = CaringHelpRequestNlpService::extract('help me with the weekly shop');

        $this->assertSame('shopping', $first['category']);
        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }
}
