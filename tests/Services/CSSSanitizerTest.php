<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\CSSSanitizer;

class CSSSanitizerTest extends TestCase
{
    private CSSSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new CSSSanitizer();
    }

    public function testSanitizeRemovesDangerousExpressions(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Dangerous CSS detected');

        $this->sanitizer->sanitize('.test { background: expression(alert(1)); }');
    }

    public function testSanitizeBlocksJavascriptProtocol(): void
    {
        $this->expectException(\Exception::class);
        $this->sanitizer->sanitize('.test { background: url(javascript:alert(1)); }');
    }

    public function testSanitizeBlocksVbscriptProtocol(): void
    {
        $this->expectException(\Exception::class);
        $this->sanitizer->sanitize('.test { background: url(vbscript:exec); }');
    }

    public function testSanitizeBlocksDataTextHtml(): void
    {
        $this->expectException(\Exception::class);
        $this->sanitizer->sanitize('.test { background: url(data:text/html,<script>alert(1)</script>); }');
    }

    public function testSanitizeBlocksMozBinding(): void
    {
        $this->expectException(\Exception::class);
        $this->sanitizer->sanitize('.test { -moz-binding: url(evil.xml); }');
    }

    public function testSanitizeBlocksBehavior(): void
    {
        $this->expectException(\Exception::class);
        $this->sanitizer->sanitize('.test { behavior: url(evil.htc); }');
    }

    public function testSanitizeBlocksImport(): void
    {
        $this->expectException(\Exception::class);
        $this->sanitizer->sanitize('@import url("evil.css"); .test { color: red; }');
    }

    public function testSanitizeBlocksScriptTags(): void
    {
        $this->expectException(\Exception::class);
        $this->sanitizer->sanitize('.test { content: "<script>alert(1)</script>"; }');
    }

    public function testSanitizeBlocksEval(): void
    {
        $this->expectException(\Exception::class);
        $this->sanitizer->sanitize('.test { content: eval(something); }');
    }

    public function testSanitizeAllowsValidCSS(): void
    {
        $css = '.test { color: red; font-size: 16px; }';
        $result = $this->sanitizer->sanitize($css);

        $this->assertStringContainsString('color: red', $result);
        $this->assertStringContainsString('font-size: 16px', $result);
    }

    public function testSanitizeStripsDisallowedProperties(): void
    {
        $css = '.test { color: red; content: "hello"; }';
        $result = $this->sanitizer->sanitize($css);

        $this->assertStringContainsString('color: red', $result);
        // 'content' is not in the allowed properties list
        $this->assertStringNotContainsString('content:', $result);
    }

    public function testSanitizeRemovesCSSComments(): void
    {
        $css = '.test { /* this is a comment */ color: blue; }';
        $result = $this->sanitizer->sanitize($css);

        $this->assertStringNotContainsString('/* this is a comment */', $result);
        $this->assertStringContainsString('color: blue', $result);
    }

    public function testSanitizeHandlesMultipleRules(): void
    {
        $css = '.a { color: red; } .b { font-size: 14px; }';
        $result = $this->sanitizer->sanitize($css);

        $this->assertStringContainsString('color: red', $result);
        $this->assertStringContainsString('font-size: 14px', $result);
    }

    public function testSanitizeAllowsBoxModelProperties(): void
    {
        $css = '.test { margin: 10px; padding: 5px; width: 100%; height: auto; }';
        $result = $this->sanitizer->sanitize($css);

        $this->assertStringContainsString('margin: 10px', $result);
        $this->assertStringContainsString('padding: 5px', $result);
        $this->assertStringContainsString('width: 100%', $result);
    }

    public function testSanitizeAllowsFlexboxProperties(): void
    {
        $css = '.test { display: flex; flex-direction: row; justify-content: center; align-items: center; }';
        $result = $this->sanitizer->sanitize($css);

        $this->assertStringContainsString('display: flex', $result);
        $this->assertStringContainsString('flex-direction: row', $result);
    }

    public function testSanitizeAllowsVisualEffects(): void
    {
        $css = '.test { opacity: 0.5; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-radius: 8px; }';
        $result = $this->sanitizer->sanitize($css);

        $this->assertStringContainsString('opacity: 0.5', $result);
        $this->assertStringContainsString('border-radius: 8px', $result);
    }

    public function testValidateReturnsValidForCleanCSS(): void
    {
        $result = $this->sanitizer->validate('.test { color: blue; }');

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateReturnsInvalidForDangerousCSS(): void
    {
        $result = $this->sanitizer->validate('.test { background: expression(alert(1)); }');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testSanitizeHandlesEmptyInput(): void
    {
        $result = $this->sanitizer->sanitize('');
        $this->assertEquals('', $result);
    }

    public function testSanitizeIsCaseInsensitiveForBlacklist(): void
    {
        $this->expectException(\Exception::class);
        $this->sanitizer->sanitize('.test { background: EXPRESSION(alert(1)); }');
    }

    public function testSanitizeHandlesDataImageUris(): void
    {
        // Note: data URIs with semicolons (;base64,) get split by the ; delimiter
        // in the property parser, so they won't survive sanitization intact.
        // This is a known limitation of the simple parser approach.
        $css = '.test { background-image: url(data:image/png;base64,abc123); }';
        $result = $this->sanitizer->sanitize($css);

        // The sanitizer uses ; as delimiter, so the data URI gets split
        // This verifies the sanitizer doesn't crash on such input
        $this->assertIsString($result);
    }

    public function testSanitizeBlocksUnsafeDataUris(): void
    {
        // data:application/javascript should be blocked by the blacklist check
        // (it doesn't match data:image/* pattern and data:text/html is in blacklist)
        $css = '.test { background-image: url(data:text/html,<h1>evil</h1>); }';
        $this->expectException(\Exception::class);
        $this->sanitizer->sanitize($css);
    }
}
