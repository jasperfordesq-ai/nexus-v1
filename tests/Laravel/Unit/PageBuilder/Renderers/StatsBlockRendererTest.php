<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\PageBuilder\Renderers;

use App\PageBuilder\Renderers\BlockRendererInterface;
use App\PageBuilder\Renderers\StatsBlockRenderer;
use Tests\Laravel\TestCase;

class StatsBlockRendererTest extends TestCase
{
    private StatsBlockRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new StatsBlockRenderer();
    }

    public function test_implements_block_renderer_interface(): void
    {
        $this->assertInstanceOf(BlockRendererInterface::class, $this->renderer);
    }

    public function test_validate_requires_stats_array(): void
    {
        $this->assertTrue($this->renderer->validate(['stats' => [['label' => 'Members', 'number' => '100']]]));
        $this->assertFalse($this->renderer->validate(['stats' => []]));
        $this->assertFalse($this->renderer->validate([]));
    }

    public function test_render_returns_empty_message_when_no_stats(): void
    {
        $html = $this->renderer->render(['stats' => []]);
        $this->assertStringContainsString('No stats added yet', $html);
    }

    public function test_render_outputs_stat_items(): void
    {
        $html = $this->renderer->render([
            'stats' => [
                ['label' => 'Members', 'number' => '500', 'suffix' => '+'],
            ],
        ]);

        $this->assertStringContainsString('pb-stat-item', $html);
        $this->assertStringContainsString('Members', $html);
        $this->assertStringContainsString('data-target="500"', $html);
        $this->assertStringContainsString('+', $html);
    }

    public function test_render_skips_stats_with_empty_label(): void
    {
        $html = $this->renderer->render([
            'stats' => [
                ['label' => '', 'number' => '100'],
                ['label' => 'Active', 'number' => '50'],
            ],
        ]);

        $this->assertEquals(1, substr_count($html, 'pb-stat-item'));
        $this->assertStringContainsString('Active', $html);
    }

    public function test_render_includes_animation_script_by_default(): void
    {
        $html = $this->renderer->render([
            'stats' => [['label' => 'Test', 'number' => '10']],
        ]);

        $this->assertStringContainsString('<script>', $html);
        $this->assertStringContainsString('animateCounters', $html);
    }

    public function test_render_excludes_animation_script_when_disabled(): void
    {
        $html = $this->renderer->render([
            'stats' => [['label' => 'Test', 'number' => '10']],
            'animated' => false,
        ]);

        $this->assertStringNotContainsString('animateCounters', $html);
    }
}
