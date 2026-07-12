<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Enums\EventCheckinCredentialStatus;
use App\Exceptions\EventOfflineCheckinException;
use App\Models\User;
use App\Services\EventCheckinCredentialService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

final class EventCheckinCredentialServiceTest extends TestCase
{
    use DatabaseTransactions;

    private EventCheckinCredentialService $service;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
        $this->service = new EventCheckinCredentialService();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_issue_is_one_shot_idempotent_secret_safe_and_constant_time_verified(): void
    {
        [$owner, $attendee, $eventId, $registrationId] = $this->fixture();

        $issued = $this->service->issue(
            $eventId,
            $registrationId,
            (int) $owner->id,
            'qr-issue-idempotency-001',
        );
        $replay = $this->service->issue(
            $eventId,
            $registrationId,
            (int) $owner->id,
            'qr-issue-idempotency-001',
        );

        self::assertTrue($issued->issued);
        self::assertNotNull($issued->secret);
        self::assertStringStartsWith('nqx2_', $issued->secret);
        self::assertFalse($replay->issued);
        self::assertNull($replay->secret);
        self::assertSame($issued->credential->id, $replay->credential->id);
        self::assertSame(1, $issued->manifestVersion);
        self::assertSame(1, DB::table('event_checkin_credentials')->count());
        self::assertSame(1, (int) DB::table('events')
            ->where('id', $eventId)
            ->value('checkin_manifest_version'));

        $stored = DB::table('event_checkin_credentials')->first();
        self::assertNotNull($stored);
        self::assertSame(hash('sha256', $issued->secret), $stored->token_hash);
        self::assertSame(substr((string) $stored->token_hash, 0, 16), $stored->token_fingerprint);
        self::assertStringNotContainsString($issued->secret, json_encode($stored, JSON_THROW_ON_ERROR));
        self::assertArrayNotHasKey('token_hash', $issued->credential->toArray());
        self::assertArrayNotHasKey('issue_idempotency_hash', $issued->credential->toArray());

        $verified = $this->service->verify($eventId, $issued->secret);
        self::assertSame((int) $attendee->id, (int) $verified->user_id);
        $this->assertReason(
            'event_qr_credential_invalid',
            fn () => $this->service->verify($eventId, 'nqx1_' . str_repeat('A', 43)),
        );
        $this->assertReason(
            'event_qr_credential_active_exists',
            fn () => $this->service->issue(
                $eventId,
                $registrationId,
                (int) $owner->id,
                'qr-issue-idempotency-002',
            ),
        );
        $this->assertReason(
            'event_qr_credential_idempotency_conflict',
            fn () => $this->service->issue(
                $eventId,
                $this->registration($eventId, (int) $owner->id, 'confirmed'),
                (int) $owner->id,
                'qr-issue-idempotency-001',
            ),
        );
    }

    public function test_rotation_and_revocation_are_monotonic_and_old_or_copied_codes_fail_closed(): void
    {
        [$owner, , $eventId, $registrationId] = $this->fixture();
        $first = $this->service->issue(
            $eventId,
            $registrationId,
            (int) $owner->id,
            'qr-lifecycle-issue',
        );
        self::assertNotNull($first->secret);

        $rotated = $this->service->rotate(
            $eventId,
            (int) $first->credential->id,
            (int) $owner->id,
            1,
            'qr-lifecycle-rotate',
        );
        $rotationReplay = $this->service->rotate(
            $eventId,
            (int) $first->credential->id,
            (int) $owner->id,
            1,
            'qr-lifecycle-rotate',
        );
        self::assertTrue($rotated->issued);
        self::assertNotNull($rotated->secret);
        self::assertSame(2, (int) $rotated->credential->credential_version);
        self::assertSame(2, $rotated->manifestVersion);
        self::assertFalse($rotationReplay->issued);
        self::assertNull($rotationReplay->secret);
        self::assertSame($rotated->credential->id, $rotationReplay->credential->id);
        self::assertSame(
            EventCheckinCredentialStatus::Rotated->value,
            DB::table('event_checkin_credentials')->where('id', $first->credential->id)->value('status'),
        );
        self::assertSame(
            $rotated->credential->id,
            DB::table('event_checkin_credentials')
                ->where('id', $first->credential->id)
                ->value('superseded_by_id'),
        );
        $this->assertReason(
            'event_qr_credential_not_active',
            fn () => $this->service->verify($eventId, (string) $first->secret),
        );
        self::assertSame(
            $rotated->credential->id,
            $this->service->verify($eventId, $rotated->secret)->id,
        );

        $revoked = $this->service->revoke(
            $eventId,
            (int) $rotated->credential->id,
            (int) $owner->id,
            2,
            '<b>Printed badge reported lost</b>',
        );
        self::assertSame(EventCheckinCredentialStatus::Revoked, $revoked->status);
        self::assertSame('Printed badge reported lost', $revoked->revocation_reason);
        self::assertSame(3, (int) DB::table('events')
            ->where('id', $eventId)
            ->value('checkin_manifest_version'));
        $this->assertReason(
            'event_qr_credential_not_active',
            fn () => $this->service->verify($eventId, $rotated->secret),
        );
    }

    public function test_expiry_confirmation_occurrence_and_tenant_boundaries_fail_closed(): void
    {
        $now = CarbonImmutable::parse('2027-01-10T10:00:00Z');
        CarbonImmutable::setTestNow($now);
        [$owner, $attendee, $eventId, $registrationId] = $this->fixture($now->addDay());
        $issued = $this->service->issue(
            $eventId,
            $registrationId,
            (int) $owner->id,
            'qr-expiry-issue',
            $now->addMinutes(5),
        );
        self::assertNotNull($issued->secret);

        CarbonImmutable::setTestNow($now->addMinutes(6));
        $this->assertReason(
            'event_qr_credential_expired',
            fn () => $this->service->verify($eventId, $issued->secret),
        );
        $expired = $this->service->expire($eventId, (int) $issued->credential->id, 1);
        self::assertSame(EventCheckinCredentialStatus::Expired, $expired->status);

        CarbonImmutable::setTestNow($now);
        $freshRegistration = $this->registration($eventId, (int) $owner->id, 'confirmed');
        $fresh = $this->service->issue(
            $eventId,
            $freshRegistration,
            (int) $owner->id,
            'qr-confirmation-issue',
            $now->addHours(2),
        );
        DB::table('event_registrations')->where('id', $freshRegistration)->update([
            'registration_state' => 'cancelled',
            'registration_version' => 2,
            'state_changed_at' => now(),
            'cancelled_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertReason(
            'event_qr_confirmed_registration_required',
            fn () => $this->service->verify($eventId, (string) $fresh->secret),
        );

        $templateId = $this->event((int) $owner->id, $now->addDay(), true);
        $templateRegistration = $this->registration(
            $templateId,
            (int) $attendee->id,
            'confirmed',
        );
        $this->assertReason(
            'event_checkin_concrete_occurrence_required',
            fn () => $this->service->issue(
                $templateId,
                $templateRegistration,
                (int) $owner->id,
                'qr-template-issue',
            ),
        );

        TenantContext::setById(999);
        $this->assertReason(
            'event_checkin_event_not_found',
            fn () => $this->service->verify($eventId, (string) $fresh->secret),
        );
        TenantContext::setById($this->testTenantId);
    }

    public function test_database_active_slot_is_the_concurrent_issue_safety_net(): void
    {
        [$owner, $attendee, $eventId, $registrationId] = $this->fixture();
        $issued = $this->service->issue(
            $eventId,
            $registrationId,
            (int) $owner->id,
            'qr-race-winner',
        );
        $hash = hash('sha256', 'simulated-race-loser');

        try {
            DB::table('event_checkin_credentials')->insert([
                'tenant_id' => $this->testTenantId,
                'event_id' => $eventId,
                'occurrence_key' => "occurrence:{$eventId}",
                'registration_id' => $registrationId,
                'user_id' => (int) $attendee->id,
                'credential_version' => 2,
                'status' => 'active',
                'active_slot' => 1,
                'token_hash' => $hash,
                'token_fingerprint' => substr($hash, 0, 16),
                'issue_idempotency_hash' => hash('sha256', 'qr-race-loser'),
                'issued_by_user_id' => (int) $owner->id,
                'issued_at' => now(),
                'expires_at' => now()->addHour(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            self::fail('Unique active credential slot accepted a second race winner.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('uq_event_qr_credential_active', $exception->getMessage());
        }

        self::assertSame(1, DB::table('event_checkin_credentials')
            ->where('registration_id', $registrationId)
            ->where('status', 'active')
            ->count());
        self::assertSame($issued->credential->id, DB::table('event_checkin_credentials')
            ->where('registration_id', $registrationId)
            ->where('status', 'active')
            ->value('id'));
    }

    /** @return array{User,User,int,int} */
    private function fixture(?CarbonImmutable $start = null): array
    {
        $owner = $this->user();
        $attendee = $this->user();
        $eventId = $this->event((int) $owner->id, $start);
        $registrationId = $this->registration($eventId, (int) $attendee->id, 'confirmed');

        return [$owner, $attendee, $eventId, $registrationId];
    }

    private function user(): User
    {
        return User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
    }

    private function event(
        int $ownerId,
        ?CarbonImmutable $start = null,
        bool $template = false,
    ): int {
        $start ??= CarbonImmutable::now('UTC')->addMonth()->startOfHour();
        $eventId = (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $ownerId,
            'title' => 'QR credential fixture',
            'description' => 'QR credential fixture.',
            'start_time' => $start,
            'end_time' => $start->addHours(4),
            'timezone' => 'UTC',
            'timezone_source' => 'test',
            'all_day' => false,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'is_recurring_template' => $template,
            'checkin_manifest_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if (! $template) {
            DB::table('events')->where('id', $eventId)->update([
                'occurrence_key' => "occurrence:{$eventId}",
            ]);
        }

        return $eventId;
    }

    private function registration(int $eventId, int $userId, string $state): int
    {
        return (int) DB::table('event_registrations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $userId,
            'capacity_pool_key' => 'event',
            'registration_state' => $state,
            'registration_version' => 1,
            'state_changed_at' => now(),
            'state_changed_by' => $userId,
            'confirmed_at' => $state === 'confirmed' ? now() : null,
            'pending_at' => $state === 'pending' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param callable():mixed $operation */
    private function assertReason(string $reason, callable $operation): void
    {
        try {
            $operation();
            self::fail("Expected {$reason}.");
        } catch (EventOfflineCheckinException $exception) {
            self::assertSame($reason, $exception->reasonCode);
        }
    }
}
