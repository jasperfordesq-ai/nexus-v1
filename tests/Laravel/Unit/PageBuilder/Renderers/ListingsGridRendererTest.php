<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\PageBuilder\Renderers;

use App\PageBuilder\Renderers\BlockRendererInterface;
use App\PageBuilder\Renderers\ListingsGridRenderer;
use Tests\Laravel\TestCase;

class ListingsGridRendererTest extends TestCase
{
    private ListingsGridRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new ListingsGridRenderer();
    }

    public function test_implements_block_renderer_interface(): void
    {
        $this->assertInstanceOf(BlockRendererInterface::class, $this->renderer);
    }

    public function test_validate_requires_valid_limit_and_columns(): void
    {
        $this->assertTrue($this->renderer->validate(['limit' => 6, 'columns' => 3]));
        $this->assertFalse($this->renderer->validate(['limit' => 0, 'columns' => 3]));
        $this->assertFalse($this->renderer->validate(['limit' => 101, 'columns' => 3]));
        $this->assertFalse($this->renderer->validate(['limit' => 6, 'columns' => 5]));
    }

    public function test_validate_allows_valid_column_counts(): void
    {
        foreach ([1, 2, 3, 4, 6] as $cols) {
            $this->assertTrue(
                $this->renderer->validate(['limit' => 6, 'columns' => $cols]),
                "Columns value {$cols} should be valid"
            );
        }
    }
}
