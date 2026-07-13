<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use PHPUnit\Framework\TestCase;

final class EventOfflineCheckinAccessibleStaticTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 4);
    }

    public function test_accessible_code_fallback_uses_verifier_and_canonical_attendance_only(): void
    {
        $source = (string) file_get_contents(
            $this->root . '/app/Http/Controllers/GovukAlpha/Concerns/EventOfflineCheckinParity.php',
        );

        self::assertStringContainsString('EventCheckinCredentialService::class)->verify', $source);
        self::assertStringContainsString('EventAttendanceService::class)->transition', $source);
        self::assertStringContainsString("->value('attendance_version')", $source);
        self::assertStringNotContainsString("->update([", $source);
        self::assertStringNotContainsString('Wallet', $source);
    }

    public function test_accessible_attendee_credential_self_service_is_confirmed_scoped_and_secret_safe(): void
    {
        $source = (string) file_get_contents(
            $this->root . '/app/Http/Controllers/GovukAlpha/Concerns/EventOfflineCheckinParity.php',
        );

        self::assertStringContainsString("->where('registration_state', 'confirmed')", $source);
        self::assertStringContainsString('EventCheckinCredentialService::class)->issue', $source);
        self::assertStringContainsString('EventCheckinCredentialService::class)->rotate', $source);
        self::assertStringContainsString('EventCheckinCredentialService::class)->revoke', $source);
        self::assertStringContainsString('$result->issued ? $result->secret : null', $source);
        self::assertStringContainsString("'Cache-Control', 'private, no-store'", $source);
        self::assertStringContainsString("'Pragma', 'no-cache'", $source);
        self::assertStringNotContainsString('session()->flash', $source);
        self::assertSame(1, substr_count($source, "'token' =>"));
        self::assertStringContainsString(
            "'token' => is_string(\$token) && str_starts_with(\$token, 'nqx2_') ? \$token : null",
            $source,
        );
        self::assertStringNotContainsString("'secret' =>", $source);
    }

    public function test_accessible_route_is_authenticated_and_throttled(): void
    {
        $route = (string) file_get_contents(
            $this->root . '/routes/govuk-alpha-parity/event-offline-checkin.php',
        );

        self::assertStringContainsString('RequireAccessibleAuthentication::class', $route);
        self::assertStringContainsString("name('events.check-in.credential')", $route);
        self::assertStringContainsString("name('events.check-in.credential.issue')", $route);
        self::assertStringContainsString("name('events.check-in.credential.rotate')", $route);
        self::assertStringContainsString("name('events.check-in.credential.revoke')", $route);
        self::assertSame(3, substr_count($route, "middleware('throttle:nexus-route-20-per-1m')"));
        self::assertStringContainsString("middleware('throttle:nexus-route-60-per-1m')", $route);
        self::assertStringContainsString("name('events.check-in.code')", $route);
    }

    public function test_accessible_view_keeps_both_code_and_name_fallbacks(): void
    {
        $view = (string) file_get_contents(
            $this->root . '/accessible-frontend/views/event-check-in.blade.php',
        );

        self::assertStringContainsString("name=\"credential\"", $view);
        self::assertStringContainsString("name=\"search\"", $view);
        self::assertStringContainsString("name=\"confirmation\"", $view);
        self::assertStringContainsString("name=\"idempotency_key\"", $view);
        self::assertStringContainsString("__('event_offline_checkin.", $view);
    }

    public function test_accessible_attendee_view_exposes_one_shot_issue_rotate_and_revoke_controls(): void
    {
        $view = (string) file_get_contents(
            $this->root . '/accessible-frontend/views/event-checkin-credential.blade.php',
        );
        $eventDetail = (string) file_get_contents(
            $this->root . '/accessible-frontend/views/event-detail.blade.php',
        );

        self::assertStringContainsString("route('govuk-alpha.events.check-in.credential.issue'", $view);
        self::assertStringContainsString("route('govuk-alpha.events.check-in.credential.rotate'", $view);
        self::assertStringContainsString("route('govuk-alpha.events.check-in.credential.revoke'", $view);
        self::assertStringContainsString('readonly spellcheck="false"', $view);
        self::assertStringContainsString('data-alpha-print-page', $view);
        self::assertStringNotContainsString('onclick=', $view);
        self::assertStringContainsString('name="confirmation"', $view);
        self::assertStringContainsString('name="idempotency_key"', $view);
        self::assertStringContainsString('name="expected_version"', $view);
        self::assertStringContainsString('name="reason"', $view);
        self::assertStringContainsString('maxlength="500"', $view);
        self::assertStringContainsString("__('event_offline_checkin.attendee.", $view);
        self::assertStringContainsString(
            "route('govuk-alpha.events.check-in.credential'",
            $eventDetail,
        );
        self::assertStringContainsString(
            "__('event_offline_checkin.attendee.manage_link')",
            $eventDetail,
        );
    }

    public function test_controller_and_authenticated_api_route_seams_cover_every_offline_client_endpoint(): void
    {
        $controller = (string) file_get_contents(
            $this->root . '/app/Http/Controllers/GovukAlpha/AlphaController.php',
        );
        self::assertStringContainsString('use Concerns\\EventOfflineCheckinParity;', $controller);

        $routes = (string) file_get_contents($this->root . '/routes/api.php');
        $authStart = strpos($routes, "Route::middleware('auth:sanctum')->group(function () {");
        $eventsStart = strpos($routes, "Route::middleware('feature:events')->group(function () {");
        $eventsEnd = strpos($routes, '// MIGRATED ROUTES', $eventsStart === false ? 0 : $eventsStart + 1);
        $authEnd = strpos($routes, "// End Route::middleware('auth:sanctum')");
        if ($authStart === false || $eventsStart === false || $eventsEnd === false || $authEnd === false) {
            self::fail('Authenticated Events route group boundaries must remain explicit.');
        }
        self::assertLessThan($eventsStart, $authStart);
        self::assertLessThan($eventsEnd, $eventsStart);
        self::assertLessThan($authEnd, $eventsEnd);

        $eventsBlock = substr($routes, $eventsStart, $eventsEnd - $eventsStart);
        $offlineStart = strpos($eventsBlock, "Route::get('/v2/events/{id}/offline-checkin'");
        $offlineEnd = strpos($eventsBlock, "Route::get('/v2/events/{eventId}/broadcasts'");
        if ($offlineStart === false || $offlineEnd === false) {
            self::fail('Offline check-in routes must remain a contiguous Events route block.');
        }
        $offlineBlock = substr($eventsBlock, $offlineStart, $offlineEnd - $offlineStart);
        $endpoints = [
            "Route::get('/v2/events/{id}/offline-checkin',",
            "Route::get('/v2/events/{id}/offline-checkin/credentials/me',",
            "Route::post('/v2/events/{id}/offline-checkin/credentials',",
            "Route::post('/v2/events/{id}/offline-checkin/credentials/{credentialId}/rotate',",
            "Route::post('/v2/events/{id}/offline-checkin/credentials/{credentialId}/revoke',",
            "Route::post('/v2/events/{id}/offline-checkin/devices',",
            "Route::post('/v2/events/{id}/offline-checkin/devices/{deviceId}/rotate',",
            "Route::post('/v2/events/{id}/offline-checkin/devices/{deviceId}/revoke',",
            "Route::post('/v2/events/{id}/offline-checkin/manifest',",
            "Route::post('/v2/events/{id}/offline-checkin/sync',",
            "Route::get('/v2/events/{id}/offline-checkin/batches/{batchId}',",
            "Route::get('/v2/events/{id}/offline-checkin/conflicts',",
            "Route::post('/v2/events/{id}/offline-checkin/conflicts/{itemId}',",
            "Route::post('/v2/events/{id}/offline-checkin/scan',",
        ];
        foreach ($endpoints as $endpoint) {
            self::assertStringContainsString($endpoint, $offlineBlock);
        }
        self::assertSame(14, substr_count($offlineBlock, 'EventOfflineCheckinController::class'));
        self::assertSame(14, substr_count($offlineBlock, "middleware('throttle:"));
        self::assertStringContainsString("middleware('throttle:nexus-route-300-per-1m')", $offlineBlock);
    }

    public function test_accessible_translations_have_exact_key_parity_and_real_locale_content(): void
    {
        $locales = ['ar', 'de', 'en', 'es', 'fr', 'ga', 'it', 'ja', 'nl', 'pl', 'pt'];
        /** @var array<string,mixed> $english */
        $english = require $this->root . '/lang/en/event_offline_checkin.php';
        $englishFlat = $this->flatten($english);
        self::assertGreaterThanOrEqual(48, count($englishFlat));

        foreach ($locales as $locale) {
            /** @var array<string,mixed> $translated */
            $translated = require $this->root . "/lang/{$locale}/event_offline_checkin.php";
            $translatedFlat = $this->flatten($translated);
            self::assertSame(array_keys($englishFlat), array_keys($translatedFlat), $locale);
            if ($locale !== 'en') {
                self::assertGreaterThanOrEqual(
                    35,
                    count(array_diff_assoc($translatedFlat, $englishFlat)),
                    "{$locale} must contain genuine translated text",
                );
            }
        }
    }

    /**
     * @param array<string,mixed> $values
     * @return array<string,string>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $flattened = [];
        foreach ($values as $key => $value) {
            $path = $prefix === '' ? $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $flattened += $this->flatten($value, $path);
            } else {
                $flattened[$path] = (string) $value;
            }
        }

        return $flattened;
    }
}
