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

final class EventRegistrationIntegrityAuditTest extends TestCase
{
    use DatabaseTransactions;

    public function test_audit_reports_union_capacity_overbooking_and_malformed_offer_evidence(): void
    {
        $organizer = $this->member();
        $first = $this->member();
        $second = $this->member();
        $waiter = $this->member();
        $eventId = $this->event($organizer, 1);
        $this->registration($eventId, $first, 1, 'confirmed');
        $this->registration($eventId, $second, 1, 'confirmed');
        $now = now();
        $entryId = (int) DB::table('event_waitlist_entries')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $waiter->id,
            'capacity_pool_key' => 'event',
            'queue_state' => 'offered',
            'queue_version' => 1,
            'queue_sequence' => 1,
            'state_changed_at' => $now,
            'state_changed_by' => $organizer->id,
            'offered_at' => $now,
            'offer_expires_at' => $now->copy()->addMinutes(15),
            'offer_token_hash' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->waitlistHistory($entryId, $eventId, $waiter, 1, 1, 'offered');
        $before = [
            DB::table('event_registrations')->count(),
            DB::table('event_registration_history')->count(),
            DB::table('event_waitlist_entries')->count(),
            DB::table('event_waitlist_entry_history')->count(),
        ];

        $result = (new EventIntegrityAuditService())->run($this->testTenantId, 25);
        $codes = collect($result['issues'])->pluck('code');

        self::assertTrue($codes->contains('event_capacity_overbooked'));
        self::assertTrue($codes->contains('event_waitlist_offer_evidence_missing'));
        self::assertSame($before, [
            DB::table('event_registrations')->count(),
            DB::table('event_registration_history')->count(),
            DB::table('event_waitlist_entries')->count(),
            DB::table('event_waitlist_entry_history')->count(),
        ]);
        self::assertTrue($result['read_only']);
        self::assertTrue($result['blocking']);
    }

    public function test_audit_reconciles_latest_history_and_accepted_registration_scope(): void
    {
        $organizer = $this->member();
        $registered = $this->member();
        $waiter = $this->member();
        $eventId = $this->event($organizer, 3);
        $registrationId = $this->registration($eventId, $registered, 1, 'confirmed');
        $now = now();
        $entryId = (int) DB::table('event_waitlist_entries')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $waiter->id,
            'capacity_pool_key' => 'event',
            'queue_state' => 'accepted',
            'queue_version' => 2,
            'queue_sequence' => 1,
            'state_changed_at' => $now,
            'state_changed_by' => $waiter->id,
            'offered_at' => $now->copy()->subMinute(),
            'offer_expires_at' => $now->copy()->addMinutes(10),
            'offer_token_hash' => hash('sha256', 'audit-token'),
            'offer_token_used_at' => $now,
            'accepted_at' => $now,
            // Deliberately points at another user's confirmed registration.
            'accepted_registration_id' => $registrationId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        // Deliberately omit version 2 so latest fact/history reconciliation fails.
        $this->waitlistHistory($entryId, $eventId, $waiter, 1, 1, 'offered');

        $codes = collect((new EventIntegrityAuditService())->run($this->testTenantId, 25)['issues'])
            ->pluck('code');

        self::assertTrue($codes->contains('event_waitlist_accepted_registration_mismatch'));
        self::assertTrue($codes->contains('event_waitlist_fact_history_missing_or_stale'));
    }

    private function member(): User
    {
        return User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
    }

    private function event(User $organizer, int $capacity): int
    {
        $start = now()->addWeek();

        return (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $organizer->id,
            'title' => 'Registration audit fixture',
            'description' => 'Registration audit fixture.',
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'timezone' => 'UTC',
            'timezone_source' => 'explicit',
            'all_day' => 0,
            'occurrence_key' => 'registration-audit:' . bin2hex(random_bytes(10)),
            'is_recurring_template' => 0,
            'max_attendees' => $capacity,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function registration(int $eventId, User $user, int $version, string $state): int
    {
        $now = now();
        $id = (int) DB::table('event_registrations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $user->id,
            'capacity_pool_key' => 'event',
            'registration_state' => $state,
            'registration_version' => $version,
            'state_changed_at' => $now,
            'state_changed_by' => $user->id,
            "{$state}_at" => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('event_registration_history')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'registration_id' => $id,
            'user_id' => $user->id,
            'actor_user_id' => $user->id,
            'capacity_pool_key' => 'event',
            'registration_version' => $version,
            'action' => $state,
            'to_state' => $state,
            'idempotency_key' => hash('sha256', "audit-registration:{$id}:{$version}"),
            'metadata' => json_encode(['schema_version' => 1], JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);

        return $id;
    }

    private function waitlistHistory(
        int $entryId,
        int $eventId,
        User $user,
        int $version,
        int $sequence,
        string $state,
    ): void {
        DB::table('event_waitlist_entry_history')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'waitlist_entry_id' => $entryId,
            'user_id' => $user->id,
            'actor_user_id' => $user->id,
            'capacity_pool_key' => 'event',
            'queue_version' => $version,
            'queue_sequence' => $sequence,
            'action' => $state,
            'to_state' => $state,
            'idempotency_key' => hash('sha256', "audit-waitlist:{$entryId}:{$version}"),
            'metadata' => json_encode(['schema_version' => 1], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
