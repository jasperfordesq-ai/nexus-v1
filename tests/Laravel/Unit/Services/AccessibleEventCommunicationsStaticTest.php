<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use PHPUnit\Framework\TestCase;

final class AccessibleEventCommunicationsStaticTest extends TestCase
{
    public function test_isolated_routes_are_authenticated_throttled_and_not_registered_here(): void
    {
        $routes = $this->source('routes/govuk-alpha-parity/event-communications.php');

        self::assertStringContainsString('RequireAccessibleAuthentication::class', $routes);
        foreach ([
            'events.communications.index',
            'events.communications.preview',
            'events.communications.create',
            'events.communications.schedule',
            'events.communications.cancel',
            'events.communications.retry',
        ] as $name) {
            self::assertStringContainsString($name, $routes);
        }
        self::assertGreaterThanOrEqual(5, substr_count($routes, "middleware('throttle:"));
    }

    public function test_trait_uses_canonical_broadcast_services_and_exact_allowlists(): void
    {
        $trait = $this->source(
            'app/Http/Controllers/GovukAlpha/Concerns/EventCommunicationsParity.php',
        );

        foreach ([
            'EventBroadcastQueryService::class',
            'EventBroadcastService::class',
            "'registration_confirmed'",
            "'waitlist_active'",
            "'attendance_attended'",
            "'attendance_no_show'",
            'createDraft(',
            'schedule(',
            'cancel(',
            'retryFailed(',
            'preview_confirmed',
        ] as $contract) {
            self::assertStringContainsString($contract, $trait);
        }
        self::assertStringNotContainsString('event_rsvps', $trait);
        self::assertStringNotContainsString('withInput(', $trait);
        self::assertStringNotContainsString('$request->all()', $trait);
        self::assertStringContainsString("query('history_page')", $trait);
        self::assertStringContainsString('$historyPage,', $trait);
    }

    public function test_html_first_view_has_csrf_preview_audit_and_no_identity_fields(): void
    {
        $view = $this->source('accessible-frontend/views/event-communications.blade.php');

        self::assertGreaterThanOrEqual(5, substr_count($view, '@csrf'));
        self::assertStringContainsString('events.communications.preview', $view);
        self::assertStringContainsString('preview_confirmed', $view);
        self::assertStringContainsString('events.communications.schedule', $view);
        self::assertStringContainsString('events.communications.cancel', $view);
        self::assertStringContainsString("'history_actions.'", $view);
        self::assertStringContainsString("\$detail['history_meta']", $view);
        self::assertStringContainsString("'history_page' => \$targetPage", $view);
        self::assertStringContainsString('govuk-pagination__prev', $view);
        self::assertStringContainsString('govuk-pagination__next', $view);
        self::assertStringNotContainsString('recipient_user_id', $view);
        self::assertStringNotContainsString('email_address', $view);
        self::assertStringNotContainsString('claim_token', $view);
        self::assertStringNotContainsString("\$detail['broadcast']['body']", $view);
    }

    public function test_all_accessible_locales_have_exact_communications_key_parity(): void
    {
        $locales = ['ar', 'de', 'en', 'es', 'fr', 'ga', 'it', 'ja', 'nl', 'pl', 'pt'];
        $expected = null;
        foreach ($locales as $locale) {
            $path = $this->root() . "/lang/{$locale}/govuk_alpha.php";
            $translations = require $path;
            self::assertIsArray($translations);
            $communications = $translations['events']['communications'] ?? null;
            self::assertIsArray($communications, "Missing accessible communications in {$locale}");
            $keys = $this->flattenKeys($communications);
            $expected ??= $keys;
            self::assertSame($expected, $keys, "Accessible communications drift in {$locale}");
        }
        self::assertCount(71, $expected);
    }

    /** @return list<string> */
    private function flattenKeys(array $value, string $prefix = ''): array
    {
        $keys = [];
        foreach ($value as $key => $item) {
            $path = $prefix . $key;
            if (is_array($item)) {
                $keys = [...$keys, ...$this->flattenKeys($item, $path . '.')];
                continue;
            }
            $keys[] = $path;
        }
        sort($keys);

        return $keys;
    }

    private function source(string $relative): string
    {
        $source = file_get_contents($this->root() . '/' . $relative);
        self::assertIsString($source, "Could not read {$relative}");

        return $source;
    }

    private function root(): string
    {
        return dirname(__DIR__, 4);
    }
}
