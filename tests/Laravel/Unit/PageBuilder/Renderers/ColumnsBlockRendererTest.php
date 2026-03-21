<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\PageBuilder\Renderers;

use App\PageBuilder\Renderers\BlockRendererInterface;
use App\PageBuilder\Renderers\ColumnsBlockRenderer;
use Tests\Laravel\TestCase;

class ColumnsBlockRendererTest extends TestCase
{
    private ColumnsBlockRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new ColumnsBlockRenderer();
    }

    public function test_implements_block_renderer_interface(): void
    {
        $this->assertInstanceOf(BlockRendererInterface::class, $this->renderer);
    }

    public function test_validate_requires_column_count_between_1_and_4(): void
    {
        $this->assertTrue($this->renderer->validate(['columnCount' => 2]));
        $this->assertTrue($this->renderer->validate(['columnCount' => 1]));
        $this->assertTrue($this->renderer->validate(['columnCount' => 4]));
        $this->assertFalse($this->renderer->validate(['columnCount' => 0]));
        $this->assertFalse($this->renderer->validate(['columnCount' => 5]));
    }

    public function test_render_creates_correct_number_of_columns(): void
    {
        $html = $this->renderer->render([
            'columnCount' => 3,
            'columns' => ['Col 1', 'Col 2', 'Col 3'],
        ]);

        $this->assertStringContainsString('columns-3', $html);
        $this->assertEquals(3, substr_count($html, 'pb-column'));
        $this->assertStringContainsString('Col 1', $html);
        $this->assertStringContainsString('Col 2', $html);
        $this->assertStringContainsString('Col 3', $html);
    }

    public function test_render_applies_gap_class(): void
    {
        $html = $this->renderer->render(['columnCount' => 2, 'gap' => 'large']);
        $this->assertStringContainsString('pb-columns-gap-lg', $html);
    }

    public function test_render_fills_empty_columns(): void
    {
        $html = $this->renderer->render([
            'columnCount' => 3,
            'columns' => ['Only one'],
        ]);

        // Should still produce 3 column divs
        $this->assertEquals(3, substr_count($html, 'pb-column'));
    }
}
