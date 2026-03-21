<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\PageBuilder\Renderers;

use App\PageBuilder\Renderers\BlockRendererInterface;
use App\PageBuilder\Renderers\VideoBlockRenderer;
use Tests\Laravel\TestCase;

class VideoBlockRendererTest extends TestCase
{
    private VideoBlockRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new VideoBlockRenderer();
    }

    public function test_implements_block_renderer_interface(): void
    {
        $this->assertInstanceOf(BlockRendererInterface::class, $this->renderer);
    }

    public function test_validate_requires_video_url(): void
    {
        $this->assertTrue($this->renderer->validate(['videoUrl' => 'https://youtube.com/watch?v=abc']));
        $this->assertFalse($this->renderer->validate(['videoUrl' => '']));
        $this->assertFalse($this->renderer->validate([]));
    }

    public function test_render_youtube_url(): void
    {
        $html = $this->renderer->render(['videoUrl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);

        $this->assertStringContainsString('youtube.com/embed/dQw4w9WgXcQ', $html);
        $this->assertStringContainsString('<iframe', $html);
    }

    public function test_render_youtube_short_url(): void
    {
        $html = $this->renderer->render(['videoUrl' => 'https://youtu.be/dQw4w9WgXcQ']);

        $this->assertStringContainsString('youtube.com/embed/dQw4w9WgXcQ', $html);
    }

    public function test_render_vimeo_url(): void
    {
        $html = $this->renderer->render(['videoUrl' => 'https://vimeo.com/123456789']);

        $this->assertStringContainsString('player.vimeo.com/video/123456789', $html);
    }

    public function test_render_native_video(): void
    {
        $html = $this->renderer->render(['videoUrl' => 'https://example.com/video.mp4']);

        $this->assertStringContainsString('<video controls>', $html);
        $this->assertStringContainsString('https://example.com/video.mp4', $html);
    }

    public function test_render_applies_width_class(): void
    {
        $html = $this->renderer->render(['videoUrl' => 'https://example.com/v.mp4', 'width' => 'wide']);
        $this->assertStringContainsString('pb-video-width-wide', $html);
    }

    public function test_render_applies_aspect_ratio_class(): void
    {
        $html = $this->renderer->render(['videoUrl' => 'https://example.com/v.mp4', 'aspectRatio' => '4-3']);
        $this->assertStringContainsString('pb-video-aspect-4-3', $html);
    }
}
