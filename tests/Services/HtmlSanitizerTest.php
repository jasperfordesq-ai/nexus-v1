<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Helpers\HtmlSanitizer;

/**
 * Tests for HtmlSanitizer — security-critical XSS prevention.
 *
 * Two sanitizer classes exist in the codebase:
 *   - Nexus\Helpers\HtmlSanitizer  (lightweight, for user-generated content)
 *   - Nexus\Core\HtmlSanitizer     (CMS / page builder, allows styles)
 *
 * This test file covers both, focusing heavily on XSS attack vectors.
 *
 * @covers \Nexus\Helpers\HtmlSanitizer
 * @covers \Nexus\Core\HtmlSanitizer
 */
class HtmlSanitizerTest extends TestCase
{
    // =================================================================
    //  PART 1 — Nexus\Helpers\HtmlSanitizer (lightweight)
    // =================================================================

    // ---------------------------------------------------------------
    // Class existence & API
    // ---------------------------------------------------------------

    public function testHelperSanitizerClassExists(): void
    {
        $this->assertTrue(class_exists(HtmlSanitizer::class));
    }

    public function testCoreSanitizerClassExists(): void
    {
        $this->assertTrue(class_exists(\Nexus\Core\HtmlSanitizer::class));
    }

    public function testSanitizeMethodIsPublicAndStatic(): void
    {
        $ref = new \ReflectionMethod(HtmlSanitizer::class, 'sanitize');
        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
    }

    public function testStripAllMethodExists(): void
    {
        $this->assertTrue(method_exists(HtmlSanitizer::class, 'stripAll'));
    }

    // ---------------------------------------------------------------
    // Empty / null input handling
    // ---------------------------------------------------------------

    public function testSanitizeReturnsEmptyStringForEmptyInput(): void
    {
        $this->assertEquals('', HtmlSanitizer::sanitize(''));
    }

    public function testCoreSanitizeReturnsEmptyStringForEmptyInput(): void
    {
        $this->assertEquals('', \Nexus\Core\HtmlSanitizer::sanitize(''));
    }

    // ---------------------------------------------------------------
    // XSS prevention — script tags
    // ---------------------------------------------------------------

    /**
     * @dataProvider scriptTagXssProvider
     */
    public function testSanitizeRemovesScriptTags(string $xssInput): void
    {
        $result = HtmlSanitizer::sanitize($xssInput);
        $this->assertStringNotContainsStringIgnoringCase('<script', $result);
        $this->assertStringNotContainsStringIgnoringCase('</script>', $result);
    }

    public static function scriptTagXssProvider(): array
    {
        return [
            'basic script' => ['<script>alert("XSS")</script>'],
            'script with src' => ['<script src="evil.js"></script>'],
            'uppercase script' => ['<SCRIPT>alert("XSS")</SCRIPT>'],
            'mixed case script' => ['<ScRiPt>alert("XSS")</sCrIpT>'],
            'script with spaces' => ['<script >alert("XSS")</script >'],
            'script in paragraph' => ['<p>Hello</p><script>alert("XSS")</script><p>World</p>'],
            'nested scripts' => ['<script><script>alert("XSS")</script></script>'],
        ];
    }

    // ---------------------------------------------------------------
    // XSS prevention — event handlers
    // ---------------------------------------------------------------

    /**
     * @dataProvider eventHandlerXssProvider
     */
    public function testSanitizeRemovesEventHandlers(string $xssInput, string $handlerName): void
    {
        $result = HtmlSanitizer::sanitize($xssInput);
        $this->assertStringNotContainsStringIgnoringCase($handlerName . '=', $result);
    }

    public static function eventHandlerXssProvider(): array
    {
        return [
            'onclick' => ['<div onclick="alert(1)">Click</div>', 'onclick'],
            'onerror' => ['<img src="x" onerror="alert(1)">', 'onerror'],
            'onload' => ['<img src="x" onload="alert(1)">', 'onload'],
            'onmouseover' => ['<div onmouseover="alert(1)">Hover</div>', 'onmouseover'],
            'onfocus' => ['<input onfocus="alert(1)">', 'onfocus'],
            'onblur' => ['<input onblur="alert(1)">', 'onblur'],
        ];
    }

    // ---------------------------------------------------------------
    // XSS prevention — javascript: URLs
    // ---------------------------------------------------------------

    /**
     * @dataProvider javascriptUrlXssProvider
     */
    public function testSanitizeRemovesJavascriptUrls(string $xssInput): void
    {
        $result = HtmlSanitizer::sanitize($xssInput);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $result);
    }

    public static function javascriptUrlXssProvider(): array
    {
        return [
            'link href' => ['<a href="javascript:alert(1)">Click</a>'],
            'uppercase' => ['<a href="JAVASCRIPT:alert(1)">Click</a>'],
            'mixed case' => ['<a href="JaVaScRiPt:alert(1)">Click</a>'],
            'with spaces' => ['<a href="java script:alert(1)">Click</a>'],
            'encoded spaces' => ['<a href="javascript&#58;alert(1)">Click</a>'],
        ];
    }

    // ---------------------------------------------------------------
    // Safe link handling
    // ---------------------------------------------------------------

    public function testSanitizeAllowsHttpLinks(): void
    {
        $html = '<a href="http://example.com">Link</a>';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('http://example.com', $result);
    }

    public function testSanitizeAllowsHttpsLinks(): void
    {
        $html = '<a href="https://example.com">Secure Link</a>';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('https://example.com', $result);
    }

    public function testSanitizeAllowsMailtoLinks(): void
    {
        $html = '<a href="mailto:test@example.com">Email</a>';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('mailto:', $result);
    }

    public function testSanitizeAllowsRelativeUrls(): void
    {
        $html = '<a href="/about">About</a>';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('/about', $result);
    }

    public function testSanitizeAllowsFragmentUrls(): void
    {
        $html = '<a href="#section">Jump</a>';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('#section', $result);
    }

    // ---------------------------------------------------------------
    // Dangerous protocol blocking
    // ---------------------------------------------------------------

    public function testSanitizeBlocksVbscriptProtocol(): void
    {
        $html = '<a href="vbscript:MsgBox(1)">Click</a>';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsStringIgnoringCase('vbscript:', $result);
    }

    public function testSanitizeBlocksDataProtocol(): void
    {
        $html = '<a href="data:text/html,<script>alert(1)</script>">Click</a>';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('data:text/html', $result);
    }

    public function testSanitizeBlocksFileProtocol(): void
    {
        $html = '<a href="file:///etc/passwd">Read</a>';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('file:///', $result);
    }

    // ---------------------------------------------------------------
    // Allowed HTML tags
    // ---------------------------------------------------------------

    /**
     * @dataProvider allowedTagProvider
     */
    public function testSanitizePreservesAllowedTags(string $tag, string $html): void
    {
        $result = HtmlSanitizer::sanitize($html);
        $this->assertStringContainsString('<' . $tag, $result);
    }

    public static function allowedTagProvider(): array
    {
        return [
            'paragraph' => ['p', '<p>Text</p>'],
            'bold' => ['strong', '<strong>Bold</strong>'],
            'emphasis' => ['em', '<em>Italic</em>'],
            'link' => ['a', '<a href="https://example.com">Link</a>'],
            'unordered list' => ['ul', '<ul><li>Item</li></ul>'],
            'ordered list' => ['ol', '<ol><li>Item</li></ol>'],
            'list item' => ['li', '<ul><li>Item</li></ul>'],
            'heading 1' => ['h1', '<h1>Title</h1>'],
            'heading 2' => ['h2', '<h2>Subtitle</h2>'],
            'heading 3' => ['h3', '<h3>Section</h3>'],
            'blockquote' => ['blockquote', '<blockquote>Quote</blockquote>'],
            'code' => ['code', '<code>var x = 1;</code>'],
            'preformatted' => ['pre', '<pre>preformatted</pre>'],
            'horizontal rule' => ['hr', '<hr>'],
            'table' => ['table', '<table><tr><td>Cell</td></tr></table>'],
            'div' => ['div', '<div>Block</div>'],
            'span' => ['span', '<span>Inline</span>'],
        ];
    }

    // ---------------------------------------------------------------
    // Stripped dangerous tags
    // ---------------------------------------------------------------

    public function testSanitizeRemovesIframeTags(): void
    {
        $html = '<iframe src="https://evil.com"></iframe>';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('<iframe', $result);
    }

    public function testSanitizeRemovesFormTags(): void
    {
        $html = '<form action="/steal"><input type="text"></form>';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('<form', $result);
    }

    public function testSanitizeRemovesObjectTags(): void
    {
        $html = '<object data="evil.swf"></object>';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('<object', $result);
    }

    public function testSanitizeRemovesEmbedTags(): void
    {
        $html = '<embed src="evil.swf">';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('<embed', $result);
    }

    // ---------------------------------------------------------------
    // Style attribute handling (Helpers sanitizer strips all styles)
    // ---------------------------------------------------------------

    public function testHelperSanitizeStripsStyleAttributes(): void
    {
        $html = '<div style="background:url(javascript:alert(1))">Content</div>';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('style=', $result);
    }

    // ---------------------------------------------------------------
    // CSS injection prevention (Core sanitizer)
    // ---------------------------------------------------------------

    public function testCoreSanitizerRemovesExpressionInStyles(): void
    {
        $html = '<div style="width: expression(alert(1))">Content</div>';
        $result = \Nexus\Core\HtmlSanitizer::sanitize($html, true);

        $this->assertStringNotContainsStringIgnoringCase('expression(', $result);
    }

    public function testCoreSanitizerRemovesMozBindingInStyles(): void
    {
        $html = '<div style="-moz-binding: url(evil.xml)">Content</div>';
        $result = \Nexus\Core\HtmlSanitizer::sanitize($html, true);

        $this->assertStringNotContainsStringIgnoringCase('-moz-binding', $result);
    }

    public function testCoreSanitizerRemovesBehaviorInStyles(): void
    {
        $html = '<div style="behavior: url(evil.htc)">Content</div>';
        $result = \Nexus\Core\HtmlSanitizer::sanitize($html, true);

        $this->assertStringNotContainsStringIgnoringCase('behavior:', $result);
    }

    // ---------------------------------------------------------------
    // Link security attributes
    // ---------------------------------------------------------------

    public function testHelperSanitizerAddsRelNoopenerNoreferrer(): void
    {
        $html = '<a href="https://example.com">Link</a>';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('noopener', $result);
        $this->assertStringContainsString('noreferrer', $result);
    }

    public function testCoreSanitizerAddsRelForBlankTargetLinks(): void
    {
        $html = '<a href="https://example.com" target="_blank">Link</a>';
        $result = \Nexus\Core\HtmlSanitizer::sanitize($html, true);

        $this->assertStringContainsString('noopener', $result);
        $this->assertStringContainsString('noreferrer', $result);
    }

    // ---------------------------------------------------------------
    // Image handling
    // ---------------------------------------------------------------

    public function testSanitizeAllowsImagesWithSafeAttributes(): void
    {
        $html = '<img src="https://example.com/photo.jpg" alt="Photo" width="200" height="150">';
        $result = HtmlSanitizer::sanitize($html, true);

        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('src=', $result);
        $this->assertStringContainsString('alt=', $result);
    }

    public function testSanitizeCanDisableImages(): void
    {
        $html = '<img src="https://example.com/photo.jpg" alt="Photo">';
        $result = HtmlSanitizer::sanitize($html, false);

        $this->assertStringNotContainsString('<img', $result);
    }

    // ---------------------------------------------------------------
    // Data URI handling
    // ---------------------------------------------------------------

    public function testCoreSanitizerAllowsDataImageUris(): void
    {
        $html = '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==" alt="Pixel">';
        $result = \Nexus\Core\HtmlSanitizer::sanitize($html, true);

        $this->assertStringContainsString('data:image/png;base64,', $result);
    }

    public function testCoreSanitizerBlocksDataNonImageUris(): void
    {
        // data:text/html should be blocked
        $html = '<img src="data:text/html,<script>alert(1)</script>" alt="Evil">';
        $result = \Nexus\Core\HtmlSanitizer::sanitize($html, true);

        $this->assertStringNotContainsString('data:text/html', $result);
    }

    // ---------------------------------------------------------------
    // Nested tag handling
    // ---------------------------------------------------------------

    public function testSanitizeHandlesNestedAllowedTags(): void
    {
        $html = '<div><p>Text with <strong>bold</strong> and <em>italic</em></p></div>';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('<p>', $result);
        $this->assertStringContainsString('<strong>', $result);
        $this->assertStringContainsString('<em>', $result);
    }

    public function testSanitizeHandlesNestedLists(): void
    {
        $html = '<ul><li>Item 1<ul><li>Sub-item</li></ul></li><li>Item 2</li></ul>';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('<ul>', $result);
        $this->assertStringContainsString('<li>', $result);
        $this->assertStringContainsString('Sub-item', $result);
    }

    // ---------------------------------------------------------------
    // HTML entity handling
    // ---------------------------------------------------------------

    public function testSanitizePreservesTextContent(): void
    {
        $html = '<p>Hello &amp; goodbye</p>';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('Hello', $result);
        $this->assertStringContainsString('goodbye', $result);
    }

    // ---------------------------------------------------------------
    // Malformed HTML handling
    // ---------------------------------------------------------------

    public function testSanitizeHandlesUnclosedTags(): void
    {
        $html = '<p>Unclosed paragraph<p>Another';
        $result = HtmlSanitizer::sanitize($html);

        // Should not throw, and should still contain the text
        $this->assertStringContainsString('Unclosed paragraph', $result);
        $this->assertStringContainsString('Another', $result);
    }

    public function testSanitizeHandlesExtraClosingTags(): void
    {
        $html = '<p>Text</p></p></div>';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('Text', $result);
    }

    public function testSanitizeHandlesMixedValidAndInvalidTags(): void
    {
        $html = '<p>Safe</p><script>alert(1)</script><p>Also safe</p>';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('Safe', $result);
        $this->assertStringContainsString('Also safe', $result);
        $this->assertStringNotContainsString('<script', $result);
    }

    // ---------------------------------------------------------------
    // Large input handling
    // ---------------------------------------------------------------

    public function testSanitizeHandlesLargeInput(): void
    {
        // 50KB of content — should not OOM or timeout
        $html = str_repeat('<p>' . str_repeat('x', 100) . '</p>', 500);
        $result = HtmlSanitizer::sanitize($html);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('<p>', $result);
    }

    // ---------------------------------------------------------------
    // stripAll method
    // ---------------------------------------------------------------

    public function testStripAllRemovesAllHtmlTags(): void
    {
        $html = '<p>Hello <strong>world</strong> <a href="#">link</a></p>';
        $result = HtmlSanitizer::stripAll($html);

        $this->assertStringNotContainsString('<', $result);
        $this->assertStringNotContainsString('>', $result);
    }

    public function testStripAllEncodesSpecialChars(): void
    {
        $html = '<p>5 > 3 & 2 < 4</p>';
        $result = HtmlSanitizer::stripAll($html);

        // htmlspecialchars encodes & and <
        $this->assertStringNotContainsString('<p>', $result);
    }

    // =================================================================
    //  PART 2 — Nexus\Core\HtmlSanitizer (CMS / page builder)
    // =================================================================

    // ---------------------------------------------------------------
    // Core sanitizer — stripTags method
    // ---------------------------------------------------------------

    public function testCoreStripTagsRemovesScriptContent(): void
    {
        $html = '<p>Safe</p><script>alert("XSS")</script><p>Also safe</p>';
        $result = \Nexus\Core\HtmlSanitizer::stripTags($html);

        $this->assertStringContainsString('Safe', $result);
        $this->assertStringContainsString('Also safe', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    public function testCoreStripTagsRemovesStyleContent(): void
    {
        $html = '<style>.evil { background: red; }</style><p>Content</p>';
        $result = \Nexus\Core\HtmlSanitizer::stripTags($html);

        $this->assertStringContainsString('Content', $result);
        $this->assertStringNotContainsString('.evil', $result);
    }

    public function testCoreStripTagsNormalizesWhitespace(): void
    {
        $html = "<p>Word1    \n\n   Word2</p>";
        $result = \Nexus\Core\HtmlSanitizer::stripTags($html);

        // Whitespace is normalized to single spaces
        $this->assertStringNotContainsString("\n", $result);
    }

    // ---------------------------------------------------------------
    // Core sanitizer — excerpt method
    // ---------------------------------------------------------------

    public function testCoreExcerptTruncatesLongContent(): void
    {
        $html = '<p>' . str_repeat('word ', 100) . '</p>';
        $result = \Nexus\Core\HtmlSanitizer::excerpt($html, 50);

        $this->assertLessThanOrEqual(53, strlen($result)); // 50 + '...'
        $this->assertStringEndsWith('...', $result);
    }

    public function testCoreExcerptReturnsShortContentUnchanged(): void
    {
        $html = '<p>Short text</p>';
        $result = \Nexus\Core\HtmlSanitizer::excerpt($html, 160);

        $this->assertEquals('Short text', $result);
        $this->assertStringNotContainsString('...', $result);
    }

    public function testCoreExcerptCutsAtWordBoundary(): void
    {
        // "Hello world this is a test" with limit 15
        // excerpt() only cuts at word boundary when last space is >= 80% of length.
        // Here last space is at position 11 (73%), so it keeps the hard cut at 15.
        $html = '<p>Hello world this is a test</p>';
        $result = \Nexus\Core\HtmlSanitizer::excerpt($html, 15);

        $this->assertStringEndsWith('...', $result);
        $this->assertEquals('Hello world thi...', $result);

        // With a longer limit (20), last space at 16 (80%) triggers word-boundary cut
        $result2 = \Nexus\Core\HtmlSanitizer::excerpt($html, 20);
        $this->assertStringEndsWith('...', $result2);
        $textPart = rtrim($result2, '.');
        $this->assertStringNotContainsString('is a', $textPart);
    }

    // ---------------------------------------------------------------
    // Core sanitizer — null byte removal
    // ---------------------------------------------------------------

    public function testCoreSanitizeRemovesNullBytes(): void
    {
        $html = "<p>Hello\0World</p>";
        $result = \Nexus\Core\HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString("\0", $result);
        $this->assertStringContainsString('Hello', $result);
        $this->assertStringContainsString('World', $result);
    }

    // ---------------------------------------------------------------
    // Core sanitizer — disallowed tags keep text content
    // ---------------------------------------------------------------

    public function testCoreSanitizeKeepsTextFromRemovedTags(): void
    {
        $html = '<marquee>Important text</marquee>';
        $result = \Nexus\Core\HtmlSanitizer::sanitize($html);

        // The marquee tag should be removed but text preserved
        $this->assertStringNotContainsString('<marquee', $result);
        $this->assertStringContainsString('Important text', $result);
    }

    // ---------------------------------------------------------------
    // Core sanitizer — allowStyles parameter
    // ---------------------------------------------------------------

    public function testCoreSanitizeWithStylesDisabled(): void
    {
        $html = '<div style="color: red;">Text</div>';
        $result = \Nexus\Core\HtmlSanitizer::sanitize($html, false);

        $this->assertStringNotContainsString('style=', $result);
    }

    public function testCoreSanitizeWithStylesEnabled(): void
    {
        $html = '<div style="color: red;">Text</div>';
        $result = \Nexus\Core\HtmlSanitizer::sanitize($html, true);

        // Styles are allowed when enabled
        $this->assertStringContainsString('color: red', $result);
    }

    // ---------------------------------------------------------------
    // Combined XSS vectors
    // ---------------------------------------------------------------

    /**
     * @dataProvider combinedXssVectorProvider
     */
    public function testCoreSanitizeBlocksCombinedXssVectors(string $xssInput, string $mustNotContain): void
    {
        $result = \Nexus\Core\HtmlSanitizer::sanitize($xssInput);
        $this->assertStringNotContainsStringIgnoringCase($mustNotContain, $result);
    }

    public static function combinedXssVectorProvider(): array
    {
        return [
            'img onerror' => [
                '<img src=x onerror=alert(1)>',
                'onerror',
            ],
            'svg onload' => [
                '<svg onload=alert(1)>',
                'onload',
            ],
            'body onload' => [
                '<body onload=alert(1)>',
                'onload',
            ],
            'input onfocus autofocus' => [
                '<input onfocus=alert(1) autofocus>',
                'onfocus',
            ],
            'details ontoggle' => [
                '<details ontoggle=alert(1) open>',
                'ontoggle',
            ],
            'marquee onstart' => [
                '<marquee onstart=alert(1)>',
                'onstart',
            ],
            'video onerror' => [
                '<video><source onerror=alert(1)>',
                'onerror',
            ],
            'style with expression' => [
                '<div style="background:expression(alert(1))">',
                'expression(',
            ],
            'javascript in href' => [
                '<a href="javascript:alert(1)">click</a>',
                'javascript:',
            ],
            'vbscript in href' => [
                '<a href="vbscript:alert(1)">click</a>',
                'vbscript:',
            ],
        ];
    }

    // ---------------------------------------------------------------
    // Core sanitizer — URL sanitization
    // ---------------------------------------------------------------

    public function testCoreSanitizeUrlAllowsRelativeUrls(): void
    {
        $ref = new \ReflectionMethod(\Nexus\Core\HtmlSanitizer::class, 'sanitizeUrl');
        $ref->setAccessible(true);

        $this->assertEquals('/path/to/page', $ref->invoke(null, '/path/to/page'));
        $this->assertEquals('#anchor', $ref->invoke(null, '#anchor'));
        $this->assertEquals('?q=test', $ref->invoke(null, '?q=test'));
    }

    public function testCoreSanitizeUrlAllowsHttpAndHttps(): void
    {
        $ref = new \ReflectionMethod(\Nexus\Core\HtmlSanitizer::class, 'sanitizeUrl');
        $ref->setAccessible(true);

        $this->assertEquals('https://example.com', $ref->invoke(null, 'https://example.com'));
        $this->assertEquals('http://example.com', $ref->invoke(null, 'http://example.com'));
    }

    public function testCoreSanitizeUrlAllowsMailtoAndTel(): void
    {
        $ref = new \ReflectionMethod(\Nexus\Core\HtmlSanitizer::class, 'sanitizeUrl');
        $ref->setAccessible(true);

        $this->assertEquals('mailto:test@example.com', $ref->invoke(null, 'mailto:test@example.com'));
        $this->assertEquals('tel:+1234567890', $ref->invoke(null, 'tel:+1234567890'));
    }

    public function testCoreSanitizeUrlBlocksJavascript(): void
    {
        $ref = new \ReflectionMethod(\Nexus\Core\HtmlSanitizer::class, 'sanitizeUrl');
        $ref->setAccessible(true);

        $this->assertFalse($ref->invoke(null, 'javascript:alert(1)'));
    }

    public function testCoreSanitizeUrlBlocksUnknownSchemes(): void
    {
        $ref = new \ReflectionMethod(\Nexus\Core\HtmlSanitizer::class, 'sanitizeUrl');
        $ref->setAccessible(true);

        $this->assertFalse($ref->invoke(null, 'ftp://example.com/file'));
    }

    // ---------------------------------------------------------------
    // Core sanitizer — style sanitization
    // ---------------------------------------------------------------

    public function testCoreSanitizeStyleRemovesJavascriptUrls(): void
    {
        $ref = new \ReflectionMethod(\Nexus\Core\HtmlSanitizer::class, 'sanitizeStyle');
        $ref->setAccessible(true);

        $result = $ref->invoke(null, 'background: url(javascript:alert(1))');
        $this->assertStringNotContainsStringIgnoringCase('javascript', $result);
    }

    public function testCoreSanitizeStyleRemovesDataNonImageUrls(): void
    {
        $ref = new \ReflectionMethod(\Nexus\Core\HtmlSanitizer::class, 'sanitizeStyle');
        $ref->setAccessible(true);

        $result = $ref->invoke(null, "background: url(data:text/html,<h1>evil</h1>)");
        $this->assertStringNotContainsString('data:text', $result);
    }

    public function testCoreSanitizeStyleAllowsSafeProperties(): void
    {
        $ref = new \ReflectionMethod(\Nexus\Core\HtmlSanitizer::class, 'sanitizeStyle');
        $ref->setAccessible(true);

        $result = $ref->invoke(null, 'color: red; font-size: 16px;');
        $this->assertStringContainsString('color: red', $result);
        $this->assertStringContainsString('font-size: 16px', $result);
    }

    // ---------------------------------------------------------------
    // Helper sanitizer — URL sanitization
    // ---------------------------------------------------------------

    public function testHelperSanitizeUrlBlocksDangerousProtocols(): void
    {
        $ref = new \ReflectionMethod(HtmlSanitizer::class, 'sanitizeUrl');
        $ref->setAccessible(true);

        $this->assertFalse($ref->invoke(null, 'javascript:alert(1)'));
        $this->assertFalse($ref->invoke(null, 'vbscript:exec'));
        $this->assertFalse($ref->invoke(null, 'data:text/html,evil'));
        $this->assertFalse($ref->invoke(null, 'file:///etc/passwd'));
    }

    public function testHelperSanitizeUrlBlocksObfuscatedJavascript(): void
    {
        $ref = new \ReflectionMethod(HtmlSanitizer::class, 'sanitizeUrl');
        $ref->setAccessible(true);

        // Whitespace in protocol — the sanitizer strips spaces before checking
        $this->assertFalse($ref->invoke(null, 'java script:alert(1)'));
    }

    public function testHelperSanitizeUrlAllowsSafeProtocols(): void
    {
        $ref = new \ReflectionMethod(HtmlSanitizer::class, 'sanitizeUrl');
        $ref->setAccessible(true);

        $this->assertNotFalse($ref->invoke(null, 'https://example.com'));
        $this->assertNotFalse($ref->invoke(null, 'http://example.com'));
        $this->assertNotFalse($ref->invoke(null, 'mailto:test@example.com'));
        $this->assertNotFalse($ref->invoke(null, '/relative/path'));
        $this->assertNotFalse($ref->invoke(null, '#anchor'));
    }

    // ---------------------------------------------------------------
    // Attribute filtering
    // ---------------------------------------------------------------

    public function testHelperSanitizeRemovesUnknownAttributes(): void
    {
        $html = '<p data-custom="value" tabindex="0">Text</p>';
        $result = HtmlSanitizer::sanitize($html);

        // The helper sanitizer only allows class for p tags
        // data-custom and tabindex should be removed
        $this->assertStringNotContainsString('data-custom', $result);
        $this->assertStringNotContainsString('tabindex', $result);
    }

    public function testHelperSanitizePreservesClassAttribute(): void
    {
        $html = '<p class="text-lg font-bold">Text</p>';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('class=', $result);
    }

    // ---------------------------------------------------------------
    // Allowed attributes per tag
    // ---------------------------------------------------------------

    public function testHelperAllowedAttributesForLinks(): void
    {
        $ref = new \ReflectionProperty(HtmlSanitizer::class, 'allowedAttributes');
        $ref->setAccessible(true);
        $attrs = $ref->getValue();

        $this->assertContains('href', $attrs['a']);
        $this->assertContains('title', $attrs['a']);
        $this->assertContains('target', $attrs['a']);
        $this->assertContains('rel', $attrs['a']);
    }

    public function testHelperAllowedAttributesForImages(): void
    {
        $ref = new \ReflectionProperty(HtmlSanitizer::class, 'allowedAttributes');
        $ref->setAccessible(true);
        $attrs = $ref->getValue();

        $this->assertContains('src', $attrs['img']);
        $this->assertContains('alt', $attrs['img']);
        $this->assertContains('width', $attrs['img']);
        $this->assertContains('height', $attrs['img']);
    }

    // ---------------------------------------------------------------
    // Table support
    // ---------------------------------------------------------------

    public function testSanitizePreservesTableStructure(): void
    {
        $html = '<table><thead><tr><th>Header</th></tr></thead><tbody><tr><td>Cell</td></tr></tbody></table>';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('<table', $result);
        $this->assertStringContainsString('<th', $result);
        $this->assertStringContainsString('<td', $result);
    }

    public function testSanitizePreservesColspanRowspan(): void
    {
        $html = '<table><tr><td colspan="2" rowspan="3">Merged</td></tr></table>';
        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('colspan', $result);
        $this->assertStringContainsString('rowspan', $result);
    }
}
