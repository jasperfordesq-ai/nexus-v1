<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\PageBuilder\Renderers;

use App\PageBuilder\Renderers\BlockRendererInterface;
use App\PageBuilder\Renderers\ImageBlockRenderer;
use Tests\Laravel\TestCase;

class ImageBlockRendererTest extends TestCase
{
    private ImageBlockRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new ImageBlockRenderer();
    }

    public function test_implements_block_renderer_interface(): void
    {
        $this->assertInstanceOf(BlockRendererInterface::class, $this->renderer);
    }

    public function test_validate_requires_image_url(): void
    {
        $this->assertTrue($this->renderer->validate(['imageUrl' => 'https://example.com/img.jpg']));
        $this->assertFalse($this->renderer->validate(['imageUrl' => '']));
        $this->assertFalse($this->renderer->validate([]));
    }

    public function test_render_outputs_img_tag(): void
    {
        $html = $this->renderer->render([
            'imageUrl' => 'https://example.com/img.jpg',
            'alt' => 'Test Image',
        ]);

        $this->assertStringContainsString('src="https://example.com/img.jpg"', $html);
        $this->assertStringContainsString('alt="Test Image"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
    }

    public function test_render_wraps_in_link_when_link_url_provided(): void
    {
        $html = $this->renderer->render([
            'imageUrl' => 'https://example.com/img.jpg',
            'linkUrl' => '/target',
        ]);

        $this->assertStringContainsString('<a href="/target">', $html);
    }

    public function test_render_shows_caption(): void
    {
        $html = $this->renderer->render([
            'imageUrl' => 'https://example.com/img.jpg',
            'caption' => 'A nice photo',
        ]);

        $this->assertStringContainsString('pb-image-caption', $html);
        $this->assertStringContainsString('A nice photo', $html);
    }

    public function test_render_escapes_html_in_attributes(): void
    {
        $html = $this->renderer->render([
            'imageUrl' => 'https://example.com/img.jpg',
            'alt' => '<script>xss</script>',
        ]);

        $this->assertStringNotContainsString('<script>', $html);
    }
}
