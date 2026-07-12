<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Enums\EventAnalyticsFactStatus;
use App\Enums\EventAnalyticsMetric;
use App\Exceptions\EventAnalyticsException;
use App\Models\Event;
use App\Models\User;
use App\Services\EventAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

final class EventAnalyticsServiceTest extends TestCase
{
    use DatabaseTransactions;

    private EventAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        config()->set('events.analytics.optional_capture_enabled', true);
        $this->service = app(EventAnalyticsService::class);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_optional_fact_is_suppressed_without_current_analytics_consent(): void
    {
        [$user, $event] = $this->subjects();

        $result = $this->service->recordOptional(
            (int) $event->id,
            $user,
            EventAnalyticsMetric::EventViewed,
            'view-without-consent-0001',
            CarbonImmutable::now('UTC'),
            $this->dimensions(),
        );

        self::assertFalse($result->recorded);
        self::assertSame('suppressed_no_consent', $result->outcome);
        self::assertSame(0, DB::table('event_analytics_optional_facts')->count());
    }

    public function test_optional_capture_rollout_flag_fails_closed(): void
    {
        [$user, $event] = $this->subjects();
        $this->consent($user);
        config()->set('events.analytics.optional_capture_enabled', false);

        $result = $this->service->recordOptional(
            (int) $event->id,
            $user,
            EventAnalyticsMetric::EventViewed,
            'disabled-view-capture-0001',
            CarbonImmutable::now('UTC'),
            $this->dimensions(),
        );

        self::assertFalse($result->recorded);
        self::assertSame('suppressed_disabled', $result->outcome);
        self::assertSame(0, DB::table('event_analytics_optional_facts')->count());
    }

    public function test_consented_fact_is_pseudonymous_idempotent_and_conflict_safe(): void
    {
        [$user, $event] = $this->subjects();
        $consentId = $this->consent($user);
        $occurred = CarbonImmutable::now('UTC')->subHours(25)->startOfSecond();

        $created = $this->service->recordOptional(
            (int) $event->id,
            $user,
            EventAnalyticsMetric::RegistrationStarted,
            'registration-attempt-0001',
            $occurred,
            $this->dimensions('registration'),
        );
        self::assertTrue($created->recorded);
        self::assertTrue($created->created);
        self::assertSame('recorded', $created->outcome);
        self::assertNotNull($created->fact);
        self::assertTrue((bool) $created->fact->is_late);

        $row = DB::table('event_analytics_optional_facts')->find($created->fact->id);
        self::assertNotNull($row);
        self::assertSame($consentId, (int) $row->consent_record_id);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $row->subject_hash);
        self::assertObjectNotHasProperty('user_id', $row);
        self::assertObjectNotHasProperty('email', $row);
        self::assertObjectNotHasProperty('ip_address', $row);

        $replay = $this->service->recordOptional(
            (int) $event->id,
            $user,
            EventAnalyticsMetric::RegistrationStarted,
            'registration-attempt-0001',
            $occurred,
            $this->dimensions('registration'),
        );
        self::assertTrue($replay->recorded);
        self::assertFalse($replay->created);
        self::assertSame('replayed', $replay->outcome);
        self::assertSame(1, DB::table('event_analytics_optional_facts')->count());

        $this->expectException(EventAnalyticsException::class);
        $this->expectExceptionMessage('event_analytics_idempotency_conflict');
        $this->service->recordOptional(
            (int) $event->id,
            $user,
            EventAnalyticsMetric::RegistrationStarted,
            'registration-attempt-0001',
            $occurred->addSecond(),
            $this->dimensions('registration'),
        );
    }

    public function test_withdrawal_anonymises_optional_facts_and_stops_new_capture(): void
    {
        [$user, $event] = $this->subjects();
        $this->consent($user);
        $occurred = CarbonImmutable::now('UTC')->startOfSecond();
        $created = $this->service->recordOptional(
            (int) $event->id,
            $user,
            EventAnalyticsMetric::EventViewed,
            'withdrawn-view-0001',
            $occurred,
            $this->dimensions(),
        );
        self::assertTrue($created->created);

        DB::table('cookie_consents')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', (int) $user->id)
            ->update(['withdrawal_date' => now(), 'updated_at' => now()]);
        $withdrawal = $this->service->withdrawForActor($user, 'withdraw-events-analytics-0001');
        self::assertSame(1, $withdrawal['withdrawn']);
        self::assertFalse($withdrawal['replayed']);

        $row = DB::table('event_analytics_optional_facts')->find($created->fact?->id);
        self::assertSame(EventAnalyticsFactStatus::Withdrawn->value, $row->status);
        self::assertNull($row->subject_hash);
        self::assertNull($row->consent_record_id);
        self::assertSame([], json_decode((string) $row->dimensions, true, 512, JSON_THROW_ON_ERROR));

        $replay = $this->service->recordOptional(
            (int) $event->id,
            $user,
            EventAnalyticsMetric::EventViewed,
            'withdrawn-view-0001',
            $occurred,
            $this->dimensions(),
        );
        self::assertFalse($replay->recorded);
        self::assertSame('suppressed_withdrawn', $replay->outcome);

        $newAttempt = $this->service->recordOptional(
            (int) $event->id,
            $user,
            EventAnalyticsMetric::EventViewed,
            'withdrawn-view-0002',
            $occurred,
            $this->dimensions(),
        );
        self::assertFalse($newAttempt->recorded);
        self::assertSame('suppressed_no_consent', $newAttempt->outcome);

        $idempotent = $this->service->withdrawForActor(
            $user,
            'withdraw-events-analytics-0001',
        );
        self::assertTrue($idempotent['replayed']);
        self::assertSame($withdrawal['run_id'], $idempotent['run_id']);
    }

    public function test_operational_metrics_cannot_be_copied_into_optional_fact_store(): void
    {
        [$user, $event] = $this->subjects();
        $this->consent($user);

        $this->expectException(EventAnalyticsException::class);
        $this->expectExceptionMessage('event_analytics_operational_fact_forbidden');
        $this->service->recordOptional(
            (int) $event->id,
            $user,
            EventAnalyticsMetric::AttendanceRecorded,
            'forbidden-operational-0001',
            CarbonImmutable::now('UTC'),
            $this->dimensions(),
        );
    }

    public function test_cross_tenant_or_unviewable_event_fails_closed(): void
    {
        [$user, $event] = $this->subjects();
        $this->consent($user);
        TenantContext::setById(999);

        $this->expectException(EventAnalyticsException::class);
        $this->expectExceptionMessage('event_analytics_actor_invalid');
        $this->service->recordOptional(
            (int) $event->id,
            $user,
            EventAnalyticsMetric::EventViewed,
            'cross-tenant-view-0001',
            CarbonImmutable::now('UTC'),
            $this->dimensions(),
        );
    }

    public function test_metric_dictionary_is_complete_and_names_authoritative_sources(): void
    {
        $dictionary = $this->service->metricDictionary();
        self::assertCount(count(EventAnalyticsMetric::cases()), $dictionary);

        foreach (EventAnalyticsMetric::cases() as $metric) {
            $definition = $dictionary[$metric->value];
            self::assertNotSame('', $definition['owner']);
            self::assertNotSame('', $definition['purpose']);
            self::assertNotSame('', $definition['source']);
            self::assertNotSame('', $definition['deduplication']);
            self::assertNotSame('', $definition['late_event_rule']);
            self::assertSame($metric->isOptional(), $definition['consent_required']);
        }
        self::assertSame(
            'event_attendance and event_attendance_activity',
            $dictionary[EventAnalyticsMetric::AttendanceRecorded->value]['source'],
        );
        self::assertSame(
            'event_notification_deliveries',
            $dictionary[EventAnalyticsMetric::CommunicationDelivered->value]['source'],
        );
    }

    public function test_access_audit_requires_event_management_and_stores_only_query_hash(): void
    {
        [$owner, $event] = $this->subjects();
        $auditId = $this->service->auditAccess(
            (int) $event->id,
            $owner,
            'organizer_summary',
            'dashboard_view',
            ['from' => '2026-01-01', 'to' => '2026-12-31', 'status' => 'confirmed'],
            12,
            4,
        );
        $audit = DB::table('event_analytics_access_audits')->find($auditId);
        self::assertNotNull($audit);
        self::assertSame(5, (int) $audit->privacy_threshold);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $audit->query_hash);
        self::assertObjectNotHasProperty('query', $audit);

        $outsider = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
        ]);
        try {
            $this->service->auditAccess(
                (int) $event->id,
                $outsider,
                'csv_export',
                'csv_export',
                ['status' => 'confirmed'],
                12,
                0,
            );
            self::fail('Cross-organizer analytics access was accepted.');
        } catch (EventAnalyticsException $exception) {
            self::assertSame('event_analytics_event_not_found', $exception->reasonCode);
        }
        self::assertSame(1, DB::table('event_analytics_access_audits')->count());
    }

    /** @return array{User,Event} */
    private function subjects(): array
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $event = Event::factory()->forTenant($this->testTenantId)->create([
            'user_id' => (int) $user->id,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'is_recurring_template' => false,
        ]);

        return [$user, $event];
    }

    private function consent(User $user): int
    {
        return (int) DB::table('cookie_consents')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => (int) $user->id,
            'session_id' => 'events-analytics-' . bin2hex(random_bytes(8)),
            'essential' => 1,
            'functional' => 0,
            'analytics' => 1,
            'marketing' => 0,
            'consent_version' => '1.0',
            'created_at' => now(),
            'updated_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
    }

    /** @return array{source_surface:string,client_platform:string} */
    private function dimensions(string $surface = 'event_detail'): array
    {
        return [
            'source_surface' => $surface,
            'client_platform' => 'react_web',
        ];
    }
}
