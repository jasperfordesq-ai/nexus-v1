<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\Tenant;
use App\Services\NewsletterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Laravel\TestCase;

/**
 * renderEmail() multi-format dispatcher + renderPlainTextPart().
 *
 * Pins the four content_format behaviors: richtext keeps the branded shell,
 * html is injected verbatim (never re-wrapped), plaintext is escaped, and
 * every format guarantees an unsubscribe mechanism + open pixel + a usable
 * text/plain part.
 */
class NewsletterRenderFormatsTest extends TestCase
{
    use DatabaseTransactions;

    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::factory()->create([
            'slug' => 'nl-render-' . uniqid('', true),
            'domain' => null,
        ]);
        $this->tenantId = (int) $tenant->id;
    }

    private function render(array $newsletter, ?string $unsub = 'unsub-token', ?array $recipient = null, ?string $tracking = 'track-token'): string
    {
        return TenantContext::runForTenant(
            $this->tenantId,
            fn () => NewsletterService::renderEmail($newsletter, 'Timebank Ireland', $unsub, $recipient, $tracking)
        );
    }

    private function renderText(array $newsletter, ?array $recipient = null): string
    {
        return TenantContext::runForTenant(
            $this->tenantId,
            fn () => NewsletterService::renderPlainTextPart($newsletter, 'Timebank Ireland', 'unsub-token', $recipient)
        );
    }

    // ================================================================
    // richtext (default, back-compat)
    // ================================================================

    public function test_richtext_uses_branded_shell(): void
    {
        $html = $this->render([
            'content' => '<p>Hello community</p>',
            'subject' => 'Hi',
            'content_format' => 'richtext',
        ]);

        $this->assertStringContainsString('Hello community', $html);
        // Branded shell renders the tenant name as a header.
        $this->assertStringContainsString('Timebank Ireland', $html);
        $this->assertStringContainsString('/newsletter/unsubscribe?token=unsub-token', $html);
    }

    public function test_missing_content_format_defaults_to_richtext(): void
    {
        $html = $this->render([
            'content' => '<p>Legacy row</p>',
            'subject' => 'Legacy',
        ]);

        $this->assertStringContainsString('Legacy row', $html);
        $this->assertStringContainsString('Timebank Ireland', $html);
    }

    // ================================================================
    // html / builder
    // ================================================================

    public function test_full_html_document_is_not_rewrapped(): void
    {
        $doc = '<!DOCTYPE html><html><head><title>T</title></head><body>'
            . '<table><tr><td>Designed {{first_name}}</td></tr></table>'
            . '<a href="{{unsubscribe_url}}">Unsubscribe</a></body></html>';

        $html = $this->render(
            ['content' => $doc, 'subject' => 'D', 'content_format' => 'html'],
            'unsub-token',
            ['first_name' => 'Jasper', 'last_name' => 'Ford', 'name' => 'Jasper Ford', 'email' => 'j@x.test']
        );

        // Author's document preserved; only ONE <html> (not wrapped in the shell).
        $this->assertSame(1, substr_count(strtolower($html), '<html'));
        $this->assertStringContainsString('Designed Jasper', $html);
        // Author-provided unsubscribe token replaced with a real link.
        $this->assertStringContainsString('/newsletter/unsubscribe?token=unsub-token', $html);
    }

    public function test_full_html_without_unsubscribe_gets_footer_injected(): void
    {
        $doc = '<!DOCTYPE html><html><body><p>No unsubscribe here</p></body></html>';

        $html = $this->render(['content' => $doc, 'subject' => 'D', 'content_format' => 'html']);

        // Compliance backstop appended an unsubscribe link before </body>.
        $this->assertStringContainsString('/newsletter/unsubscribe?token=unsub-token', $html);
        $this->assertStringContainsString('No unsubscribe here', $html);
    }

    public function test_html_fragment_is_wrapped_in_minimal_skeleton(): void
    {
        $fragment = '<table role="presentation"><tr><td>Fragment body</td></tr></table>';

        $html = $this->render(['content' => $fragment, 'subject' => 'F', 'content_format' => 'html']);

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('Fragment body', $html);
        $this->assertStringContainsString('/newsletter/unsubscribe?token=unsub-token', $html);
        // NOT the branded richtext shell header.
        $this->assertStringNotContainsString('linear-gradient(135deg, #6366f1', $html);
    }

    // ================================================================
    // builder image safety net (send-path)
    // ================================================================

    public function test_builder_relative_storage_image_is_absolutized_on_send(): void
    {
        config(['app.url' => 'https://api.test']);

        $doc = '<!DOCTYPE html><html><body>'
            . '<table><tr><td><img src="/storage/tenant_1/uploads/a.png" alt="hero"></td></tr></table>'
            . '<a href="{{unsubscribe_url}}">Unsubscribe</a></body></html>';

        $html = $this->render(['content' => $doc, 'subject' => 'B', 'content_format' => 'builder']);

        $this->assertStringContainsString('https://api.test/storage/tenant_1/uploads/a.png', $html);
        $this->assertStringNotContainsString('src="/storage', $html);
    }

    public function test_builder_blob_image_is_dropped_on_send(): void
    {
        $doc = '<!DOCTYPE html><html><body>'
            . '<p>Kept body</p><img src="blob:https://app.test/xyz-uuid">'
            . '<a href="{{unsubscribe_url}}">Unsubscribe</a></body></html>';

        $html = $this->render(['content' => $doc, 'subject' => 'B', 'content_format' => 'builder']);

        $this->assertStringNotContainsString('blob:', $html);
        $this->assertStringContainsString('Kept body', $html);
    }

    // ================================================================
    // plaintext
    // ================================================================

    public function test_plaintext_is_escaped_and_wrapped(): void
    {
        $html = $this->render([
            'content' => "Hello <script>alert(1)</script>\nSecond line",
            'subject' => 'P',
            'content_format' => 'plaintext',
        ]);

        // Angle brackets escaped — the script never becomes live markup.
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        // Newline became a <br>.
        $this->assertStringContainsString('<br', $html);
        $this->assertStringContainsString('/newsletter/unsubscribe?token=unsub-token', $html);
    }

    // ================================================================
    // renderPlainTextPart()
    // ================================================================

    public function test_plaintext_part_for_plaintext_format_keeps_raw_text(): void
    {
        $text = $this->renderText(
            ['content' => 'Hi {{first_name}}, welcome back.', 'content_format' => 'plaintext'],
            ['first_name' => 'Jasper', 'last_name' => '', 'name' => 'Jasper', 'email' => 'j@x.test']
        );

        $this->assertStringContainsString('Hi Jasper, welcome back.', $text);
        $this->assertStringNotContainsString('{{first_name}}', $text);
        $this->assertStringContainsString('/newsletter/unsubscribe?token=unsub-token', $text);
    }

    public function test_plaintext_part_for_html_is_stripped_to_text(): void
    {
        $text = $this->renderText([
            'content' => '<h1>Heading</h1><p>Body paragraph</p>',
            'subject' => 'H',
            'content_format' => 'html',
        ]);

        $this->assertStringContainsString('Heading', $text);
        $this->assertStringContainsString('Body paragraph', $text);
        $this->assertStringNotContainsString('<h1>', $text);
        $this->assertStringNotContainsString('<p>', $text);
    }
}
