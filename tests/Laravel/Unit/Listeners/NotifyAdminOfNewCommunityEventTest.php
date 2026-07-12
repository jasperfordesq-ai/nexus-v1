<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Events\CommunityEventCreated;
use App\Listeners\NotifyAdminOfNewCommunityEvent;
use App\Models\Event as CommunityEventModel;
use App\Models\Notification;
use App\Services\EmailDispatchService;
use App\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\Laravel\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class NotifyAdminOfNewCommunityEventTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 997;

    private $notificationAlias;
    private $emailAlias;
    private $dispatcherAlias;

    protected function setUp(): void
    {
        // Static aliases must exist before the application bootstraps them.
        $this->notificationAlias = Mockery::mock('alias:' . Notification::class)->shouldIgnoreMissing();
        $this->emailAlias = Mockery::mock('alias:' . EmailDispatchService::class)->shouldIgnoreMissing();
        $this->dispatcherAlias = Mockery::mock('alias:' . NotificationDispatcher::class)->shouldIgnoreMissing();

        parent::setUp();

        config()->set('events.notification_delivery.mode', 'direct');
        config()->set('events.notification_delivery.max_attempts', 5);
        config()->set('events.notification_delivery.base_retry_seconds', 1);

        $this->seedTenant(self::TENANT_ID, 'Event Test Tenant', 'test-997');
    }

    public function test_listener_has_retryable_queue_contract(): void
    {
        $listener = new NotifyAdminOfNewCommunityEvent();

        $this->assertContains(ShouldQueue::class, class_implements($listener));
        $this->assertSame(5, $listener->tries);
        $this->assertSame(60, $listener->timeout);
        $this->assertSame([60, 300, 900, 1800], $listener->backoff);
    }

    public function test_handle_delivers_canonical_channels_and_records_durable_evidence(): void
    {
        $admin = $this->seedUser(['role' => 'admin']);
        $communityEvent = $this->seedEvent();

        $this->notificationAlias
            ->shouldReceive('createNotification')
            ->once()
            ->with($admin->id, Mockery::type('string'), '/events/' . $communityEvent->id, 'event_created');
        $this->dispatcherAlias
            ->shouldReceive('fanOutPush')
            ->once()
            ->with($admin->id, 'event_created', Mockery::type('string'), '/events/' . $communityEvent->id);
        $this->emailAlias
            ->shouldReceive('sendRaw')
            ->once()
            ->withArgs(static function (...$args) use ($admin, $communityEvent): bool {
                return $args[0] === $admin->email
                    && $args[6] === 'admin_new_event'
                    && ($args[7]['tenant_id'] ?? null) === self::TENANT_ID
                    && ($args[7]['event_id'] ?? null) === $communityEvent->id
                    && is_string($args[7]['idempotency_key'] ?? null);
            })
            ->andReturn(true);

        (new NotifyAdminOfNewCommunityEvent())->handle($this->domainEvent($communityEvent));

        $deliveries = DB::table('event_notification_deliveries')
            ->where('tenant_id', self::TENANT_ID)
            ->where('recipient_user_id', $admin->id)
            ->get();
        $this->assertCount(3, $deliveries);
        $this->assertSame(['email', 'in_app', 'push'], $deliveries->pluck('channel')->sort()->values()->all());
        $this->assertSame(['delivered'], $deliveries->pluck('status')->unique()->values()->all());
        $outbox = DB::table('event_domain_outbox')
            ->where('tenant_id', self::TENANT_ID)
            ->where('event_id', $communityEvent->id)
            ->first();
        $this->assertNotNull($outbox);
        $this->assertSame('event.admin_publication.created', $outbox->action);
        $this->assertStringNotContainsString('reminder', $outbox->action);
        $payload = json_decode((string) $outbox->payload, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('admin-publication-created:v1', $payload['delivery_identity']);
    }

    public function test_events_email_opt_out_keeps_bell_and_push_and_records_suppression(): void
    {
        $admin = $this->seedUser([
            'role' => 'admin',
            'notification_preferences' => json_encode(['email_events' => false]),
        ]);
        $communityEvent = $this->seedEvent();

        $this->notificationAlias->shouldReceive('createNotification')->once();
        $this->dispatcherAlias->shouldReceive('fanOutPush')->once();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        (new NotifyAdminOfNewCommunityEvent())->handle($this->domainEvent($communityEvent));

        $emailDelivery = DB::table('event_notification_deliveries')
            ->where('tenant_id', self::TENANT_ID)
            ->where('recipient_user_id', $admin->id)
            ->where('channel', 'email')
            ->first();
        $this->assertSame('suppressed', $emailDelivery->status);
        $this->assertSame('email_events', $emailDelivery->preference_reason);
    }

    public function test_daily_cadence_uses_durable_digest_queue_instead_of_immediate_email(): void
    {
        $admin = $this->seedUser(['role' => 'admin'], self::TENANT_ID, 'daily');
        $communityEvent = $this->seedEvent();

        $this->notificationAlias->shouldReceive('createNotification')->once();
        $this->dispatcherAlias->shouldReceive('fanOutPush')->once();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        (new NotifyAdminOfNewCommunityEvent())->handle($this->domainEvent($communityEvent));

        $queued = DB::table('notification_queue')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $admin->id)
            ->first();
        $this->assertNotNull($queued);
        $this->assertSame('event_created', $queued->activity_type);
        $this->assertSame('daily', $queued->frequency);
        $this->assertNotNull($queued->event_delivery_id);
        $this->assertNotEmpty($queued->idempotency_key);

        $this->assertDatabaseHas('event_notification_deliveries', [
            'id' => $queued->event_delivery_id,
            'status' => 'delivered',
            'provider' => 'notification_queue',
        ]);
    }

    public function test_event_organizer_is_excluded_from_admin_fanout(): void
    {
        $organizer = $this->seedUser([
            'role' => 'admin',
            'first_name' => 'Alice',
            'last_name' => 'Organizer',
            'name' => 'Alice Organizer',
        ]);
        $otherAdmin = $this->seedUser(['role' => 'admin']);
        $communityEvent = $this->seedEvent(['user_id' => $organizer->id]);

        $this->notificationAlias
            ->shouldReceive('createNotification')
            ->once()
            ->with($otherAdmin->id, Mockery::type('string'), Mockery::type('string'), 'event_created');
        $this->emailAlias
            ->shouldReceive('sendRaw')
            ->once()
            ->withArgs(static fn (...$args): bool => $args[0] === $otherAdmin->email
                && str_contains((string) $args[2], 'Alice Organizer'))
            ->andReturn(true);

        (new NotifyAdminOfNewCommunityEvent())->handle($this->domainEvent($communityEvent));

        $this->assertDatabaseMissing('event_notification_deliveries', [
            'tenant_id' => self::TENANT_ID,
            'recipient_user_id' => $organizer->id,
        ]);
    }

    public function test_fallback_copy_is_rendered_inside_each_admin_locale(): void
    {
        $english = $this->seedUser(['role' => 'admin', 'preferred_language' => 'en']);
        $german = $this->seedUser(['role' => 'admin', 'preferred_language' => 'de']);
        $communityEvent = $this->seedEvent(['title' => '', 'user_id' => 0]);
        $bodies = [];

        $this->notificationAlias->shouldReceive('createNotification')->twice();
        $this->emailAlias
            ->shouldReceive('sendRaw')
            ->twice()
            ->andReturnUsing(function (...$args) use (&$bodies): bool {
                $bodies[(string) $args[0]] = (string) $args[2];
                return true;
            });

        (new NotifyAdminOfNewCommunityEvent())->handle($this->domainEvent($communityEvent));

        $this->assertStringContainsString('Untitled event', $bodies[$english->email]);
        $this->assertStringContainsString('A member', $bodies[$english->email]);
        $this->assertStringContainsString('Veranstaltung ohne Titel', $bodies[$german->email]);
        $this->assertStringContainsString('Ein Mitglied', $bodies[$german->email]);
    }

    public function test_handle_notifies_every_eligible_admin_role(): void
    {
        foreach (['super_admin', 'admin', 'tenant_admin', 'broker', 'coordinator'] as $role) {
            $this->seedUser(['role' => $role]);
        }
        $communityEvent = $this->seedEvent();

        $this->notificationAlias->shouldReceive('createNotification')->times(5);
        $this->dispatcherAlias->shouldReceive('fanOutPush')->times(5);
        $this->emailAlias->shouldReceive('sendRaw')->times(5)->andReturn(true);

        (new NotifyAdminOfNewCommunityEvent())->handle($this->domainEvent($communityEvent));

        $this->assertSame(15, DB::table('event_notification_deliveries')
            ->where('tenant_id', self::TENANT_ID)
            ->count());
    }

    public function test_handle_excludes_inactive_plain_and_other_tenant_users(): void
    {
        $this->seedUser(['role' => 'admin', 'status' => 'inactive']);
        $this->seedUser(['role' => 'member']);
        $this->seedTenant(2, 'Other Event Test Tenant', 'other-event-test');
        $this->seedUser(['role' => 'admin'], 2);
        $communityEvent = $this->seedEvent();

        $this->notificationAlias->shouldReceive('createNotification')->never();
        $this->dispatcherAlias->shouldReceive('fanOutPush')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        (new NotifyAdminOfNewCommunityEvent())->handle($this->domainEvent($communityEvent));

        $this->assertDatabaseMissing('event_notification_deliveries', [
            'tenant_id' => self::TENANT_ID,
        ]);
    }

    public function test_durable_channel_ledger_suppresses_duplicate_listener_delivery(): void
    {
        $admin = $this->seedUser(['role' => 'admin']);
        $communityEvent = $this->seedEvent();

        $this->notificationAlias->shouldReceive('createNotification')->once();
        $this->dispatcherAlias->shouldReceive('fanOutPush')->once();
        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(true);

        $listener = new NotifyAdminOfNewCommunityEvent();
        $listener->handle($this->domainEvent($communityEvent));
        $listener->handle($this->domainEvent($communityEvent));

        $this->assertSame(3, DB::table('event_notification_deliveries')
            ->where('tenant_id', self::TENANT_ID)
            ->where('recipient_user_id', $admin->id)
            ->count());
        $this->assertSame(1, DB::table('event_domain_outbox')
            ->where('tenant_id', self::TENANT_ID)
            ->where('event_id', $communityEvent->id)
            ->count());
    }

    public function test_one_recipient_failure_does_not_block_remaining_admins(): void
    {
        $firstAdmin = $this->seedUser(['role' => 'admin']);
        $secondAdmin = $this->seedUser(['role' => 'admin']);
        $communityEvent = $this->seedEvent();

        $this->notificationAlias
            ->shouldReceive('createNotification')
            ->once()
            ->with($firstAdmin->id, Mockery::type('string'), Mockery::type('string'), 'event_created')
            ->andThrow(new RuntimeException('notification store unavailable'));
        $this->notificationAlias
            ->shouldReceive('createNotification')
            ->once()
            ->with($secondAdmin->id, Mockery::type('string'), Mockery::type('string'), 'event_created');
        $this->dispatcherAlias->shouldReceive('fanOutPush')->twice();
        $this->emailAlias->shouldReceive('sendRaw')->twice()->andReturn(true);

        try {
            (new NotifyAdminOfNewCommunityEvent())->handle($this->domainEvent($communityEvent));
            $this->fail('A retryable channel should requeue the listener.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('require retry', $e->getMessage());
        }

        $this->assertDatabaseHas('event_notification_deliveries', [
            'tenant_id' => self::TENANT_ID,
            'recipient_user_id' => $firstAdmin->id,
            'channel' => 'in_app',
            'status' => 'retrying',
        ]);
        $this->assertDatabaseHas('event_notification_deliveries', [
            'tenant_id' => self::TENANT_ID,
            'recipient_user_id' => $secondAdmin->id,
            'channel' => 'in_app',
            'status' => 'delivered',
        ]);
    }

    public function test_email_provider_failure_preserves_other_channels_and_requests_retry(): void
    {
        $admin = $this->seedUser(['role' => 'admin']);
        $communityEvent = $this->seedEvent();

        $this->notificationAlias->shouldReceive('createNotification')->once();
        $this->dispatcherAlias->shouldReceive('fanOutPush')->once();
        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(false);

        $this->expectException(RuntimeException::class);
        try {
            (new NotifyAdminOfNewCommunityEvent())->handle($this->domainEvent($communityEvent));
        } finally {
            $this->assertDatabaseHas('event_notification_deliveries', [
                'tenant_id' => self::TENANT_ID,
                'recipient_user_id' => $admin->id,
                'channel' => 'email',
                'status' => 'retrying',
            ]);
            $this->assertDatabaseHas('event_notification_deliveries', [
                'tenant_id' => self::TENANT_ID,
                'recipient_user_id' => $admin->id,
                'channel' => 'in_app',
                'status' => 'delivered',
            ]);
        }
    }

    public function test_admin_without_email_still_receives_non_email_channels(): void
    {
        $withoutEmail = $this->seedUser(['role' => 'admin', 'email' => '']);
        $withEmail = $this->seedUser(['role' => 'admin']);
        $communityEvent = $this->seedEvent();

        $this->notificationAlias->shouldReceive('createNotification')->twice();
        $this->dispatcherAlias->shouldReceive('fanOutPush')->twice();
        $this->emailAlias
            ->shouldReceive('sendRaw')
            ->once()
            ->withArgs(static fn (...$args): bool => $args[0] === $withEmail->email)
            ->andReturn(true);

        (new NotifyAdminOfNewCommunityEvent())->handle($this->domainEvent($communityEvent));

        $this->assertDatabaseHas('event_notification_deliveries', [
            'tenant_id' => self::TENANT_ID,
            'recipient_user_id' => $withoutEmail->id,
            'channel' => 'email',
            'status' => 'suppressed',
        ]);
    }

    public function test_disabled_events_feature_suppresses_routine_admin_fanout(): void
    {
        DB::table('tenants')->where('id', self::TENANT_ID)->update([
            'features' => json_encode(['events' => false]),
        ]);
        $this->seedUser(['role' => 'admin']);
        $communityEvent = $this->seedEvent();

        $this->notificationAlias->shouldReceive('createNotification')->never();
        $this->dispatcherAlias->shouldReceive('fanOutPush')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        (new NotifyAdminOfNewCommunityEvent())->handle($this->domainEvent($communityEvent));

        $this->assertDatabaseMissing('event_domain_outbox', [
            'tenant_id' => self::TENANT_ID,
            'event_id' => $communityEvent->id,
        ]);
    }

    private function seedUser(
        array $overrides = [],
        int $tenantId = self::TENANT_ID,
        string $frequency = 'instant',
    ): object {
        $unique = uniqid('u_', true);
        $data = array_merge([
            'tenant_id' => $tenantId,
            'name' => 'Test User ' . $unique,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $unique . '@example.com',
            'role' => 'member',
            'status' => 'active',
            'preferred_language' => 'en',
            'is_approved' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
        $id = (int) DB::table('users')->insertGetId($data);

        DB::table('notification_settings')->insert([
            'user_id' => $id,
            'context_type' => 'global',
            'context_id' => 0,
            'frequency' => $frequency,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) array_merge($data, ['id' => $id]);
    }

    private function seedEvent(array $overrides = []): CommunityEventModel
    {
        $unique = uniqid('e_', true);
        $data = array_merge([
            'tenant_id' => self::TENANT_ID,
            'user_id' => 0,
            'title' => 'Test Event ' . $unique,
            'description' => 'A test community event.',
            'start_time' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
        $id = (int) DB::table('events')->insertGetId($data);

        $model = new CommunityEventModel();
        $model->id = $id;
        $model->tenant_id = $data['tenant_id'];
        $model->user_id = $data['user_id'];
        $model->title = $data['title'];

        return $model;
    }

    private function seedTenant(int $id, string $name, string $slug): void
    {
        DB::table('tenants')->updateOrInsert(
            ['id' => $id],
            [
                'name' => $name,
                'slug' => $slug,
                'domain' => null,
                'features' => json_encode(['events' => true]),
                'is_active' => true,
                'depth' => 0,
                'allows_subtenants' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function domainEvent(CommunityEventModel $communityEvent): CommunityEventCreated
    {
        return new CommunityEventCreated($communityEvent, self::TENANT_ID);
    }
}
