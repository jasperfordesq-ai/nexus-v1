<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\EmailCssInliner;
use Tests\Laravel\TestCase;

/**
 * EmailCssInliner — emogrifier wrapper, fail-open by design.
 */
class EmailCssInlinerTest extends TestCase
{
    public function test_inlines_style_block_rules_onto_elements(): void
    {
        $html = '<html><head><style>p { color: #374151; }</style></head>'
            . '<body><p>Hello</p></body></html>';

        $out = EmailCssInliner::inline($html);

        $this->assertMatchesRegularExpression('/<p[^>]*style="[^"]*color:\s*#374151/', $out);
    }

    public function test_preserves_media_queries_in_style_block(): void
    {
        $html = '<html><head><style>'
            . '@media (max-width: 600px) { .stack { width: 100% !important; } }'
            . '</style></head><body><div class="stack">x</div></body></html>';

        $out = EmailCssInliner::inline($html);

        $this->assertStringContainsString('@media', $out);
        $this->assertStringContainsString('max-width: 600px', $out);
    }

    public function test_promotes_dimensions_to_html_attributes_for_outlook(): void
    {
        $html = '<html><head><style>table { width: 600px; }</style></head>'
            . '<body><table><tr><td>x</td></tr></table></body></html>';

        $out = EmailCssInliner::inline($html);

        $this->assertStringContainsString('width="600"', $out);
    }

    public function test_leaves_existing_inline_styles_intact(): void
    {
        $html = '<html><body><td style="padding:20px;background-color:#F6A821;">x</td></body></html>';

        $out = EmailCssInliner::inline($html);

        $this->assertStringContainsString('padding:20px', str_replace(' ', '', $out));
        $this->assertStringContainsString('background-color:#F6A821', str_replace(' ', '', $out));
    }

    public function test_fails_open_on_empty_input(): void
    {
        $this->assertSame('', EmailCssInliner::inline(''));
        $this->assertSame('   ', EmailCssInliner::inline('   '));
    }

    public function test_fails_open_on_oversized_input(): void
    {
        $huge = '<p>' . str_repeat('a', 1024 * 1024 + 1) . '</p>';

        $this->assertSame($huge, EmailCssInliner::inline($huge));
    }
}
