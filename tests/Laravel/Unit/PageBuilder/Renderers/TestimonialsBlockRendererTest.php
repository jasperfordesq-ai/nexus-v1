<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\PageBuilder\Renderers;

use App\PageBuilder\Renderers\BlockRendererInterface;
use App\PageBuilder\Renderers\TestimonialsBlockRenderer;
use Tests\Laravel\TestCase;

class TestimonialsBlockRendererTest extends TestCase
{
    private TestimonialsBlockRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new TestimonialsBlockRenderer();
    }

    public function test_implements_block_renderer_interface(): void
    {
        $this->assertInstanceOf(BlockRendererInterface::class, $this->renderer);
    }

    public function test_validate_requires_testimonials_array(): void
    {
        $this->assertTrue($this->renderer->validate(['testimonials' => [['quote' => 'Great!']]]));
        $this->assertFalse($this->renderer->validate(['testimonials' => []]));
        $this->assertFalse($this->renderer->validate([]));
    }

    public function test_render_returns_empty_message_when_no_testimonials(): void
    {
        $html = $this->renderer->render(['testimonials' => []]);
        $this->assertStringContainsString('No testimonials added yet', $html);
    }

    public function test_render_outputs_testimonial_cards(): void
    {
        $html = $this->renderer->render([
            'testimonials' => [
                ['quote' => 'Amazing platform!', 'name' => 'Alice', 'position' => 'Manager', 'company' => 'Acme'],
            ],
        ]);

        $this->assertStringContainsString('pb-testimonial-card', $html);
        $this->assertStringContainsString('Amazing platform!', $html);
        $this->assertStringContainsString('Alice', $html);
        $this->assertStringContainsString('Manager', $html);
        $this->assertStringContainsString('Acme', $html);
    }

    public function test_render_shows_rating_stars(): void
    {
        $html = $this->renderer->render([
            'testimonials' => [
                ['quote' => 'Great', 'rating' => 4],
            ],
        ]);

        $this->assertStringContainsString('pb-testimonial-rating', $html);
        // 4 solid stars + 1 regular star
        $this->assertEquals(4, substr_count($html, 'fa-solid fa-star'));
        $this->assertEquals(1, substr_count($html, 'fa-regular fa-star'));
    }

    public function test_render_skips_testimonials_with_empty_quote(): void
    {
        $html = $this->renderer->render([
            'testimonials' => [
                ['quote' => '', 'name' => 'Ghost'],
                ['quote' => 'Valid quote', 'name' => 'Real'],
            ],
        ]);

        $this->assertStringNotContainsString('Ghost', $html);
        $this->assertStringContainsString('Valid quote', $html);
    }
}
