<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\HtmlSanitizer;

/**
 * Tests for App\Services\HtmlSanitizer — the injectable service wrapper.
 *
 * This tests the service class (instance methods) which delegates to
 * App\Helpers\HtmlSanitizer. The helper's own tests live in HtmlSanitizerTest.
 *
 * @covers \App\Services\HtmlSanitizer
 */
class HtmlSanitizerServiceTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizer();
    }

    // =========================================================================
    // sanitize()
    // =========================================================================

    public function testSanitizeReturnsEmptyStringForEmptyInput(): void
    {
        $this->assertSame('', $this->sanitizer->sanitize(''));
    }

    public function testSanitizePreservesAllowedHtml(): void
    {
        $html = '<p>Hello <strong>world</strong></p>';
        $result = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('<p>', $result);
        $this->assertStringContainsString('<strong>', $result);
        $this->assertStringContainsString('world', $result);
    }

    public function testSanitizeRemovesScriptTags(): void
    {
        $html = '<p>Safe</p><script>alert("XSS")</script>';
        $result = $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsStringIgnoringCase('<script', $result);
        $this->assertStringContainsString('Safe', $result);
    }

    public function testSanitizeRemovesEventHandlers(): void
    {
        $html = '<img src="photo.jpg" onerror="alert(1)">';
        $result = $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsString('onerror', $result);
    }

    public function testSanitizeRemovesJavascriptUrls(): void
    {
        $html = '<a href="javascript:alert(1)">Click</a>';
        $result = $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsStringIgnoringCase('javascript:', $result);
    }

    // =========================================================================
    // containsHtml()
    // =========================================================================

    public function testContainsHtmlReturnsFalseForEmptyString(): void
    {
        $this->assertFalse($this->sanitizer->containsHtml(''));
    }

    public function testContainsHtmlReturnsFalseForPlainText(): void
    {
        $this->assertFalse($this->sanitizer->containsHtml('Just plain text'));
    }

    public function testContainsHtmlReturnsFalseForEntitiesOnly(): void
    {
        $this->assertFalse($this->sanitizer->containsHtml('5 &gt; 3 &amp; 2 &lt; 4'));
    }

    public function testContainsHtmlReturnsTrueForParagraph(): void
    {
        $this->assertTrue($this->sanitizer->containsHtml('<p>Hello</p>'));
    }

    public function testContainsHtmlReturnsTrueForSelfClosingTag(): void
    {
        $this->assertTrue($this->sanitizer->containsHtml('Line 1<br/>Line 2'));
    }

    public function testContainsHtmlReturnsTrueForNestedTags(): void
    {
        $this->assertTrue($this->sanitizer->containsHtml('<div><span>Text</span></div>'));
    }

    public function testContainsHtmlReturnsTrueForScriptTag(): void
    {
        $this->assertTrue($this->sanitizer->containsHtml('<script>alert(1)</script>'));
    }

    public function testContainsHtmlReturnsFalseForAngleBracketsInMath(): void
    {
        // "5 > 3" should not be detected as HTML
        $this->assertFalse($this->sanitizer->containsHtml('5 > 3'));
    }

    // =========================================================================
    // toPlainText()
    // =========================================================================

    public function testToPlainTextReturnsEmptyStringForEmptyInput(): void
    {
        $this->assertSame('', $this->sanitizer->toPlainText(''));
    }

    public function testToPlainTextStripsAllHtmlTags(): void
    {
        $html = '<p>Hello <strong>world</strong></p>';
        $result = $this->sanitizer->toPlainText($html);

        $this->assertStringNotContainsString('<', $result);
        $this->assertStringNotContainsString('>', $result);
        $this->assertStringContainsString('Hello', $result);
        $this->assertStringContainsString('world', $result);
    }

    public function testToPlainTextRemovesScriptContent(): void
    {
        $html = '<p>Safe</p><script>alert("XSS")</script><p>Also safe</p>';
        $result = $this->sanitizer->toPlainText($html);

        $this->assertStringContainsString('Safe', $result);
        $this->assertStringContainsString('Also safe', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    public function testToPlainTextRemovesStyleContent(): void
    {
        $html = '<style>.evil { color: red; }</style><p>Content</p>';
        $result = $this->sanitizer->toPlainText($html);

        $this->assertStringContainsString('Content', $result);
        $this->assertStringNotContainsString('.evil', $result);
    }

    public function testToPlainTextNormalizesWhitespace(): void
    {
        $html = "<p>Word1   \n\n   Word2</p>";
        $result = $this->sanitizer->toPlainText($html);

        $this->assertStringNotContainsString("\n", $result);
    }

    // =========================================================================
    // sanitizeCms()
    // =========================================================================

    public function testSanitizeCmsReturnsEmptyForEmptyInput(): void
    {
        $this->assertSame('', $this->sanitizer->sanitizeCms(''));
    }

    public function testSanitizeCmsRemovesNullBytes(): void
    {
        $result = $this->sanitizer->sanitizeCms("<p>Hello\0World</p>");
        $this->assertStringNotContainsString("\0", $result);
    }

    public function testSanitizeCmsStripsStylesWhenDisabled(): void
    {
        $html = '<div style="color: red;">Text</div>';
        $result = $this->sanitizer->sanitizeCms($html, false);

        $this->assertStringNotContainsString('style=', $result);
    }

    public function testSanitizeCmsKeepsSafeStylesWhenEnabled(): void
    {
        $html = '<div style="color: red;">Text</div>';
        $result = $this->sanitizer->sanitizeCms($html, true);

        $this->assertStringContainsString('color: red', $result);
    }

    // =========================================================================
    // stripAll()
    // =========================================================================

    public function testStripAllRemovesAllTags(): void
    {
        $html = '<p>Hello <strong>world</strong></p>';
        $result = $this->sanitizer->stripAll($html);

        $this->assertStringNotContainsString('<', $result);
    }

    public function testStripAllEscapesSpecialChars(): void
    {
        $html = '<p>A & B</p>';
        $result = $this->sanitizer->stripAll($html);

        $this->assertStringContainsString('&amp;', $result);
    }

    // =========================================================================
    // excerpt()
    // =========================================================================

    public function testExcerptReturnsShortContentUnchanged(): void
    {
        $html = '<p>Short text</p>';
        $result = $this->sanitizer->excerpt($html, 160);

        $this->assertEquals('Short text', $result);
    }

    public function testExcerptTruncatesLongContent(): void
    {
        $html = '<p>' . str_repeat('word ', 100) . '</p>';
        $result = $this->sanitizer->excerpt($html, 50);

        $this->assertStringEndsWith('...', $result);
        $this->assertLessThanOrEqual(53, mb_strlen($result));
    }

    public function testExcerptStripsHtmlBeforeTruncating(): void
    {
        $html = '<p><strong>Bold</strong> content</p>';
        $result = $this->sanitizer->excerpt($html, 160);

        $this->assertStringNotContainsString('<', $result);
        $this->assertStringContainsString('Bold', $result);
    }

    // =========================================================================
    // sanitizeStyle()
    // =========================================================================

    public function testSanitizeStyleAllowsSafeProperties(): void
    {
        $result = $this->sanitizer->sanitizeStyle('color: red; font-size: 16px;');

        $this->assertStringContainsString('color: red', $result);
        $this->assertStringContainsString('font-size: 16px', $result);
    }

    public function testSanitizeStyleRemovesExpression(): void
    {
        $result = $this->sanitizer->sanitizeStyle('width: expression(alert(1))');

        $this->assertStringNotContainsStringIgnoringCase('expression(', $result);
    }

    public function testSanitizeStyleRemovesMozBinding(): void
    {
        $result = $this->sanitizer->sanitizeStyle('-moz-binding: url(evil.xml)');

        $this->assertStringNotContainsStringIgnoringCase('-moz-binding', $result);
    }

    public function testSanitizeStyleRemovesBehavior(): void
    {
        $result = $this->sanitizer->sanitizeStyle('behavior: url(evil.htc)');

        $this->assertStringNotContainsStringIgnoringCase('behavior:', $result);
    }

    public function testSanitizeStyleRemovesJavascriptUrl(): void
    {
        $result = $this->sanitizer->sanitizeStyle('background: url(javascript:alert(1))');

        $this->assertStringNotContainsStringIgnoringCase('javascript', $result);
    }

    public function testSanitizeStyleFiltersDangerousProperties(): void
    {
        $result = $this->sanitizer->sanitizeStyle('color: red; position: fixed; z-index: 9999;');

        $this->assertStringContainsString('color: red', $result);
        // position and z-index are not in the safe list
        $this->assertStringNotContainsString('position', $result);
        $this->assertStringNotContainsString('z-index', $result);
    }
}
