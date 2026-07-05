<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * Email-safe HTML sanitizer — allow-structure / deny-execution.
 *
 * Purpose-built for admin-authored newsletter HTML (pasted designs, builder
 * exports). Email HTML legitimately relies on constructs the generic
 * App\Helpers\HtmlSanitizer strips — <style> blocks, inline style attributes,
 * table layout attributes (bgcolor/width/cellpadding...), MSO conditional
 * comments, VML — so that sanitizer MUST NOT be used here. This class inverts
 * the approach: preserve all presentation, remove only executable vectors.
 *
 * Strips:
 *  - <script>, <iframe>, <frame>/<frameset>, <object>, <embed>, <applet>,
 *    <form>/<input>/<select>/<textarea>/<button>, <base>, <link rel=import>,
 *    <meta http-equiv="refresh">
 *  - on* event-handler attributes
 *  - javascript:/vbscript:/data:text/html URIs in href/src/action/background/
 *    poster/formaction and inside style url(...)
 *  - CSS expression(...)
 *  - data: URIs that are not data:image/(png|jpe?g|gif|webp)
 *
 * Preserves:
 *  - table layout attrs, inline style, <style> blocks, HTML comments including
 *    MSO conditionals (<!--[if mso]>...<![endif]-->), VML (v:*), <img>, <a>.
 *
 * Regex-based rather than DOMDocument on purpose: DOMDocument "repairs" email
 * markup (moves elements, drops conditional comments, mangles VML), which
 * corrupts exactly what email clients need. Targeted pattern removal keeps the
 * author's markup byte-stable everywhere except the removed vectors.
 */
class EmailHtmlSanitizer
{
    /** Tags removed together with their content. */
    private const STRIP_WITH_CONTENT = ['script', 'iframe', 'frameset', 'frame', 'object', 'embed', 'applet', 'form', 'select', 'textarea', 'button'];

    /** Void/simple tags removed but content kept (none currently nest content we keep). */
    private const STRIP_TAG_ONLY = ['base', 'input'];

    /** URL-bearing attributes checked for dangerous schemes. */
    private const URL_ATTRIBUTES = ['href', 'src', 'action', 'background', 'poster', 'formaction', 'xlink:href'];

    /**
     * Sanitize admin-authored email HTML.
     */
    public static function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        // 1. Remove executable containers with their content.
        foreach (self::STRIP_WITH_CONTENT as $tag) {
            $html = preg_replace('#<' . $tag . '\b[^>]*>.*?</' . $tag . '\s*>#is', '', $html) ?? $html;
            // Unclosed/self-closing variants
            $html = preg_replace('#<' . $tag . '\b[^>]*/?>#i', '', $html) ?? $html;
        }

        foreach (self::STRIP_TAG_ONLY as $tag) {
            $html = preg_replace('#</?' . $tag . '\b[^>]*/?>#i', '', $html) ?? $html;
        }

        // 2. <meta http-equiv="refresh"> and <link rel="import">
        $html = preg_replace('#<meta\b[^>]*http-equiv\s*=\s*(["\']?)refresh\1[^>]*>#i', '', $html) ?? $html;
        $html = preg_replace('#<link\b[^>]*rel\s*=\s*(["\']?)import\1[^>]*>#i', '', $html) ?? $html;

        // 3. on* event handlers (onclick, onload, onerror, ...) — quoted or bare.
        $html = preg_replace('#\s+on[a-z]+\s*=\s*"[^"]*"#i', '', $html) ?? $html;
        $html = preg_replace("#\s+on[a-z]+\s*=\s*'[^']*'#i", '', $html) ?? $html;
        $html = preg_replace('#\s+on[a-z]+\s*=\s*[^\s>"\'][^\s>]*#i', '', $html) ?? $html;

        // 4. Dangerous URI schemes in URL-bearing attributes.
        $attrPattern = implode('|', array_map('preg_quote', self::URL_ATTRIBUTES));
        $html = preg_replace_callback(
            '#(\s(?:' . $attrPattern . ')\s*=\s*)(["\']?)([^"\'>\s][^"\'>]*)\2#i',
            static function (array $m): string {
                $value = html_entity_decode($m[3], ENT_QUOTES, 'UTF-8');
                // Collapse whitespace/control chars used to obfuscate schemes (ja va script:)
                $probe = strtolower((string) preg_replace('/[\s\x00-\x1f]+/', '', $value));

                if (str_starts_with($probe, 'javascript:') || str_starts_with($probe, 'vbscript:')) {
                    return $m[1] . $m[2] . '#' . $m[2];
                }
                if (str_starts_with($probe, 'data:') && !preg_match('#^data:image/(png|jpe?g|gif|webp)[;,]#', $probe)) {
                    return $m[1] . $m[2] . '#' . $m[2];
                }

                return $m[0];
            },
            $html
        ) ?? $html;

        // 5. CSS attack vectors — expression() and javascript: inside url(),
        //    in both style attributes and <style> blocks.
        $html = preg_replace('#expression\s*\(#i', 'blocked(', $html) ?? $html;
        $html = preg_replace_callback(
            // Quoted url() contents may legitimately contain parentheses
            // (url("javascript:worse()")) — match up to the closing quote,
            // falling back to paren-terminated for the unquoted form.
            '#url\s*\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^)"\']*))\s*\)#i',
            static function (array $m): string {
                $raw = $m[1] !== '' ? $m[1] : ($m[2] !== '' ? $m[2] : ($m[3] ?? ''));
                $probe = strtolower((string) preg_replace('/[\s\x00-\x1f]+/', '', html_entity_decode($raw, ENT_QUOTES, 'UTF-8')));
                if (
                    str_starts_with($probe, 'javascript:')
                    || str_starts_with($probe, 'vbscript:')
                    || (str_starts_with($probe, 'data:') && !preg_match('#^data:image/(png|jpe?g|gif|webp)[;,]#', $probe))
                ) {
                    return 'url()';
                }

                return $m[0];
            },
            $html
        ) ?? $html;

        return $html;
    }

    /**
     * Sanitize content according to its authoring format.
     *
     * plaintext is escaped at render time, never interpreted as HTML — leave
     * it byte-exact. Every HTML-bearing format goes through sanitize().
     */
    public static function sanitizeForFormat(string $content, string $contentFormat): string
    {
        if ($contentFormat === 'plaintext') {
            return $content;
        }

        return self::sanitize($content);
    }

    /**
     * Normalize <img> sources for delivery so a sent email never carries an
     * image an inbox client can't fetch. This is the SEND-path safety net for
     * the newsletter builder: whatever ended up in the stored content, the
     * delivered HTML gets only absolute, reachable image URLs.
     *
     *  1. Root-relative "/storage/..." and "/uploads/..." → absolute {APP_URL}/…
     *     (the browser preview resolves these against the app origin, but an
     *     email client has no base URL, so they must be absolutized).
     *  2. Protocol-relative "//host/…" → "https://host/…".
     *  3. <img> whose src is a blob: URL or a non-image data: URI is DROPPED —
     *     blob URLs are meaningless outside the authoring page and a stray
     *     base64 blob bloats/breaks the email. (Legitimate data:image/* is kept.)
     *
     * Regex-based to match the byte-stable philosophy of sanitize(); it only
     * touches <img src> values, leaving all other email markup untouched.
     *
     * @param string $appUrl Absolute app origin (e.g. https://api.example.com);
     *                        the trailing slash is trimmed.
     */
    public static function normalizeEmailImageSources(string $html, string $appUrl): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $base = rtrim($appUrl, '/');

        // 1. Root-relative /storage/... and /uploads/... → absolute.
        if ($base !== '') {
            $html = preg_replace_callback(
                '#(<img\b[^>]*?\bsrc\s*=\s*)(["\'])(/(?:storage|uploads)/[^"\']*)\2#i',
                static fn (array $m): string => $m[1] . $m[2] . $base . $m[3] . $m[2],
                $html
            ) ?? $html;
        }

        // 2. Protocol-relative //host/... → https://host/...
        $html = preg_replace(
            '#(<img\b[^>]*?\bsrc\s*=\s*["\'])//#i',
            '$1https://',
            $html
        ) ?? $html;

        // 3. Drop <img> with a blob: or non-image data: src entirely.
        $html = preg_replace_callback(
            '#<img\b[^>]*?\bsrc\s*=\s*(["\'])([^"\']*)\1[^>]*>#i',
            static function (array $m): string {
                $src = strtolower(trim(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8')));
                if (str_starts_with($src, 'blob:')) {
                    return '';
                }
                if (str_starts_with($src, 'data:') && !preg_match('#^data:image/(png|jpe?g|gif|webp)[;,]#', $src)) {
                    return '';
                }

                return $m[0];
            },
            $html
        ) ?? $html;

        return $html;
    }
}
