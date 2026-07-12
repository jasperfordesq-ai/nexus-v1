<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Enums\EventAnalyticsMetric;
use App\Exceptions\EventAnalyticsException;
use App\Models\Event;
use App\Models\User;
use App\Services\EventAnalyticsQueryService;
use App\Services\EventAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

final class EventAnalyticsQueryServiceTest extends TestCase
{
    use DatabaseTransactions;

    private EventAnalyticsQueryService $queries;
    private EventAnalyticsService $capture;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        config()->set('events.analytics.optional_capture_enabled', true);
        config()->set('events.analytics.privacy_threshold', 5);
        $this->queries = app(EventAnalyticsQueryService::class);
        $this->capture = app(EventAnalyticsService::class);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_summary_reconciles_ledgers_and_suppresses_small_optional_cohorts(): void
    {
        $owner = $this->user();
        $event = Event::factory()->forTenant($this->testTenantId)->create([
            'user_id' => (int) $owner->id,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'is_recurring_template' => false,
            'max_attendees' => 10,
        ]);
        $members = [];
        for ($index = 0; $index < 5; $index++) {
            $member = $this->user();
            $members[] = $member;
            $this->consent($member);
            DB::table('event_registrations')->insert([
                'tenant_id' => $this->testTenantId,
                'event_id' => (int) $event->id,
                'user_id' => (int) $member->id,
                'capacity_pool_key' => 'event',
                'allocation_key' => null,
                'registration_state' => 'confirmed',
                'registration_version' => 1,
                'state_changed_at' => now(),
                'state_changed_by' => (int) $member->id,
                'confirmed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->capture->recordOptional(
                (int) $event->id,
                $member,
                EventAnalyticsMetric::RegistrationStarted,
                "analytics-start-{$event->id}-{$member->id}",
                CarbonImmutable::now('UTC'),
                ['source_surface' => 'registration', 'client_platform' => 'react_web'],
            );
            if ($index < 4) {
                $this->capture->recordOptional(
                    (int) $event->id,
                    $member,
                    EventAnalyticsMetric::EventViewed,
                    "analytics-view-{$event->id}-{$member->id}",
                    CarbonImmutable::now('UTC'),
                    ['source_surface' => 'event_detail', 'client_platform' => 'react_web'],
                );
            }
        }

        DB::table('event_attendance')->insert([
            [
                'tenant_id' => $this->testTenantId,
                'event_id' => (int) $event->id,
                'user_id' => (int) $members[0]->id,
                'attendance_status' => 'attended',
                'attendance_version' => 1,
                'status_changed_at' => now(),
                'status_changed_by' => (int) $owner->id,
                'checked_in_at' => now(),
                'checked_in_by' => (int) $owner->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $this->testTenantId,
                'event_id' => (int) $event->id,
                'user_id' => (int) $members[1]->id,
                'attendance_status' => 'no_show',
                'attendance_version' => 1,
                'status_changed_at' => now(),
                'status_changed_by' => (int) $owner->id,
                'checked_in_at' => null,
                'checked_in_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $outboxId = (int) DB::table('event_domain_outbox')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => (int) $event->id,
            'aggregate_stream' => 'lifecycle',
            'aggregate_version' => 1,
            'action' => 'event.updated',
            'idempotency_key' => "analytics-outbox-{$event->id}",
            'production_mode' => 'outbox',
            'status' => 'processed',
            'payload' => json_encode(['schema_version' => 1], JSON_THROW_ON_ERROR),
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach (['delivered', 'suppressed'] as $index => $status) {
            DB::table('event_notification_deliveries')->insert([
                'tenant_id' => $this->testTenantId,
                'outbox_id' => $outboxId,
                'recipient_user_id' => (int) $members[$index]->id,
                'channel' => 'email',
                'delivery_key' => "analytics-delivery-{$event->id}-{$index}",
                'status' => $status,
                'attempts' => 1,
                'delivered_at' => $status === 'delivered' ? now() : null,
                'suppressed_at' => $status === 'suppressed' ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $summary = $this->queries->summary((int) $event->id, $owner);

        self::assertSame(5, $summary['registration']['confirmed']);
        self::assertSame(5, $summary['registration']['remaining']);
        self::assertSame(1, $summary['attendance']['attended']);
        self::assertSame(1, $summary['attendance']['no_show']);
        self::assertSame(5000, $summary['attendance']['attendance_rate']['basis_points']);
        self::assertSame(1, $summary['communications']['delivered']);
        self::assertSame(1, $summary['communications']['suppressed']);
        self::assertTrue($summary['optional_funnel']['event_views']['suppressed']);
        self::assertNull($summary['optional_funnel']['event_views']['value']);
        self::assertFalse($summary['optional_funnel']['registration_starts']['suppressed']);
        self::assertSame(5, $summary['optional_funnel']['registration_starts']['value']);
        self::assertSame(
            10000,
            $summary['optional_funnel']['start_to_registration_conversion']['basis_points'],
        );
        self::assertSame(1, DB::table('event_analytics_access_audits')
            ->where('event_id', $event->id)
            ->where('purpose_code', 'dashboard_view')
            ->count());
    }

    public function test_cross_organizer_access_is_hidden_and_not_audited(): void
    {
        $owner = $this->user();
        $outsider = $this->user();
        $event = Event::factory()->forTenant($this->testTenantId)->create([
            'user_id' => (int) $owner->id,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'is_recurring_template' => false,
        ]);

        try {
            $this->queries->summary((int) $event->id, $outsider);
            self::fail('Cross-organizer analytics access was accepted.');
        } catch (EventAnalyticsException $exception) {
            self::assertSame('event_analytics_event_not_found', $exception->reasonCode);
        }
        self::assertSame(0, DB::table('event_analytics_access_audits')
            ->where('event_id', $event->id)
            ->count());
    }

    private function user(): User
    {
        return User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
    }

    private function consent(User $user): int
    {
        return (int) DB::table('cookie_consents')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => (int) $user->id,
            'session_id' => 'events-analytics-query-' . bin2hex(random_bytes(8)),
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
}
