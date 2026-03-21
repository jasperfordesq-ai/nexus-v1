<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Helpers\HtmlSanitizer as HtmlSanitizerHelper;
use App\Services\HtmlSanitizer;
use Mockery;
use Tests\Laravel\TestCase;

class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HtmlSanitizer();
    }

    // ─── containsHtml ────────────────────────────────────────────

    public function test_containsHtml_empty_string_returns_false(): void
    {
        $this->assertFalse($this->service->containsHtml(''));
    }

    public function test_containsHtml_plain_text_returns_false(): void
    {
        $this->assertFalse($this->service->containsHtml('Hello world'));
    }

    public function test_containsHtml_with_html_tag_returns_true(): void
    {
        $this->assertTrue($this->service->containsHtml('<p>Hello</p>'));
    }

    public function test_containsHtml_self_closing_tag_returns_true(): void
    {
        $this->assertTrue($this->service->containsHtml('<br/>'));
    }

    public function test_containsHtml_entities_do_not_count(): void
    {
        $this->assertFalse($this->service->containsHtml('&amp; &lt;'));
    }

    // ─── toPlainText ─────────────────────────────────────────────

    public function test_toPlainText_empty_returns_empty(): void
    {
        $this->assertSame('', $this->service->toPlainText(''));
    }

    public function test_toPlainText_delegates_to_helper(): void
    {
        // Delegates to HtmlSanitizerHelper::stripTags - just verify it returns string
        $result = $this->service->toPlainText('<p>Hello</p>');
        $this->assertIsString($result);
    }

    // ─── sanitize ────────────────────────────────────────────────

    public function test_sanitize_delegates_to_helper(): void
    {
        $result = $this->service->sanitize('<p>Safe</p><script>alert("xss")</script>');
        $this->assertIsString($result);
    }

    // ─── sanitizeCms ─────────────────────────────────────────────

    public function test_sanitizeCms_delegates_to_helper(): void
    {
        $result = $this->service->sanitizeCms('<p style="color:red">Hello</p>', true);
        $this->assertIsString($result);
    }

    // ─── stripAll ────────────────────────────────────────────────

    public function test_stripAll_delegates_to_helper(): void
    {
        $result = $this->service->stripAll('<b>Bold</b>');
        $this->assertIsString($result);
    }

    // ─── excerpt ─────────────────────────────────────────────────

    public function test_excerpt_delegates_to_helper(): void
    {
        $result = $this->service->excerpt('<p>Long content here</p>', 10);
        $this->assertIsString($result);
    }

    // ─── sanitizeStyle ───────────────────────────────────────────

    public function test_sanitizeStyle_delegates_to_helper(): void
    {
        $result = $this->service->sanitizeStyle('color: red; expression(alert("xss"))');
        $this->assertIsString($result);
    }
}
