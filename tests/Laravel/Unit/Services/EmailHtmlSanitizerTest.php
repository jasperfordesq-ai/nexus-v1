<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\EmailHtmlSanitizer;
use Tests\Laravel\TestCase;

/**
 * EmailHtmlSanitizer — allow-structure / deny-execution.
 *
 * The email sanitizer must strip executable vectors while preserving the
 * email-specific markup (tables, inline styles, <style>, MSO conditional
 * comments) that the generic HtmlSanitizer would destroy.
 */
class EmailHtmlSanitizerTest extends TestCase
{
    // ================================================================
    // Deny-execution
    // ================================================================

    public function test_strips_script_tags_with_content(): void
    {
        $html = '<p>Hello</p><script>alert(1)</script><p>World</p>';

        $out = EmailHtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringNotContainsString('alert(1)', $out);
        $this->assertStringContainsString('<p>Hello</p>', $out);
        $this->assertStringContainsString('<p>World</p>', $out);
    }

    public function test_strips_iframes_objects_embeds_and_forms(): void
    {
        $html = '<iframe src="https://evil.test"></iframe>'
            . '<object data="x"></object><embed src="x">'
            . '<form action="https://evil.test"><input name="a"><button>Go</button></form>'
            . '<p>Kept</p>';

        $out = EmailHtmlSanitizer::sanitize($html);

        foreach (['<iframe', '<object', '<embed', '<form', '<input', '<button'] as $tag) {
            $this->assertStringNotContainsString($tag, $out);
        }
        $this->assertStringContainsString('<p>Kept</p>', $out);
    }

    public function test_strips_event_handler_attributes(): void
    {
        $html = '<img src="https://ok.test/a.png" onerror="alert(1)" alt="x">'
            . '<a href="https://ok.test" onclick=\'steal()\'>Link</a>'
            . '<td onmouseover=hack()>Cell</td>';

        $out = EmailHtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('onerror', $out);
        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringNotContainsString('onmouseover', $out);
        $this->assertStringContainsString('src="https://ok.test/a.png"', $out);
        $this->assertStringContainsString('href="https://ok.test"', $out);
    }

    public function test_neutralizes_javascript_uris(): void
    {
        $html = '<a href="javascript:alert(1)">A</a>'
            . '<a href="JaVaScRiPt:alert(1)">B</a>'
            . '<a href="java script:alert(1)">C</a>'
            . '<img src="vbscript:bad()">';

        $out = EmailHtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('javascript:', strtolower($out));
        $this->assertStringNotContainsString('vbscript:', strtolower($out));
    }

    public function test_blocks_data_text_html_but_allows_data_images(): void
    {
        $html = '<a href="data:text/html;base64,PHNjcmlwdD4=">bad</a>'
            . '<img src="data:image/png;base64,iVBORw0KGgo=">';

        $out = EmailHtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('data:text/html', $out);
        $this->assertStringContainsString('data:image/png;base64,iVBORw0KGgo=', $out);
    }

    public function test_strips_meta_refresh_and_base(): void
    {
        $html = '<meta http-equiv="refresh" content="0;url=https://evil.test">'
            . '<base href="https://evil.test/">'
            . '<meta charset="utf-8">';

        $out = EmailHtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('http-equiv="refresh"', $out);
        $this->assertStringNotContainsString('<base', $out);
        $this->assertStringContainsString('<meta charset="utf-8">', $out);
    }

    public function test_neutralizes_css_expression_and_javascript_urls_in_styles(): void
    {
        $html = '<div style="width:expression(alert(1));background:url(javascript:bad())">x</div>'
            . '<style>.a { background: url("javascript:worse()"); }</style>';

        $out = EmailHtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('expression(', $out);
        $this->assertStringNotContainsString('javascript:', strtolower($out));
    }

    // ================================================================
    // Allow-structure — the email markup the generic sanitizer destroys
    // ================================================================

    public function test_preserves_table_layout_and_attributes(): void
    {
        $html = '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff">'
            . '<tr><td align="center" valign="top" width="50%" style="padding:20px;">Cell</td></tr></table>';

        $this->assertSame($html, EmailHtmlSanitizer::sanitize($html));
    }

    public function test_preserves_style_blocks_and_inline_styles(): void
    {
        $html = '<style>@media (max-width:600px){ .stack{width:100%!important;} }</style>'
            . '<div style="background-color:#F6A821;border-radius:12px;">x</div>';

        $this->assertSame($html, EmailHtmlSanitizer::sanitize($html));
    }

    public function test_preserves_mso_conditional_comments(): void
    {
        $html = '<!--[if mso]><table><tr><td width="600"><![endif]-->'
            . '<div>content</div>'
            . '<!--[if mso]></td></tr></table><![endif]-->';

        $this->assertSame($html, EmailHtmlSanitizer::sanitize($html));
    }

    public function test_preserves_full_document_structure(): void
    {
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>T</title></head>'
            . '<body style="margin:0;"><p>Hi {{first_name}}</p>{{unsubscribe_link}}</body></html>';

        $this->assertSame($html, EmailHtmlSanitizer::sanitize($html));
    }

    // ================================================================
    // sanitizeForFormat
    // ================================================================

    public function test_plaintext_format_is_left_byte_exact(): void
    {
        $text = "Hello <world> & friends\nonclick=not-code javascript: mention";

        $this->assertSame($text, EmailHtmlSanitizer::sanitizeForFormat($text, 'plaintext'));
    }

    public function test_html_format_is_sanitized(): void
    {
        $html = '<p>ok</p><script>bad()</script>';

        $this->assertStringNotContainsString('<script', EmailHtmlSanitizer::sanitizeForFormat($html, 'html'));
    }

    // ================================================================
    // normalizeEmailImageSources — send-path image safety net
    // ================================================================

    public function test_absolutizes_root_relative_storage_image_src(): void
    {
        $html = '<mj-column><img src="/storage/tenant_1/uploads/a.png" alt="x"></mj-column>';

        $out = EmailHtmlSanitizer::normalizeEmailImageSources($html, 'https://api.example.com');

        $this->assertStringContainsString('src="https://api.example.com/storage/tenant_1/uploads/a.png"', $out);
        $this->assertStringNotContainsString('src="/storage', $out);
    }

    public function test_absolutizes_root_relative_uploads_image_src(): void
    {
        $html = '<img src="/uploads/general/x.jpg">';

        $out = EmailHtmlSanitizer::normalizeEmailImageSources($html, 'https://api.example.com/');

        $this->assertStringContainsString('src="https://api.example.com/uploads/general/x.jpg"', $out);
    }

    public function test_rewrites_protocol_relative_image_src_to_https(): void
    {
        $html = '<img src="//cdn.example.com/a.png" alt="">';

        $out = EmailHtmlSanitizer::normalizeEmailImageSources($html, 'https://api.example.com');

        $this->assertStringContainsString('src="https://cdn.example.com/a.png"', $out);
        $this->assertStringNotContainsString('src="//cdn', $out);
    }

    public function test_leaves_absolute_https_image_src_untouched(): void
    {
        $html = '<img src="https://api.example.com/storage/tenant_1/uploads/a.png" alt="ok">';

        $this->assertSame($html, EmailHtmlSanitizer::normalizeEmailImageSources($html, 'https://api.example.com'));
    }

    public function test_drops_blob_url_images(): void
    {
        $html = '<p>before</p><img src="blob:https://app.example.com/1234-uuid"><p>after</p>';

        $out = EmailHtmlSanitizer::normalizeEmailImageSources($html, 'https://api.example.com');

        $this->assertStringNotContainsString('<img', $out);
        $this->assertStringNotContainsString('blob:', $out);
        $this->assertStringContainsString('<p>before</p>', $out);
        $this->assertStringContainsString('<p>after</p>', $out);
    }

    public function test_drops_non_image_data_uri_images_but_keeps_data_images(): void
    {
        $html = '<img src="data:text/html;base64,PHNjcmlwdD4=">'
            . '<img src="data:image/png;base64,iVBORw0KGgo=" alt="ok">';

        $out = EmailHtmlSanitizer::normalizeEmailImageSources($html, 'https://api.example.com');

        $this->assertStringNotContainsString('data:text/html', $out);
        $this->assertStringContainsString('data:image/png;base64,iVBORw0KGgo=', $out);
    }

    public function test_empty_app_url_leaves_relative_src_but_still_drops_blobs(): void
    {
        $html = '<img src="/storage/a.png"><img src="blob:x">';

        $out = EmailHtmlSanitizer::normalizeEmailImageSources($html, '');

        // No base to absolutize against — relative src is left as-is (no crash)…
        $this->assertStringContainsString('src="/storage/a.png"', $out);
        // …but blob images are still dropped.
        $this->assertStringNotContainsString('blob:', $out);
    }
}
