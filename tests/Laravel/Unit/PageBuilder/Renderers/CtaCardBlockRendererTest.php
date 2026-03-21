<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\PageBuilder\Renderers;

use App\PageBuilder\Renderers\BlockRendererInterface;
use App\PageBuilder\Renderers\CtaCardBlockRenderer;
use Tests\Laravel\TestCase;

class CtaCardBlockRendererTest extends TestCase
{
    private CtaCardBlockRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new CtaCardBlockRenderer();
    }

    public function test_implements_block_renderer_interface(): void
    {
        $this->assertInstanceOf(BlockRendererInterface::class, $this->renderer);
    }

    public function test_validate_requires_cards_array(): void
    {
        $this->assertTrue($this->renderer->validate(['cards' => [['title' => 'Card']]]));
        $this->assertFalse($this->renderer->validate(['cards' => []]));
        $this->assertFalse($this->renderer->validate([]));
    }

    public function test_render_returns_empty_message_when_no_cards(): void
    {
        $html = $this->renderer->render(['cards' => []]);
        $this->assertStringContainsString('No cards added yet', $html);
    }

    public function test_render_outputs_cards(): void
    {
        $html = $this->renderer->render([
            'cards' => [
                ['title' => 'Get Started', 'description' => 'Begin here', 'buttonText' => 'Go', 'buttonUrl' => '/start'],
            ],
        ]);

        $this->assertStringContainsString('pb-cta-card', $html);
        $this->assertStringContainsString('Get Started', $html);
        $this->assertStringContainsString('Begin here', $html);
        $this->assertStringContainsString('href="/start"', $html);
    }

    public function test_render_skips_cards_with_empty_title(): void
    {
        $html = $this->renderer->render([
            'cards' => [
                ['title' => '', 'description' => 'No title'],
                ['title' => 'Valid', 'description' => 'Has title'],
            ],
        ]);

        $this->assertStringNotContainsString('No title', $html);
        $this->assertStringContainsString('Valid', $html);
    }
}
