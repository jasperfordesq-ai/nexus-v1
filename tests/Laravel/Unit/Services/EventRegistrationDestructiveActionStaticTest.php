<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use PHPUnit\Framework\TestCase;

final class EventRegistrationDestructiveActionStaticTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 4);
    }

    public function test_accessible_guest_and_retention_mutations_require_explicit_confirmation(): void
    {
        $view = $this->source('accessible-frontend/views/event-registration.blade.php');
        self::assertGreaterThanOrEqual(2, substr_count($view, 'name="confirm_destructive"'));
        self::assertStringContainsString('event_registration.guests.cancel_confirm_body', $view);
        self::assertStringContainsString('event_registration.retention.confirm_apply', $view);

        $controller = $this->source(
            'app/Http/Controllers/GovukAlpha/Concerns/EventRegistrationParity.php',
        );
        self::assertGreaterThanOrEqual(
            2,
            substr_count($controller, "boolean('confirm_destructive')"),
        );
    }

    public function test_accessible_confirmation_copy_exists_in_every_supported_locale(): void
    {
        $paths = glob($this->root . '/lang/*/event_registration.php');
        self::assertIsArray($paths);
        self::assertNotEmpty($paths);

        foreach ($paths as $path) {
            /** @var array<string,mixed> $translations */
            $translations = require $path;
            self::assertNotEmpty($translations['guests']['cancel_confirm_title'] ?? null, $path);
            self::assertNotEmpty($translations['guests']['cancel_confirm_body'] ?? null, $path);
            self::assertNotEmpty($translations['guests']['keep'] ?? null, $path);
            self::assertNotEmpty($translations['retention']['confirm_apply'] ?? null, $path);
        }
    }

    private function source(string $relativePath): string
    {
        $source = file_get_contents($this->root . '/' . $relativePath);
        self::assertNotFalse($source, $relativePath);

        return $source;
    }
}
