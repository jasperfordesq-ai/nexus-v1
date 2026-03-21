<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\PageBuilder\Renderers;

use App\PageBuilder\Renderers\BlockRendererInterface;
use App\PageBuilder\Renderers\AccordionBlockRenderer;
use Tests\Laravel\TestCase;

class AccordionBlockRendererTest extends TestCase
{
    private AccordionBlockRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new AccordionBlockRenderer();
    }

    public function test_implements_block_renderer_interface(): void
    {
        $this->assertInstanceOf(BlockRendererInterface::class, $this->renderer);
    }

    public function test_validate_requires_items_array(): void
    {
        $this->assertTrue($this->renderer->validate(['items' => [['question' => 'Q', 'answer' => 'A']]]));
        $this->assertFalse($this->renderer->validate(['items' => []]));
        $this->assertFalse($this->renderer->validate([]));
        $this->assertFalse($this->renderer->validate(['items' => 'not-array']));
    }

    public function test_render_returns_comment_when_no_items(): void
    {
        $html = $this->renderer->render(['items' => []]);
        $this->assertStringContainsString('No accordion items', $html);
    }

    public function test_render_outputs_accordion_with_items(): void
    {
        $html = $this->renderer->render([
            'title' => 'FAQ',
            'items' => [
                ['question' => 'What is NEXUS?', 'answer' => 'A timebanking platform'],
            ],
        ]);

        $this->assertStringContainsString('pb-accordion', $html);
        $this->assertStringContainsString('FAQ', $html);
        $this->assertStringContainsString('What is NEXUS?', $html);
        $this->assertStringContainsString('A timebanking platform', $html);
    }

    public function test_render_skips_items_with_empty_question(): void
    {
        $html = $this->renderer->render([
            'items' => [
                ['question' => '', 'answer' => 'Orphan answer'],
                ['question' => 'Valid Q', 'answer' => 'Valid A'],
            ],
        ]);

        $this->assertStringNotContainsString('Orphan answer', $html);
        $this->assertStringContainsString('Valid Q', $html);
    }

    public function test_render_includes_accordion_javascript(): void
    {
        $html = $this->renderer->render([
            'items' => [['question' => 'Q', 'answer' => 'A']],
        ]);

        $this->assertStringContainsString('<script>', $html);
        $this->assertStringContainsString('accordion', $html);
    }
}
