<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Accessible;

use PHPUnit\Framework\TestCase;

class AccessibleRenderHardeningTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 4);
    }

    public function test_progressive_enhancement_and_print_controls_are_csp_compatible(): void
    {
        $layout = (string) file_get_contents($this->root . '/accessible-frontend/views/layout.blade.php');
        $credential = (string) file_get_contents($this->root . '/accessible-frontend/views/event-checkin-credential.blade.php');
        $bundle = (string) file_get_contents($this->root . '/accessible-frontend/src/app.ts');

        self::assertStringContainsString('<script nonce="{{ $cspNonce', $layout);
        self::assertStringContainsString('data-alpha-print-page', $credential);
        self::assertStringNotContainsString('onclick=', $credential);
        self::assertStringContainsString("querySelectorAll<HTMLElement>('[data-alpha-print-page]')", $bundle);
        self::assertStringContainsString("addEventListener('click', () => window.print())", $bundle);
    }

    public function test_raw_cms_html_is_sanitized_again_at_render_time(): void
    {
        foreach (['blog-post.blade.php', 'kb-article.blade.php', 'legal-document.blade.php'] as $view) {
            $source = (string) file_get_contents($this->root . '/accessible-frontend/views/' . $view);
            self::assertStringContainsString('HtmlSanitizer::sanitizeCms', $source, $view);
        }
    }

    public function test_all_inline_accessible_scripts_carry_the_request_nonce(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $this->root . '/accessible-frontend/views',
            \FilesystemIterator::SKIP_DOTS,
        ));

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            preg_match_all(
                '/<script\b(?![^>]*\bsrc\s*=)(?![^>]*\bnonce\s*=)[^>]*>/is',
                $source,
                $matches,
            );
            self::assertSame([], $matches[0], $file->getFilename() . ' contains an inline script without a CSP nonce.');
        }
    }

    public function test_conversation_avatar_assignment_uses_a_blade_php_block(): void
    {
        $source = (string) file_get_contents($this->root . '/accessible-frontend/views/conversation.blade.php');

        self::assertStringContainsString('$senderAvatar = $message[\'sender\'][\'avatar_url\'] ?? null;', $source);
        self::assertStringNotContainsString('@php($senderAvatar', $source);
    }
}
