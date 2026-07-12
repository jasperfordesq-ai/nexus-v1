<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use PHPUnit\Framework\TestCase;

final class EventMigrationChainSafetyStaticTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 4);
    }

    public function test_event_migration_prerequisites_fail_closed_instead_of_silently_returning(): void
    {
        foreach ([
            '2026_07_11_000053_create_event_agenda_sessions.php'
                => 'event_agenda_prerequisite_missing:',
            '2026_07_11_000054_create_event_federation_reliability_foundation.php'
                => 'event_federation_prerequisite_missing:',
            '2026_07_11_000055_create_event_offline_checkin_foundation.php'
                => 'event_offline_checkin_prerequisite_missing:',
            '2026_07_11_000056_create_event_registration_forms_and_invitations.php'
                => 'event_registration_forms_prerequisite_missing:',
            '2026_07_11_000057_create_event_ticketing_foundation.php'
                => 'event_ticketing_prerequisite_missing:',
            '2026_07_11_000058_create_event_templates_foundation.php'
                => 'event_templates_prerequisite_missing:',
            '2026_07_11_000060_create_event_safety_foundation.php'
                => 'event_safety_prerequisite_missing:',
            '2026_07_11_000061_add_event_guardian_delivery_boundary.php'
                => 'event_guardian_delivery_prerequisite_missing:',
            '2026_07_11_000063_expand_event_registration_forms_and_invitations_phase_b.php'
                => 'event_registration_phase_b_prerequisite_missing:',
            '2026_07_11_000064_add_event_venue_accessibility.php'
                => 'event_venue_accessibility_prerequisite_missing:',
            '2026_07_11_000065_expand_event_agenda_enterprise.php'
                => 'event_agenda_enterprise_prerequisite_missing:',
            '2026_07_11_000066_add_event_context_to_notification_queue.php'
                => 'event_notification_queue_context_prerequisite_missing:',
        ] as $migration => $exceptionMarker) {
            $source = $this->source('database/migrations/' . $migration);
            $up = $this->between($source, 'public function up(): void', 'public function down(): void');

            self::assertStringContainsString($exceptionMarker, $up, $migration);
            self::assertStringNotContainsString('return;', $up, $migration);
        }

        $safety = $this->source(
            'database/migrations/2026_07_11_000060_create_event_safety_foundation.php',
        );
        self::assertStringContainsString(
            'event_safety_prerequisite_column_missing:',
            $safety,
        );
    }

    public function test_registration_ticketing_and_delivery_rollbacks_guard_newer_schema(): void
    {
        $registration = $this->source(
            'database/migrations/2026_07_11_000056_create_event_registration_forms_and_invitations.php',
        );
        foreach ([
            'event_registration_forms_rollback_refused_dependents_exist',
            'event_invitation_campaign_history',
            'event_invitation_delivery_evidence',
            'event_registration_guest_attendance',
            'ticket_entitlement_id',
            'information_schema.REFERENTIAL_CONSTRAINTS',
        ] as $needle) {
            self::assertStringContainsString($needle, $registration, $needle);
        }

        $ticketing = $this->source(
            'database/migrations/2026_07_11_000057_create_event_ticketing_foundation.php',
        );
        foreach ([
            'event_ticketing_rollback_refused_dependents_exist',
            "Schema::hasColumn('event_registration_guests', 'ticket_entitlement_id')",
            'information_schema.REFERENTIAL_CONSTRAINTS',
        ] as $needle) {
            self::assertStringContainsString($needle, $ticketing, $needle);
        }

        $delivery = $this->source(
            'database/migrations/2026_07_11_000061_add_event_guardian_delivery_boundary.php',
        );
        foreach ([
            'event_guardian_delivery_rollback_refused_dependents_exist',
            "Schema::hasTable('event_invitation_delivery_evidence')",
            "UNIQUE_CONSTRAINT_NAME', 'uq_event_outbox_scope_id'",
            'event_guardian_delivery_rollback_refused_guardian_locale_evidence',
            "whereNotNull('guardian_locale')",
        ] as $needle) {
            self::assertStringContainsString($needle, $delivery, $needle);
        }
    }

    public function test_accessibility_rollback_preserves_any_non_null_venue_evidence(): void
    {
        $source = $this->source(
            'database/migrations/2026_07_11_000064_add_event_venue_accessibility.php',
        );

        foreach ([
            'event_venue_accessibility_rollback_refused_evidence_exists',
            'private const COLUMNS',
            'containsDurableEvidence()',
            'orWhereNotNull($column)',
        ] as $needle) {
            self::assertStringContainsString($needle, $source, $needle);
        }
    }

    public function test_focused_events_harness_includes_migration_chain_safety_contract(): void
    {
        $source = $this->source('scripts/test-events.mjs');
        $path = 'tests/Laravel/Unit/Services/EventMigrationChainSafetyStaticTest.php';

        self::assertGreaterThanOrEqual(2, substr_count($source, $path));
        self::assertStringContainsString("phpBatch === 'contract'", $source);
    }

    private function source(string $relative): string
    {
        $source = file_get_contents($this->root . '/' . $relative);
        self::assertIsString($source, "Could not read {$relative}");

        return $source;
    }

    private function between(string $source, string $start, string $end): string
    {
        $startAt = strpos($source, $start);
        self::assertNotFalse($startAt, "Missing start marker: {$start}");
        $endAt = strpos($source, $end, $startAt);
        self::assertNotFalse($endAt, "Missing end marker: {$end}");

        return substr($source, $startAt, $endAt - $startAt);
    }
}
