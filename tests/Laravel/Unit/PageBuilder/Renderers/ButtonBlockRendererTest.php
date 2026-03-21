<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\PageBuilder\Renderers;

use App\PageBuilder\Renderers\BlockRendererInterface;
use App\PageBuilder\Renderers\ButtonBlockRenderer;
use Tests\Laravel\TestCase;

class ButtonBlockRendererTest extends TestCase
{
    private ButtonBlockRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new ButtonBlockRenderer();
    }

    public function test_implements_block_renderer_interface(): void
    {
        $this->assertInstanceOf(BlockRendererInterface::class, $this->renderer);
    }

    public function test_validate_requires_text_and_url(): void
    {
        $this->assertTrue($this->renderer->validate(['text' => 'Click', 'url' => '/go']));
        $this->assertFalse($this->renderer->validate(['text' => 'Click', 'url' => '']));
        $this->assertFalse($this->renderer->validate(['text' => '', 'url' => '/go']));
        $this->assertFalse($this->renderer->validate([]));
    }

    public function test_render_outputs_link_with_text(): void
    {
        $html = $this->renderer->render(['text' => 'Sign Up', 'url' => '/signup']);

        $this->assertStringContainsString('href="/signup"', $html);
        $this->assertStringContainsString('Sign Up', $html);
        $this->assertStringContainsString('pb-button', $html);
    }

    public function test_render_applies_style_class(): void
    {
        $html = $this->renderer->render(['text' => 'Go', 'url' => '/', 'style' => 'secondary']);
        $this->assertStringContainsString('pb-btn-secondary', $html);
    }

    public function test_render_applies_size_class(): void
    {
        $html = $this->renderer->render(['text' => 'Go', 'url' => '/', 'size' => 'large']);
        $this->assertStringContainsString('pb-btn-lg', $html);
    }

    public function test_render_adds_target_blank_when_open_in_new_tab(): void
    {
        $html = $this->renderer->render(['text' => 'Go', 'url' => '/', 'openInNewTab' => true]);

        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    public function test_render_includes_icon(): void
    {
        $html = $this->renderer->render(['text' => 'Go', 'url' => '/', 'icon' => 'arrow-right']);
        $this->assertStringContainsString('fa-arrow-right', $html);
    }
}
