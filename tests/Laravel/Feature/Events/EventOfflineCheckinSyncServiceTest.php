<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Enums\EventOfflineSyncBatchStatus;
use App\Enums\EventOfflineSyncOutcome;
use App\Exceptions\EventOfflineCheckinException;
use App\Models\User;
use App\Services\EventCheckinCredentialService;
use App\Services\EventCheckinDeviceService;
use App\Services\EventOfflineCheckinSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

final class EventOfflineCheckinSyncServiceTest extends TestCase
{
    use DatabaseTransactions;

    private EventCheckinCredentialService $credentials;
    private EventCheckinDeviceService $devices;
    private EventOfflineCheckinSyncService $sync;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
        $this->credentials = new EventCheckinCredentialService();
        $this->devices = new EventCheckinDeviceService();
        $this->sync = new EventOfflineCheckinSyncService($this->devices);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_stage_is_bounded_idempotent_private_and_preserves_untrusted_evidence(): void
    {
        $now = CarbonImmutable::parse('2027-03-01T10:00:00Z');
        CarbonImmutable::setTestNow($now);
        [$owner, $eventId, $credentialSecret, $deviceSecret, $manifestVersion] = $this->fixture($now);
        $knownHash = hash('sha256', $credentialSecret);
        $unknownHash = hash('sha256', 'unknown-offline-credential');
        $items = [
            $this->item('scan-nonce-0001', $knownHash, $now, [
                'reason' => "<script>alert(1)</script>\0 Manual scan",
            ]),
            $this->item('scan-nonce-0002', $unknownHash, $now->addSecond(), [
                'operation' => 'check_out',
                'expected_attendance_version' => 1,
            ]),
        ];

        $staged = $this->sync->stage(
            $eventId,
            $deviceSecret,
            (int) $owner->id,
            'client-batch-0001',
            $manifestVersion,
            $items,
        );
        $replay = $this->sync->stage(
            $eventId,
            $deviceSecret,
            (int) $owner->id,
            'client-batch-0001',
            $manifestVersion,
            $items,
        );

        self::assertTrue($staged->staged);
        self::assertFalse($replay->staged);
        self::assertSame($staged->batch->id, $replay->batch->id);
        self::assertSame(EventOfflineSyncBatchStatus::Pending, $staged->batch->status);
        self::assertCount(2, $staged->items);
        self::assertSame('alert(1) Manual scan', $staged->items[0]->submitted_reason);
        self::assertNotNull($staged->items[0]->credential_id);
        self::assertNull($staged->items[1]->credential_id);
        self::assertNull($staged->items[1]->registration_id);
        self::assertNull($staged->items[1]->user_id);
        self::assertSame(0, DB::table('event_attendance')->where('event_id', $eventId)->count());
        self::assertSame(1, DB::table('event_offline_sync_batches')->count());
        self::assertSame(2, DB::table('event_offline_sync_items')->count());

        $storedBatch = DB::table('event_offline_sync_batches')->first();
        self::assertNotNull($storedBatch);
        self::assertSame(64, strlen((string) $storedBatch->payload_hash));
        self::assertStringNotContainsString($deviceSecret, json_encode($storedBatch, JSON_THROW_ON_ERROR));
        $storedItems = json_encode(
            DB::table('event_offline_sync_items')->get()->all(),
            JSON_THROW_ON_ERROR,
        );
        self::assertStringNotContainsString($credentialSecret, $storedItems);
        self::assertStringNotContainsString($deviceSecret, $storedItems);

        $this->assertReason(
            'event_offline_batch_idempotency_conflict',
            fn () => $this->sync->stage(
                $eventId,
                $deviceSecret,
                (int) $owner->id,
                'client-batch-0001',
                $manifestVersion,
                [$this->item('scan-nonce-0001', $knownHash, $now, ['operation' => 'undo'])],
            ),
        );
        $this->assertReason(
            'event_offline_nonce_conflict',
            fn () => $this->sync->stage(
                $eventId,
                $deviceSecret,
                (int) $owner->id,
                'client-batch-0002',
                $manifestVersion,
                [$this->item('scan-nonce-0001', $knownHash, $now)],
            ),
        );
        $duplicate = $this->item('scan-nonce-0003', $knownHash, $now);
        $this->assertReason(
            'event_offline_nonce_duplicate',
            fn () => $this->sync->stage(
                $eventId,
                $deviceSecret,
                (int) $owner->id,
                'client-batch-0003',
                $manifestVersion,
                [$duplicate, $duplicate],
            ),
        );
        $this->assertReason(
            'event_offline_batch_size_invalid',
            fn () => $this->sync->stage(
                $eventId,
                $deviceSecret,
                (int) $owner->id,
                'client-batch-oversize',
                $manifestVersion,
                array_fill(0, 501, $duplicate),
            ),
        );
        $this->assertReason(
            'event_offline_observed_at_outside_window',
            fn () => $this->sync->stage(
                $eventId,
                $deviceSecret,
                (int) $owner->id,
                'client-batch-stale',
                $manifestVersion,
                [$this->item('scan-nonce-stale', $knownHash, $now->subDays(2))],
            ),
        );
    }

    public function test_expired_claim_is_recovered_and_decisions_are_explicit_append_only_without_attendance_writes(): void
    {
        $now = CarbonImmutable::parse('2027-03-02T10:00:00Z');
        CarbonImmutable::setTestNow($now);
        [$owner, $eventId, $credentialSecret, $deviceSecret, $manifestVersion] = $this->fixture($now);
        $manager = $this->user('Attendance manager');
        $hash = hash('sha256', $credentialSecret);
        $stage = $this->sync->stage(
            $eventId,
            $deviceSecret,
            (int) $owner->id,
            'claim-batch-0001',
            $manifestVersion,
            [
                $this->item('claim-nonce-0001', $hash, $now),
                $this->item('claim-nonce-0002', $hash, $now->addSecond(), [
                    'operation' => 'check_out',
                    'expected_attendance_version' => 1,
                ]),
            ],
        );
        $firstClaim = $this->sync->claimNext($eventId, 15);
        self::assertNotNull($firstClaim);
        self::assertSame(1, (int) $firstClaim->batch->claim_attempts);

        CarbonImmutable::setTestNow($now->addSeconds(16));
        $recovered = $this->sync->claimNext($eventId, 30);
        self::assertNotNull($recovered);
        self::assertSame($firstClaim->batch->id, $recovered->batch->id);
        self::assertSame(2, (int) $recovered->batch->claim_attempts);
        self::assertNotSame($firstClaim->claimToken, $recovered->claimToken);
        $this->assertReason(
            'event_offline_claim_invalid',
            fn () => $this->sync->decide(
                (int) $stage->batch->id,
                (int) $stage->items[0]->id,
                $firstClaim->claimToken,
                (int) $owner->id,
                EventOfflineSyncOutcome::Conflict,
                'attendance.version_conflict',
                'Stale offline attendance version',
                0,
                null,
                null,
                'decision-stale-claim',
            ),
        );

        $conflict = $this->sync->decide(
            (int) $stage->batch->id,
            (int) $stage->items[0]->id,
            $recovered->claimToken,
            (int) $manager->id,
            EventOfflineSyncOutcome::Conflict,
            'attendance.version_conflict',
            '<b>Expected version was stale</b>',
            0,
            null,
            null,
            'decision-conflict-0001',
        );
        self::assertTrue($conflict->recorded);
        self::assertSame((int) $manager->id, (int) $conflict->decision->decided_by_user_id);
        self::assertSame('Expected version was stale', $conflict->decision->decision_reason);
        self::assertSame(EventOfflineSyncBatchStatus::Processing, $conflict->batch->status);
        self::assertSame(1, (int) $conflict->batch->conflict_count);

        $this->assertReason(
            'event_offline_decision_attendance_evidence_invalid',
            fn () => $this->sync->decide(
                (int) $stage->batch->id,
                (int) $stage->items[1]->id,
                $recovered->claimToken,
                (int) $manager->id,
                EventOfflineSyncOutcome::Accepted,
                'attendance.applied',
                null,
                1,
                2,
                987654,
                'decision-accepted-missing-evidence',
            ),
        );
        $attendanceId = (int) DB::table('event_attendance')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => (int) $stage->items[1]->user_id,
            'attendance_status' => 'checked_out',
            'attendance_version' => 2,
            'status_changed_at' => now(),
            'status_changed_by' => (int) $manager->id,
            'checked_in_at' => now()->subMinute(),
            'checked_in_by' => (int) $manager->id,
            'checked_out_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $activityId = (int) DB::table('event_attendance_activity')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'attendance_id' => $attendanceId,
            'user_id' => (int) $stage->items[1]->user_id,
            'actor_user_id' => (int) $manager->id,
            'attendance_version' => 2,
            'action' => 'check_out',
            'from_status' => 'checked_in',
            'to_status' => 'checked_out',
            'idempotency_key' => 'canonical-attendance-evidence-0002',
            'reason' => null,
            'metadata' => json_encode(['schema_version' => 1], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
        try {
            DB::table('event_offline_sync_decisions')->insert([
                'tenant_id' => $this->testTenantId,
                'event_id' => $eventId,
                'batch_id' => (int) $stage->batch->id,
                'item_id' => (int) $stage->items[1]->id,
                'decision_version' => 1,
                'outcome' => 'accepted',
                'decision_code' => 'attendance.missing_evidence',
                'attendance_version_before' => 1,
                'attendance_version_after' => 2,
                'attendance_activity_id' => null,
                'decided_by_user_id' => (int) $manager->id,
                'idempotency_key_hash' => hash('sha256', 'direct-missing-attendance-evidence'),
                'request_hash' => hash('sha256', 'direct-missing-attendance-request'),
                'created_at' => now(),
            ]);
            self::fail('Accepted decision without attendance activity evidence was persisted.');
        } catch (QueryException $exception) {
            self::assertStringContainsString(
                'event_offline_decision_attendance_mismatch',
                $exception->getMessage(),
            );
        }
        try {
            DB::table('event_offline_sync_decisions')->insert([
                'tenant_id' => $this->testTenantId,
                'event_id' => $eventId,
                'batch_id' => (int) $stage->batch->id,
                'item_id' => (int) $stage->items[1]->id,
                'decision_version' => 1,
                'outcome' => 'accepted',
                'decision_code' => 'attendance.non_monotonic',
                'attendance_version_before' => 2,
                'attendance_version_after' => 2,
                'attendance_activity_id' => $activityId,
                'decided_by_user_id' => (int) $manager->id,
                'idempotency_key_hash' => hash('sha256', 'direct-non-monotonic-evidence'),
                'request_hash' => hash('sha256', 'direct-non-monotonic-request'),
                'created_at' => now(),
            ]);
            self::fail('Accepted decision with non-increasing attendance version was persisted.');
        } catch (QueryException $exception) {
            self::assertStringContainsString(
                'chk_event_offline_decision_attendance',
                $exception->getMessage(),
            );
        }
        $wrongAttendanceId = (int) DB::table('event_attendance')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => (int) $owner->id,
            'attendance_status' => 'checked_out',
            'attendance_version' => 2,
            'status_changed_at' => now(),
            'status_changed_by' => (int) $manager->id,
            'checked_in_at' => now()->subMinute(),
            'checked_in_by' => (int) $manager->id,
            'checked_out_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $wrongActivityId = (int) DB::table('event_attendance_activity')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'attendance_id' => $wrongAttendanceId,
            'user_id' => (int) $owner->id,
            'actor_user_id' => (int) $manager->id,
            'attendance_version' => 2,
            'action' => 'check_out',
            'from_status' => 'checked_in',
            'to_status' => 'checked_out',
            'idempotency_key' => 'wrong-attendee-activity-evidence',
            'reason' => null,
            'metadata' => json_encode(['schema_version' => 1], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
        try {
            DB::table('event_offline_sync_decisions')->insert([
                'tenant_id' => $this->testTenantId,
                'event_id' => $eventId,
                'batch_id' => (int) $stage->batch->id,
                'item_id' => (int) $stage->items[1]->id,
                'decision_version' => 1,
                'outcome' => 'accepted',
                'decision_code' => 'attendance.wrong_attendee',
                'attendance_version_before' => 1,
                'attendance_version_after' => 2,
                'attendance_activity_id' => $wrongActivityId,
                'decided_by_user_id' => (int) $manager->id,
                'idempotency_key_hash' => hash('sha256', 'direct-wrong-attendee-evidence'),
                'request_hash' => hash('sha256', 'direct-wrong-attendee-request'),
                'created_at' => now(),
            ]);
            self::fail('Accepted decision linked another attendee activity.');
        } catch (QueryException $exception) {
            self::assertStringContainsString(
                'event_offline_decision_attendance_mismatch',
                $exception->getMessage(),
            );
        }
        $attendanceCount = DB::table('event_attendance')->where('event_id', $eventId)->count();
        $activityCount = DB::table('event_attendance_activity')->where('event_id', $eventId)->count();

        $accepted = $this->sync->decide(
            (int) $stage->batch->id,
            (int) $stage->items[1]->id,
            $recovered->claimToken,
            (int) $manager->id,
            EventOfflineSyncOutcome::Accepted,
            'attendance.applied',
            null,
            1,
            2,
            $activityId,
            'decision-accepted-0002',
        );
        self::assertTrue($accepted->recorded);
        self::assertSame(EventOfflineSyncBatchStatus::Completed, $accepted->batch->status);
        self::assertSame(1, (int) $accepted->batch->accepted_count);
        self::assertSame(1, (int) $accepted->batch->conflict_count);
        self::assertSame(0, (int) $accepted->batch->rejected_count);
        self::assertNotNull($accepted->batch->completed_at);
        self::assertNull($accepted->batch->claim_token_hash);
        self::assertSame($attendanceCount, DB::table('event_attendance')
            ->where('event_id', $eventId)
            ->count());
        self::assertSame($activityCount, DB::table('event_attendance_activity')
            ->where('event_id', $eventId)
            ->count());

        $acceptedReplay = $this->sync->decide(
            (int) $stage->batch->id,
            (int) $stage->items[1]->id,
            $recovered->claimToken,
            (int) $manager->id,
            EventOfflineSyncOutcome::Accepted,
            'attendance.applied',
            null,
            1,
            2,
            $activityId,
            'decision-accepted-0002',
        );
        self::assertFalse($acceptedReplay->recorded);
        self::assertSame($accepted->decision->id, $acceptedReplay->decision->id);
        self::assertNull($this->sync->claimNext($eventId));

        try {
            DB::table('event_offline_sync_items')
                ->where('id', $stage->items[0]->id)
                ->update(['operation' => 'undo']);
            self::fail('Immutable submitted evidence accepted an update.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('event_offline_item_immutable', $exception->getMessage());
        }
        try {
            DB::table('event_offline_sync_decisions')
                ->where('id', $accepted->decision->id)
                ->update(['decision_code' => 'tampered']);
            self::fail('Append-only decision evidence accepted an update.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('event_offline_decision_immutable', $exception->getMessage());
        }
    }

    public function test_release_delay_device_actor_event_and_tenant_isolation_are_enforced(): void
    {
        $now = CarbonImmutable::parse('2027-03-03T10:00:00Z');
        CarbonImmutable::setTestNow($now);
        [$owner, $eventId, $credentialSecret, $deviceSecret, $manifestVersion] = $this->fixture($now);
        $otherActor = $this->user('Other check-in staff');
        $hash = hash('sha256', $credentialSecret);
        $stage = $this->sync->stage(
            $eventId,
            $deviceSecret,
            (int) $owner->id,
            'release-batch-0001',
            $manifestVersion,
            [$this->item('release-nonce-0001', $hash, $now)],
        );
        $claim = $this->sync->claimNext($eventId, 30);
        self::assertNotNull($claim);
        $released = $this->sync->releaseClaim(
            (int) $stage->batch->id,
            $claim->claimToken,
            60,
        );
        self::assertSame(EventOfflineSyncBatchStatus::Pending, $released->status);
        self::assertNotNull($released->last_released_at);
        self::assertNull($this->sync->claimNext($eventId));

        CarbonImmutable::setTestNow($now->addSeconds(61));
        $retried = $this->sync->claimNext($eventId);
        self::assertNotNull($retried);
        self::assertSame(2, (int) $retried->batch->claim_attempts);

        $this->assertReason(
            'event_checkin_actor_invalid',
            fn () => $this->sync->stage(
                $eventId,
                $deviceSecret,
                (int) $otherActor->id,
                'actor-isolation-batch',
                $manifestVersion,
                [$this->item('actor-isolation-nonce', $hash, $now->addMinute())],
            ),
        );
        $otherEventId = $this->event((int) $owner->id, $now->addDay());
        $this->assertReason(
            'event_checkin_device_invalid',
            fn () => $this->sync->stage(
                $otherEventId,
                $deviceSecret,
                (int) $owner->id,
                'event-isolation-batch',
                0,
                [$this->item('event-isolation-nonce', $hash, $now->addMinute())],
            ),
        );

        TenantContext::setById(999);
        self::assertNull($this->sync->claimNext());
        TenantContext::setById($this->testTenantId);
    }

    public function test_exhausted_or_explicitly_terminal_batches_are_durably_dead_lettered(): void
    {
        $now = CarbonImmutable::parse('2027-03-04T10:00:00Z');
        CarbonImmutable::setTestNow($now);
        [$owner, $eventId, $credentialSecret, $deviceSecret, $manifestVersion] = $this->fixture($now);
        $manager = $this->user('Offline recovery manager');
        $hash = hash('sha256', $credentialSecret);
        Config::set('event_checkin.sync_claim_max_attempts', 1);

        $releasedStage = $this->sync->stage(
            $eventId,
            $deviceSecret,
            (int) $owner->id,
            'terminal-release-batch',
            $manifestVersion,
            [$this->item('terminal-release-nonce', $hash, $now)],
        );
        $releasedClaim = $this->sync->claimNext($eventId, 15);
        self::assertNotNull($releasedClaim);
        $released = $this->sync->releaseClaim(
            (int) $releasedStage->batch->id,
            $releasedClaim->claimToken,
        );
        self::assertSame(EventOfflineSyncBatchStatus::DeadLetter, $released->status);
        self::assertSame('claim_attempts_exhausted', $released->terminal_code);
        self::assertNotNull($released->dead_lettered_at);
        self::assertNull($released->claim_token_hash);

        $staleStage = $this->sync->stage(
            $eventId,
            $deviceSecret,
            (int) $owner->id,
            'terminal-stale-batch',
            $manifestVersion,
            [$this->item('terminal-stale-nonce', $hash, $now->addSecond())],
        );
        $staleClaim = $this->sync->claimNext($eventId, 15);
        self::assertNotNull($staleClaim);
        CarbonImmutable::setTestNow($now->addSeconds(16));
        self::assertNull($this->sync->claimNext($eventId, 15));
        $stale = DB::table('event_offline_sync_batches')->where('id', $staleStage->batch->id)->first();
        self::assertNotNull($stale);
        self::assertSame(EventOfflineSyncBatchStatus::DeadLetter->value, $stale->status);
        self::assertSame('claim_attempts_exhausted', $stale->terminal_code);

        Config::set('event_checkin.sync_claim_max_attempts', 10);
        $explicitStage = $this->sync->stage(
            $eventId,
            $deviceSecret,
            (int) $owner->id,
            'terminal-explicit-batch',
            $manifestVersion,
            [$this->item('terminal-explicit-nonce', $hash, $now->addSeconds(2))],
        );
        $explicitClaim = $this->sync->claimNext($eventId, 30);
        self::assertNotNull($explicitClaim);
        $explicit = $this->sync->deadLetter(
            (int) $explicitStage->batch->id,
            $explicitClaim->claimToken,
            (int) $manager->id,
            'processor.unrecoverable',
            '<b>Canonical attendance evidence could not be reconciled</b>',
        );
        self::assertSame(EventOfflineSyncBatchStatus::DeadLetter, $explicit->status);
        self::assertSame('processor.unrecoverable', $explicit->terminal_code);
        self::assertSame(
            'Canonical attendance evidence could not be reconciled',
            $explicit->terminal_reason,
        );
        self::assertSame((int) $manager->id, (int) $explicit->terminal_by_user_id);
        self::assertNull($this->sync->claimNext($eventId));
    }

    /** @return array{User,int,string,string,int} */
    private function fixture(CarbonImmutable $now): array
    {
        $owner = $this->user('Offline owner');
        $attendee = $this->user('Offline attendee');
        $eventId = $this->event((int) $owner->id, $now->addDay());
        $registrationId = $this->registration($eventId, (int) $attendee->id);
        $credential = $this->credentials->issue(
            $eventId,
            $registrationId,
            (int) $owner->id,
            'offline-credential-' . bin2hex(random_bytes(4)),
        );
        $device = $this->devices->register(
            $eventId,
            (int) $owner->id,
            'Offline tablet',
            'offline-device-' . bin2hex(random_bytes(4)),
        );
        self::assertNotNull($credential->secret);
        self::assertNotNull($device->secret);

        return [
            $owner,
            $eventId,
            $credential->secret,
            $device->secret,
            $device->manifestVersion,
        ];
    }

    private function user(string $name): User
    {
        return User::factory()->forTenant($this->testTenantId)->create([
            'name' => $name,
            'status' => 'active',
            'is_approved' => true,
        ]);
    }

    private function event(int $ownerId, CarbonImmutable $start): int
    {
        $eventId = (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $ownerId,
            'title' => 'Offline sync fixture',
            'description' => 'Offline sync fixture.',
            'start_time' => $start,
            'end_time' => $start->addHours(4),
            'timezone' => 'UTC',
            'timezone_source' => 'test',
            'all_day' => false,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'is_recurring_template' => false,
            'checkin_manifest_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('events')->where('id', $eventId)->update([
            'occurrence_key' => "occurrence:{$eventId}",
        ]);

        return $eventId;
    }

    private function registration(int $eventId, int $userId): int
    {
        return (int) DB::table('event_registrations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $userId,
            'capacity_pool_key' => 'event',
            'registration_state' => 'confirmed',
            'registration_version' => 1,
            'state_changed_at' => now(),
            'state_changed_by' => $userId,
            'confirmed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string,mixed> */
    private function item(
        string $nonce,
        string $hash,
        CarbonImmutable $observedAt,
        array $overrides = [],
    ): array {
        return array_merge([
            'client_nonce' => $nonce,
            'operation' => 'check_in',
            'observed_at' => $observedAt->toIso8601String(),
            'expected_attendance_version' => 0,
            'credential_fingerprint' => substr($hash, 0, 16),
            'credential_hash_reference' => $hash,
            'reason' => null,
        ], $overrides);
    }

    /** @param callable():mixed $operation */
    private function assertReason(string $reason, callable $operation): void
    {
        try {
            $operation();
            self::fail("Expected {$reason}.");
        } catch (EventOfflineCheckinException $exception) {
            self::assertSame($reason, $exception->reasonCode);
            self::assertSame($reason, $exception->getMessage());
        }
    }
}
