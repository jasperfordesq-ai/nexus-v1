<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\PageBuilder\Renderers;

use App\PageBuilder\Renderers\BlockRendererInterface;
use App\PageBuilder\Renderers\MembersGridRenderer;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class MembersGridRendererTest extends TestCase
{
    private MembersGridRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new MembersGridRenderer();
    }

    public function test_implements_block_renderer_interface(): void
    {
        $this->assertInstanceOf(BlockRendererInterface::class, $this->renderer);
    }

    public function test_member_grid_is_disabled_for_public_cms_rendering(): void
    {
        DB::shouldReceive('select')->never();

        $this->assertFalse($this->renderer->validate(['limit' => 6, 'columns' => 3]));
        $this->assertSame('', $this->renderer->render(['limit' => 6, 'columns' => 3]));
    }
}
