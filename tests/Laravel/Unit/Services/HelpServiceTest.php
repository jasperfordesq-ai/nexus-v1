<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\HelpService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class HelpServiceTest extends TestCase
{
    private HelpService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HelpService();
    }

    public function test_getFaqs_returns_grouped_by_category(): void
    {
        DB::shouldReceive('table')->with('help_faqs')->andReturnSelf();
        DB::shouldReceive('where')->with('tenant_id', 2)->andReturnSelf();
        DB::shouldReceive('where')->with('is_published', 1)->andReturnSelf();
        DB::shouldReceive('orderBy')->with('category')->andReturnSelf();
        DB::shouldReceive('orderBy')->with('sort_order')->andReturnSelf();
        DB::shouldReceive('orderBy')->with('id')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([
            (object) ['id' => 1, 'question' => 'What is TB?', 'answer' => 'Timebanking', 'category' => 'Getting Started'],
            (object) ['id' => 2, 'question' => 'How?', 'answer' => 'Easy', 'category' => 'Getting Started'],
        ]));

        $result = $this->service->getFaqs(2);

        $this->assertCount(1, $result);
        $this->assertSame('Getting Started', $result[0]['category']);
        $this->assertCount(2, $result[0]['faqs']);
        $this->assertSame(1, $result[0]['faqs'][0]['id']);
    }

    public function test_getFaqs_falls_back_to_global_when_tenant_empty(): void
    {
        // First call for tenant_id=2 returns empty
        DB::shouldReceive('table')->with('help_faqs')->andReturnSelf();
        DB::shouldReceive('where')->with('tenant_id', 2)->andReturnSelf();
        DB::shouldReceive('where')->with('is_published', 1)->andReturnSelf();
        DB::shouldReceive('orderBy')->andReturnSelf();
        DB::shouldReceive('get')->once()->andReturn(collect([]));

        // Fallback call for tenant_id=0
        DB::shouldReceive('table')->with('help_faqs')->andReturnSelf();
        DB::shouldReceive('where')->with('tenant_id', 0)->andReturnSelf();
        DB::shouldReceive('where')->with('is_published', 1)->andReturnSelf();
        DB::shouldReceive('orderBy')->andReturnSelf();
        DB::shouldReceive('get')->once()->andReturn(collect([
            (object) ['id' => 10, 'question' => 'Global FAQ', 'answer' => 'Answer', 'category' => 'General'],
        ]));

        $result = $this->service->getFaqs(2);

        $this->assertCount(1, $result);
        $this->assertSame('General', $result[0]['category']);
    }

    public function test_getFaqs_with_search_filter(): void
    {
        DB::shouldReceive('table')->with('help_faqs')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('orderBy')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([
            (object) ['id' => 1, 'question' => 'Credits?', 'answer' => 'Time credits', 'category' => null],
        ]));

        $result = $this->service->getFaqs(2, null, 'credits');

        $this->assertCount(1, $result);
        $this->assertSame('General', $result[0]['category']);
    }

    public function test_getFaqs_with_category_filter_does_not_fallback(): void
    {
        DB::shouldReceive('table')->with('help_faqs')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('orderBy')->andReturnSelf();
        DB::shouldReceive('get')->once()->andReturn(collect([]));

        $result = $this->service->getFaqs(2, 5);

        // No fallback when category filter is set
        $this->assertSame([], $result);
    }
}
