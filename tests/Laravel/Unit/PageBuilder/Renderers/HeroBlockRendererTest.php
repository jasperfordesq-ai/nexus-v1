<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\PageBuilder\Renderers;

use App\PageBuilder\Renderers\BlockRendererInterface;
use App\PageBuilder\Renderers\HeroBlockRenderer;
use Tests\Laravel\TestCase;

class HeroBlockRendererTest extends TestCase
{
    private HeroBlockRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new HeroBlockRenderer();
    }

    public function test_implements_block_renderer_interface(): void
    {
        $this->assertInstanceOf(BlockRendererInterface::class, $this->renderer);
    }

    public function test_validate_requires_title(): void
    {
        $this->assertTrue($this->renderer->validate(['title' => 'Hello']));
        $this->assertFalse($this->renderer->validate(['title' => '']));
        $this->assertFalse($this->renderer->validate([]));
    }

    public function test_render_includes_title(): void
    {
        $html = $this->renderer->render(['title' => 'Welcome']);

        $this->assertStringContainsString('<h1 class="pb-hero-title">Welcome</h1>', $html);
    }

    public function test_render_includes_subtitle(): void
    {
        $html = $this->renderer->render(['title' => 'Title', 'subtitle' => 'Sub']);

        $this->assertStringContainsString('pb-hero-subtitle', $html);
        $this->assertStringContainsString('Sub', $html);
    }

    public function test_render_includes_button(): void
    {
        $html = $this->renderer->render([
            'title' => 'Title',
            'buttonText' => 'Click',
            'buttonUrl' => '/action',
        ]);

        $this->assertStringContainsString('pb-hero-button', $html);
        $this->assertStringContainsString('href="/action"', $html);
        $this->assertStringContainsString('Click', $html);
    }

    public function test_render_applies_height_class(): void
    {
        $html = $this->renderer->render(['title' => 'Title', 'height' => 'large']);
        $this->assertStringContainsString('hero-lg', $html);

        $html = $this->renderer->render(['title' => 'Title', 'height' => 'small']);
        $this->assertStringContainsString('hero-sm', $html);
    }

    public function test_render_applies_background_image(): void
    {
        $html = $this->renderer->render([
            'title' => 'Title',
            'backgroundImage' => 'https://example.com/bg.jpg',
        ]);

        $this->assertStringContainsString('background-image:', $html);
        $this->assertStringContainsString('https://example.com/bg.jpg', $html);
    }

    public function test_render_escapes_html_in_title(): void
    {
        $html = $this->renderer->render(['title' => '<script>alert("xss")</script>']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
