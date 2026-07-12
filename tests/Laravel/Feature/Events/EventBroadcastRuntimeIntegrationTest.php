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
use App\Services\EventBroadcastService;
use App\Services\SafeguardingInteractionPolicy;
use App\Support\Events\EventBroadcastRenderedMessage;
use App\Support\Events\EventBroadcastTransportResult;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Laravel\TestCase;

final class EventBroadcastRuntimeIntegrationTest extends TestCase
{
    use DatabaseTransactions;

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
            'notification_preferences' => json_encode([], JSON_THROW_ON_ERROR),
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
