<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\PageBuilder;

use App\PageBuilder\BlockRegistry;
use App\PageBuilder\PageRenderer;
use App\PageBuilder\Renderers\BlockRendererInterface;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Laravel\TestCase;

class PageRendererTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BlockRegistry::clear();
    }

    protected function tearDown(): void
    {
        BlockRegistry::clear();
        parent::tearDown();
    }

    public function test_render_page_returns_comment_when_no_blocks(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->andReturn([]);

        $result = PageRenderer::renderPage(1);

        $this->assertStringContainsString('No blocks found for this page', $result);
    }

    public function test_render_page_renders_blocks_in_order(): void
    {
        $block1 = (object) ['block_type' => 'hero', 'block_data' => json_encode(['title' => 'Hello'])];
        $block2 = (object) ['block_type' => 'text', 'block_data' => json_encode(['content' => 'World'])];

        DB::shouldReceive('select')
            ->once()
            ->andReturn([$block1, $block2]);

        $heroRenderer = Mockery::mock(BlockRendererInterface::class);
        $heroRenderer->shouldReceive('validate')->andReturn(true);
        $heroRenderer->shouldReceive('render')->with(['title' => 'Hello'])->andReturn('<h1>Hello</h1>');

        $textRenderer = Mockery::mock(BlockRendererInterface::class);
        $textRenderer->shouldReceive('validate')->andReturn(true);
        $textRenderer->shouldReceive('render')->with(['content' => 'World'])->andReturn('<p>World</p>');

        BlockRegistry::registerRenderer('hero', $heroRenderer);
        BlockRegistry::registerRenderer('text', $textRenderer);

        $result = PageRenderer::renderPage(1);

        $this->assertStringContainsString('<h1>Hello</h1>', $result);
        $this->assertStringContainsString('<p>World</p>', $result);
    }

    public function test_render_page_handles_invalid_block_data(): void
    {
        $block = (object) ['block_type' => 'hero', 'block_data' => 'not-json'];

        DB::shouldReceive('select')
            ->once()
            ->andReturn([$block]);

        $result = PageRenderer::renderPage(1);

        $this->assertStringContainsString("Invalid block data for type 'hero'", $result);
    }

    public function test_preview_block_delegates_to_block_registry(): void
    {
        $renderer = Mockery::mock(BlockRendererInterface::class);
        $renderer->shouldReceive('validate')->andReturn(true);
        $renderer->shouldReceive('render')->with(['title' => 'Preview'])->andReturn('<h1>Preview</h1>');

        BlockRegistry::registerRenderer('hero', $renderer);

        $result = PageRenderer::previewBlock('hero', ['title' => 'Preview']);

        $this->assertEquals('<h1>Preview</h1>', $result);
    }

    public function test_get_blocks_returns_structured_array(): void
    {
        $dbResults = [
            (object) [
                'block_type' => 'hero',
                'block_data' => json_encode(['title' => 'Test']),
                'sort_order' => 0,
            ],
            (object) [
                'block_type' => 'text',
                'block_data' => json_encode(['content' => 'Body']),
                'sort_order' => 1,
            ],
        ];

        DB::shouldReceive('select')
            ->once()
            ->andReturn($dbResults);

        $result = PageRenderer::getBlocks(1);

        $this->assertCount(2, $result);
        $this->assertEquals('hero', $result[0]['type']);
        $this->assertEquals(['title' => 'Test'], $result[0]['data']);
        $this->assertEquals('text', $result[1]['type']);
    }
}
