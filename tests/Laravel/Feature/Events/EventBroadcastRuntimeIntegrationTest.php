<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Contracts\EventBroadcastTransport;
use App\Core\TenantContext;
use App\Enums\EventBroadcastChannel;
use App\Enums\EventBroadcastStatus;
use App\Models\User;
use App\Services\EventBroadcastAudienceResolver;
use App\Services\EventBroadcastDeliveryConsumer;
use App\Services\EventBroadcastQueryService;
use App\Services\EventBroadcastService;
use App\Services\SafeguardingInteractionPolicy;
use App\Support\Events\EventBroadcastRenderedMessage;
use App\Support\Events\EventBroadcastTransportResult;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\Laravel\TestCase;

final class EventBroadcastRuntimeIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_individual_broadcast_history_is_bounded_and_independently_paginated(): void
    {
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['events' => true], JSON_THROW_ON_ERROR),
        ]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $organizer = $this->user('History Organizer', 'en');
        $eventId = $this->event((int) $organizer->id);
        $occurrenceKey = (string) DB::table('events')->where('id', $eventId)->value('occurrence_key');
        $broadcastId = (int) DB::table('event_broadcasts')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'occurrence_key' => $occurrenceKey,
            'variant' => 'announcement',
            'status' => 'draft',
            'broadcast_version' => 105,
            'audience_segments' => json_encode(['registration_confirmed'], JSON_THROW_ON_ERROR),
            'channels' => json_encode(['in_app'], JSON_THROW_ON_ERROR),
            'body' => 'History pagination fixture.',
            'content_hash' => hash('sha256', 'History pagination fixture.'),
            'recipient_count' => 0,
            'delivery_count' => 0,
            'delivered_count' => 0,
            'suppressed_count' => 0,
            'dead_letter_count' => 0,
            'created_by_user_id' => (int) $organizer->id,
            'updated_by_user_id' => (int) $organizer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $rows = [];
        for ($version = 1; $version <= 105; $version++) {
            $rows[] = [
                'tenant_id' => $this->testTenantId,
                'event_id' => $eventId,
                'broadcast_id' => $broadcastId,
                'broadcast_version' => $version,
                'action' => $version === 1 ? 'created' : 'revised',
                'from_status' => $version === 1 ? null : 'draft',
                'to_status' => 'draft',
                'actor_user_id' => (int) $organizer->id,
                'idempotency_hash' => hash('sha256', "history-page-idempotency-{$version}"),
                'request_hash' => hash('sha256', "history-page-request-{$version}"),
                'content_hash' => hash('sha256', "history-page-content-{$version}"),
                'metadata' => '{}',
                'created_at' => now()->addSeconds($version),
            ];
        }
        DB::table('event_broadcast_history')->insert($rows);

        $queries = app(EventBroadcastQueryService::class);
        $first = $queries->detail($broadcastId, $organizer);
        self::assertCount(50, $first['history']);
        self::assertSame(1, $first['history'][0]['version']);
        self::assertSame(50, $first['history'][49]['version']);
        self::assertSame([
            'current_page' => 1,
            'per_page' => 50,
            'total' => 105,
            'total_pages' => 3,
            'has_more' => true,
        ], $first['history_meta']);

        $last = $queries->detail($broadcastId, $organizer, 2, 500);
        self::assertCount(5, $last['history']);
        self::assertSame(101, $last['history'][0]['version']);
        self::assertSame(105, $last['history'][4]['version']);
        self::assertSame([
            'current_page' => 2,
            'per_page' => 100,
            'total' => 105,
            'total_pages' => 2,
            'has_more' => false,
        ], $last['history_meta']);

        $clamped = $queries->detail($broadcastId, $organizer, 999, 50);
        self::assertCount(5, $clamped['history']);
        self::assertSame(3, $clamped['history_meta']['current_page']);
        self::assertSame(101, $clamped['history'][0]['version']);

        Sanctum::actingAs($organizer, ['*']);
        $this->apiGet("/v2/event-broadcasts/{$broadcastId}?history_page=999&history_per_page=50")
            ->assertOk()
            ->assertJsonPath('data.history_meta.current_page', 3)
            ->assertJsonCount(5, 'data.history');
        $this->apiGet("/v2/event-broadcasts/{$broadcastId}?history_page=invalid")
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'EVENT_BROADCAST_VALIDATION_FAILED')
            ->assertJsonPath('errors.0.field', 'history_page');
        $this->apiGet("/v2/event-broadcasts/{$broadcastId}?history_per_page=0")
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.field', 'history_per_page');
        $this->apiGet("/v2/events/{$eventId}/broadcasts?page=invalid")
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.field', 'page');
        $this->apiGet("/v2/events/{$eventId}/broadcasts?per_page=0")
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.field', 'per_page');
    }

    public function test_versioned_broadcast_is_idempotent_locale_safe_and_delivered_once(): void
    {
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['events' => true], JSON_THROW_ON_ERROR),
            'configuration' => json_encode([
                'notifications' => [
                    'event_defaults' => [
                        'channels' => [
                            'email' => false,
                            'in_app' => true,
                            'web_push' => false,
                            'fcm' => false,
                            'realtime' => false,
                        ],
                        'cadence' => 'instant',
                        'reminders_enabled' => true,
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $organizer = $this->user('Broadcast Organizer', 'en');
        $recipient = $this->user('Broadcast Recipient', 'fr');
        $eventId = $this->event((int) $organizer->id);
        $this->confirmedRegistration($eventId, (int) $recipient->id, (int) $organizer->id);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertManyLocalContactsAllowed')
            ->with((int) $organizer->id, [(int) $recipient->id], $this->testTenantId, 'event_broadcast')
            ->atLeast()->once();
        $policy->shouldReceive('assertLocalContactAllowed')
            ->with((int) $organizer->id, (int) $recipient->id, $this->testTenantId, 'event_broadcast')
            ->once();

        $broadcasts = new EventBroadcastService(new EventBroadcastAudienceResolver($policy));
        $preview = $broadcasts->preview(
            $eventId,
            $organizer,
            'announcement',
            ['registration_confirmed'],
            ['in_app'],
        );
        self::assertSame(1, $preview['recipient_count']);
        self::assertSame(1, $preview['delivery_count']);

        $body = "Please arrive ten minutes early.\n<script>alert('unsafe')</script>";
        $created = $broadcasts->createDraft(
            $eventId,
            $organizer,
            'announcement',
            ['registration_confirmed'],
            ['in_app'],
            $body,
            'runtime-broadcast-create',
        );
        self::assertTrue($created['changed']);
        $broadcastId = (int) $created['broadcast']->id;
        $createReplay = $broadcasts->createDraft(
            $eventId,
            $organizer,
            'announcement',
            ['registration_confirmed'],
            ['in_app'],
            $body,
            'runtime-broadcast-create',
        );
        self::assertFalse($createReplay['changed']);
        self::assertSame($broadcastId, (int) $createReplay['broadcast']->id);

        $scheduled = $broadcasts->schedule(
            $broadcastId,
            $organizer,
            1,
            null,
            'runtime-broadcast-schedule',
        );
        self::assertTrue($scheduled['changed']);
        $scheduleReplay = $broadcasts->schedule(
            $broadcastId,
            $organizer,
            1,
            null,
            'runtime-broadcast-schedule',
        );
        self::assertFalse($scheduleReplay['changed']);
        self::assertSame(1, DB::table('event_broadcast_deliveries')
            ->where('tenant_id', $this->testTenantId)
            ->where('broadcast_id', $broadcastId)
            ->count());

        $transport = new class implements EventBroadcastTransport
        {
            /** @var list<array<string,mixed>> */
            public array $calls = [];

            public function send(
                EventBroadcastChannel $channel,
                int $tenantId,
                int $eventId,
                object $recipient,
                EventBroadcastRenderedMessage $message,
                string $deliveryKey,
                string $emailCadence,
            ): EventBroadcastTransportResult {
                $this->calls[] = [
                    'channel' => $channel->value,
                    'tenant_id' => $tenantId,
                    'event_id' => $eventId,
                    'recipient_id' => (int) $recipient->id,
                    'locale' => App::getLocale(),
                    'subject' => $message->subject,
                    'message' => $message->message,
                    'html' => $message->html,
                    'delivery_key' => $deliveryKey,
                    'cadence' => $emailCadence,
                ];

                return new EventBroadcastTransportResult('test_transport', 'evidence-1');
            }
        };
        App::setLocale('en');
        $consumer = new EventBroadcastDeliveryConsumer(
            $broadcasts,
            $policy,
            transport: $transport,
        );
        $summary = $consumer->processBatch(10, $this->testTenantId);

        self::assertSame([
            'claimed' => 1,
            'delivered' => 1,
            'suppressed' => 0,
            'retrying' => 0,
            'dead_lettered' => 0,
            'stale_released' => 0,
        ], $summary);
        self::assertCount(1, $transport->calls);
        self::assertSame('fr', $transport->calls[0]['locale']);
        self::assertSame('in_app', $transport->calls[0]['channel']);
        self::assertSame((int) $recipient->id, $transport->calls[0]['recipient_id']);
        self::assertSame($body, $transport->calls[0]['message']);
        self::assertStringNotContainsString('<script>', (string) $transport->calls[0]['html']);
        self::assertStringContainsString('unsafe', (string) $transport->calls[0]['html']);
        self::assertSame('en', App::getLocale());
        self::assertSame(EventBroadcastStatus::Sent->value, DB::table('event_broadcasts')
            ->where('tenant_id', $this->testTenantId)
            ->where('id', $broadcastId)
            ->value('status'));
        self::assertSame('delivered', DB::table('event_broadcast_deliveries')
            ->where('tenant_id', $this->testTenantId)
            ->where('broadcast_id', $broadcastId)
            ->value('status'));
        self::assertSame(1, DB::table('event_broadcast_delivery_attempts')
            ->where('tenant_id', $this->testTenantId)
            ->where('broadcast_id', $broadcastId)
            ->count());

        $secondPass = $consumer->processBatch(10, $this->testTenantId);
        self::assertSame(0, $secondPass['claimed']);
        self::assertCount(1, $transport->calls);
    }

    private function user(string $name, string $locale): User
    {
        return User::factory()->forTenant($this->testTenantId)->create([
            'name' => $name,
            'first_name' => $name,
            'preferred_language' => $locale,
            'status' => 'active',
            'is_approved' => true,
            'notification_preferences' => [],
        ]);
    }

    private function event(int $organizerId): int
    {
        $start = CarbonImmutable::now('UTC')->addDay()->startOfHour();

        return (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $organizerId,
            'title' => 'Broadcast runtime event',
            'description' => 'Runtime delivery fixture.',
            'start_time' => $start,
            'end_time' => $start->addHours(2),
            'timezone' => 'UTC',
            'timezone_source' => 'test',
            'all_day' => false,
            'is_recurring_template' => false,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 1,
            'occurrence_key' => 'broadcast-runtime:' . bin2hex(random_bytes(12)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function confirmedRegistration(int $eventId, int $userId, int $actorId): void
    {
        DB::table('event_registrations')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $userId,
            'capacity_pool_key' => 'event',
            'registration_state' => 'confirmed',
            'registration_version' => 1,
            'state_changed_at' => now(),
            'state_changed_by' => $actorId,
            'confirmed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
