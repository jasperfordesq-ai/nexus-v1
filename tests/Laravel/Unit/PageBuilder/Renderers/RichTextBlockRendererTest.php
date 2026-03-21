<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\PageBuilder\Renderers;

use App\PageBuilder\Renderers\BlockRendererInterface;
use App\PageBuilder\Renderers\RichTextBlockRenderer;
use Tests\Laravel\TestCase;

class RichTextBlockRendererTest extends TestCase
{
    private RichTextBlockRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new RichTextBlockRenderer();
    }

    public function test_implements_block_renderer_interface(): void
    {
        $this->assertInstanceOf(BlockRendererInterface::class, $this->renderer);
    }

    public function test_validate_requires_non_empty_content(): void
    {
        $this->assertTrue($this->renderer->validate(['content' => '<p>Hello</p>']));
        $this->assertFalse($this->renderer->validate(['content' => '']));
        $this->assertFalse($this->renderer->validate(['content' => '   ']));
        $this->assertFalse($this->renderer->validate([]));
    }

    public function test_validate_strips_tags_to_check_content(): void
    {
        // Content with only HTML tags and no text should fail
        $this->assertFalse($this->renderer->validate(['content' => '<br><hr>']));
    }

    public function test_render_wraps_content_in_rich_text_div(): void
    {
        $html = $this->renderer->render(['content' => '<p>Hello</p>']);

        $this->assertStringContainsString('pb-rich-text', $html);
        $this->assertStringContainsString('pb-rich-text-content', $html);
        $this->assertStringContainsString('<p>Hello</p>', $html);
    }

    public function test_render_applies_width_class(): void
    {
        $html = $this->renderer->render(['content' => 'Test', 'width' => 'wide']);
        $this->assertStringContainsString('pb-width-wide', $html);

        $html = $this->renderer->render(['content' => 'Test', 'width' => 'narrow']);
        $this->assertStringContainsString('pb-width-narrow', $html);
    }

    public function test_render_applies_padding_class(): void
    {
        $html = $this->renderer->render(['content' => 'Test', 'padding' => 'large']);
        $this->assertStringContainsString('pb-padding-lg', $html);
    }
}
