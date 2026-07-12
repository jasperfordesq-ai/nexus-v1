<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Models\User;
use App\Services\EventIntegrityAuditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

final class EventTimeIdentityIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_audit_reports_invalid_time_and_missing_concrete_identity(): void
    {
        $organizer = User::factory()->forTenant($this->testTenantId)->create();
        $eventId = $this->insertEvent((int) $organizer->id, [
            'timezone' => 'Mars/Olympus',
            'timezone_source' => 'preexisting_unverified',
            'all_day' => null,
            'occurrence_key' => null,
        ]);

        $result = (new EventIntegrityAuditService())->run($this->testTenantId, 20);
        $issues = collect($result['issues'])->keyBy('code');

        foreach ([
            'event_timezone_invalid',
            'event_timezone_fallback_provenance',
            'event_all_day_semantics_missing',
            'event_concrete_occurrence_key_missing',
        ] as $code) {
            $this->assertTrue($issues->has($code), "Missing integrity issue {$code}");
            $this->assertContains($eventId, $issues->get($code)['sample_ids']);
        }
        $this->assertTrue($result['blocking']);
    }

    public function test_audit_rejects_registrations_attached_to_recurrence_template(): void
    {
        $organizer = User::factory()->forTenant($this->testTenantId)->create();
        $attendee = User::factory()->forTenant($this->testTenantId)->create();
        $templateId = $this->insertEvent((int) $organizer->id, [
            'title' => 'Abstract recurrence template',
            'timezone' => 'UTC',
            'timezone_source' => 'tenant_setting',
            'all_day' => 0,
            'occurrence_key' => null,
            'is_recurring_template' => 1,
            'recurrence_engine' => 'legacy',
            'recurrence_engine_version' => '1',
        ]);
        $rsvpId = (int) DB::table('event_rsvps')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $templateId,
            'user_id' => $attendee->id,
            'status' => 'going',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = (new EventIntegrityAuditService())->run($this->testTenantId, 20);
        $issue = collect($result['issues'])->firstWhere(
            'code',
            'event_rsvps_attached_to_recurrence_template',
        );

        $this->assertNotNull($issue);
        $this->assertContains($rsvpId, $issue['sample_ids']);
        $this->assertTrue($result['blocking']);
    }

    /** @param array<string,mixed> $overrides */
    private function insertEvent(int $organizerId, array $overrides = []): int
    {
        $start = now()->addWeek();

        return (int) DB::table('events')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $organizerId,
            'title' => 'Integrity fixture',
            'description' => 'Event time identity audit fixture.',
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'timezone' => 'UTC',
            'timezone_source' => 'tenant_setting',
            'all_day' => 0,
            'occurrence_key' => 'integrity:' . bin2hex(random_bytes(12)),
            'recurrence_engine' => null,
            'recurrence_engine_version' => null,
            'is_recurring_template' => 0,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
