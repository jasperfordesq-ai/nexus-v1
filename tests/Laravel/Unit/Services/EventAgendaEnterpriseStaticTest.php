<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use PHPUnit\Framework\TestCase;

final class EventAgendaEnterpriseStaticTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 4);
    }

    public function test_schema_and_service_preserve_enterprise_boundaries(): void
    {
        $migration = $this->source(
            'database/migrations/2026_07_11_000065_expand_event_agenda_enterprise.php',
        );
        foreach ([
            "unsignedInteger('capacity')->nullable()",
            "Schema::create('event_session_resources'",
            "Schema::create('event_session_registrations'",
            "Schema::create('event_session_registration_history'",
            "EVENT_REGISTRATION_SCOPE_INDEX = 'uq_event_registrations_checkin_scope'",
            'fk_ev_session_reg_event_reg',
            'fk_ev_session_reg_hist_registration',
            'event_session_registration_history_immutable',
            'event_session_confirmed_registration_required',
            'event_agenda_enterprise_rollback_refused_dependents_exist',
        ] as $needle) {
            self::assertStringContainsString($needle, $migration, $needle);
        }
        $foundation = $this->source(
            'database/migrations/2026_07_11_000053_create_event_agenda_sessions.php',
        );
        self::assertStringContainsString('event_agenda_rollback_refused_dependents_exist', $foundation);
        self::assertStringContainsString("'event_session_registrations'", $foundation);

        $service = $this->source('app/Services/EventSessionService.php');
        foreach ([
            'public function registerSession(',
            'public function withdrawSession(',
            'confirmedEventRegistration(',
            'activeSessionRegistrationCount(',
            'lockForUpdate()',
            'event_agenda_session_capacity_full',
            'event_agenda_registration_version_conflict',
            'event_agenda_registration_idempotency_conflict',
            'Crypt::encryptString',
            'event_agenda_resource_media_visibility_invalid',
            '$this->policy->manageAgenda($viewer, $event)',
            '$this->policy->viewRegisteredAgenda($viewer, $event)',
            '$this->policy->viewStaffAgenda($viewer, $event)',
        ] as $needle) {
            self::assertStringContainsString($needle, $service, $needle);
        }
        self::assertStringNotContainsString("DB::table('event_registrations')->update", $service);
        self::assertStringNotContainsString("DB::table('event_ticket_entitlements')->", $service);
        self::assertStringNotContainsString("DB::table('event_attendance_activity')->", $service);
        self::assertStringNotContainsString("DB::table('transactions')->", $service);
    }

    public function test_contract_and_clients_expose_only_viewer_scoped_registration_and_resources(): void
    {
        $mapper = $this->source('app/Support/Events/EventSessionContractMapper.php');
        foreach ([
            "'capacity' => [",
            "'registration' => [",
            "'resources' => \$projectedResources",
            'Crypt::decryptString',
            "in_array(\$type, ['stream', 'recording']",
            "'registered' => \$canViewRegistered",
            "'staff' => \$canViewStaff",
        ] as $needle) {
            self::assertStringContainsString($needle, $mapper, $needle);
        }

        $controller = $this->source('app/Http/Controllers/Api/EventAgendaController.php');
        self::assertStringContainsString('public function register(int $id, int $sessionId)', $controller);
        self::assertStringContainsString('public function withdraw(int $id, int $sessionId)', $controller);
        self::assertStringContainsString('registrationMutation(', $controller);
        self::assertStringContainsString("'Cache-Control', 'private, no-store'", $controller);

        $reactApi = $this->source('react-frontend/src/lib/events-api.ts');
        self::assertStringContainsString('registerAgendaSession(', $reactApi);
        self::assertStringContainsString('withdrawAgendaSession(', $reactApi);
        self::assertStringContainsString('eventAgendaResourceSchema', $reactApi);
        $react = $this->source(
            'react-frontend/src/pages/events/components/EventAgendaWorkspace.tsx',
        );
        self::assertStringContainsString('session.registration.can_register', $react);
        self::assertStringContainsString('session.capacity.registered', $react);
        self::assertStringContainsString('rel="noopener noreferrer"', $react);

        $accessible = $this->source('accessible-frontend/views/event-agenda.blade.php');
        self::assertStringContainsString("? 'withdraw' : 'register'", $accessible);
        self::assertStringContainsString("registration['version']", $accessible);
        self::assertStringContainsString('rel="noopener noreferrer"', $accessible);

        $mobileApi = $this->source('mobile/lib/api/events.ts');
        self::assertStringContainsString('registerEventAgendaSession(', $mobileApi);
        self::assertStringContainsString('withdrawEventAgendaSession(', $mobileApi);
        $mobile = $this->source(
            'mobile/components/events/EventAgendaEnterprisePanel.tsx',
        );
        self::assertStringContainsString('<Card.Body', $mobile);
        self::assertStringContainsString('<Button.Label>', $mobile);
        self::assertStringContainsString('session.registration.version', $mobile);
    }

    public function test_enterprise_agenda_translations_are_complete_and_not_english_fallbacks(): void
    {
        $webLocales = ['ar', 'de', 'en', 'es', 'fr', 'ga', 'it', 'ja', 'nl', 'pl', 'pt'];
        $mobileLocales = ['de', 'en', 'es', 'fr', 'ga', 'it', 'pt'];
        $webKeys = null;
        $phpKeys = null;
        foreach ($webLocales as $locale) {
            /** @var array<string,mixed> $web */
            $web = json_decode(
                file_get_contents($this->root . "/react-frontend/public/locales/{$locale}/event_agenda.json"),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
            $flattenedWeb = $this->flatten($web);
            $webKeys ??= array_keys($flattenedWeb);
            self::assertSame($webKeys, array_keys($flattenedWeb), "web {$locale}");
            self::assertSame(
                $this->placeholders((string) $flattenedWeb['capacity_limited']),
                ['limit', 'registered'],
                "web {$locale}",
            );
            if ($locale !== 'en') {
                self::assertNotSame('Session capacity', $flattenedWeb['capacity_label'], $locale);
            }

            /** @var array<string,mixed> $php */
            $php = require $this->root . "/lang/{$locale}/event_agenda.php";
            $flattenedPhp = $this->flatten($php);
            $phpKeys ??= array_keys($flattenedPhp);
            self::assertSame($phpKeys, array_keys($flattenedPhp), "php {$locale}");
            self::assertSame(
                $this->phpPlaceholders((string) $flattenedPhp['capacity_limited']),
                ['limit', 'registered'],
                "php {$locale}",
            );
        }

        $mobileKeys = null;
        foreach ($mobileLocales as $locale) {
            /** @var array<string,mixed> $mobile */
            $mobile = json_decode(
                file_get_contents($this->root . "/mobile/locales/{$locale}/events.json"),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
            $flattened = $this->flatten($mobile['agenda']['enterprise'] ?? []);
            $mobileKeys ??= array_keys($flattened);
            self::assertSame($mobileKeys, array_keys($flattened), "mobile {$locale}");
            self::assertSame(
                $this->placeholders((string) $flattened['capacityLimited']),
                ['limit', 'registered'],
                "mobile {$locale}",
            );
            if ($locale !== 'en') {
                self::assertNotSame('Session resources', $flattened['resourcesTitle'], $locale);
            }
        }
    }

    private function source(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, $path);

        return $source;
    }

    /** @param array<string,mixed> $value @return array<string,mixed> */
    private function flatten(array $value, string $prefix = ''): array
    {
        $flat = [];
        foreach ($value as $key => $item) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($item)) {
                $flat += $this->flatten($item, $path);
            } else {
                $flat[$path] = $item;
            }
        }
        ksort($flat);

        return $flat;
    }

    /** @return list<string> */
    private function placeholders(string $value): array
    {
        preg_match_all('/{{\s*([A-Za-z0-9_]+)\s*}}/', $value, $matches);
        $placeholders = array_values(array_unique($matches[1] ?? []));
        sort($placeholders);

        return $placeholders;
    }

    /** @return list<string> */
    private function phpPlaceholders(string $value): array
    {
        preg_match_all('/(?<!:):([A-Za-z0-9_]+)/', $value, $matches);
        $placeholders = array_values(array_unique($matches[1] ?? []));
        sort($placeholders);

        return $placeholders;
    }
}
