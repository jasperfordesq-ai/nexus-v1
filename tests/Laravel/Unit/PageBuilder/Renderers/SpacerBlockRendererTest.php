<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\PageBuilder\Renderers;

use App\PageBuilder\Renderers\BlockRendererInterface;
use App\PageBuilder\Renderers\SpacerBlockRenderer;
use Tests\Laravel\TestCase;

class SpacerBlockRendererTest extends TestCase
{
    private SpacerBlockRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new SpacerBlockRenderer();
    }

    public function test_implements_block_renderer_interface(): void
    {
        $this->assertInstanceOf(BlockRendererInterface::class, $this->renderer);
    }

    public function test_validate_always_returns_true(): void
    {
        $this->assertTrue($this->renderer->validate([]));
        $this->assertTrue($this->renderer->validate(['height' => 'large']));
    }

    public function test_render_outputs_div_with_height(): void
    {
        $html = $this->renderer->render(['height' => 'small']);

        $this->assertStringContainsString('pb-spacer', $html);
        $this->assertStringContainsString('height: 20px', $html);
    }

    public function test_render_defaults_to_medium(): void
    {
        $html = $this->renderer->render([]);
        $this->assertStringContainsString('height: 40px', $html);
    }

    public function test_render_large_height(): void
    {
        $html = $this->renderer->render(['height' => 'large']);
        $this->assertStringContainsString('height: 60px', $html);
    }

    public function test_render_xlarge_height(): void
    {
        $html = $this->renderer->render(['height' => 'xlarge']);
        $this->assertStringContainsString('height: 100px', $html);
    }
}
