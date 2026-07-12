<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Groups;

use App\Core\TenantContext;
use App\Enums\GroupStatus;
use App\Jobs\DeliverGroupWebhook;
use App\Models\Group;
use App\Models\Tenant;
use App\Models\User;
use App\Services\GroupFileService;
use App\Services\GroupService;
use App\Services\GroupWebhookService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Tests\Laravel\TestCase;
use Throwable;

final class GroupWebhookReliabilityTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;
    private Group $group;
    private Group $otherGroup;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::setById($this->testTenantId);
        $this->owner = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $this->group = Group::factory()->forTenant($this->testTenantId)->create([
            'owner_id' => $this->owner->id,
            'status' => GroupStatus::Active->value,
            'is_active' => true,
            'visibility' => 'public',
        ]);
        $this->otherGroup = Group::factory()->forTenant($this->testTenantId)->create([
            'owner_id' => $this->owner->id,
            'status' => GroupStatus::Active->value,
            'is_active' => true,
        ]);

        TenantContext::setById($this->testTenantId);
        Sanctum::actingAs($this->owner, ['*']);
        Queue::fake();
        Http::preventStrayRequests();
    }

    public function test_producer_only_persists_outbox_and_scheduler_enqueues_it(): void
    {
        $webhookId = $this->insertWebhook();

        GroupWebhookService::fire(
            (int) $this->group->id,
            GroupWebhookService::EVENT_FILE_UPLOADED,
            ['file_id' => 42],
        );

        $delivery = DB::table('group_webhook_deliveries')->where('webhook_id', $webhookId)->first();
        self::assertNotNull($delivery);
        self::assertSame('queued', $delivery->status);
        self::assertNull($delivery->dispatched_at);
        Http::assertNothingSent();
        Queue::assertNothingPushed();

        self::assertSame(1, GroupWebhookService::dispatchDueDeliveries());
        self::assertSame(0, GroupWebhookService::dispatchDueDeliveries());
        Queue::assertPushed(DeliverGroupWebhook::class, static fn (DeliverGroupWebhook $job): bool =>
            $job->deliveryId === (string) $delivery->id
            && $job->tenantId > 0
        );
        Http::assertNothingSent();
    }

    public function test_outbox_insert_participates_in_the_producer_transaction(): void
    {
        $webhookId = $this->insertWebhook();

        try {
            DB::transaction(function (): void {
                GroupWebhookService::fire(
                    (int) $this->group->id,
                    GroupWebhookService::EVENT_FILE_UPLOADED,
                    ['file_id' => 99],
                );
                throw new RuntimeException('roll back producer');
            });
        } catch (RuntimeException) {
            // Expected producer rollback.
        }

        self::assertSame(0, DB::table('group_webhook_deliveries')->where('webhook_id', $webhookId)->count());
    }

    public function test_only_200_and_204_are_recorded_as_success(): void
    {
        Http::fakeSequence()->push('accepted', 200)->push('', 204);
        foreach ([200, 204] as $status) {
            $webhookId = $this->insertWebhook();
            $deliveryId = $this->fireAndGetDelivery($webhookId, ['status' => $status]);

            self::assertSame('delivered', GroupWebhookService::deliver($deliveryId, $this->testTenantId));
            self::assertSame('skipped', GroupWebhookService::deliver($deliveryId, $this->testTenantId));
            $delivery = DB::table('group_webhook_deliveries')->where('id', $deliveryId)->first();
            self::assertSame('delivered', $delivery->status);
            self::assertSame($status, (int) $delivery->http_status);
            self::assertNotNull($delivery->delivered_at);
            self::assertSame(0, (int) DB::table('group_webhooks')->where('id', $webhookId)->value('failure_count'));
        }
        Http::assertSentCount(2);
    }

    public function test_400_500_and_redirect_responses_retry_and_capture_a_bounded_excerpt(): void
    {
        Http::fakeSequence()
            ->push("failure\x00" . str_repeat('x', 1500), 400)
            ->push("failure\x00" . str_repeat('x', 1500), 500)
            ->push("failure\x00" . str_repeat('x', 1500), 302, [
                'Location' => 'https://8.8.8.8/redirected',
            ]);
        foreach ([400, 500, 302] as $status) {
            $webhookId = $this->insertWebhook();
            $deliveryId = $this->fireAndGetDelivery($webhookId, ['status' => $status]);

            self::assertSame('retry', GroupWebhookService::deliver($deliveryId, $this->testTenantId));
            $delivery = DB::table('group_webhook_deliveries')->where('id', $deliveryId)->first();
            self::assertSame('retry', $delivery->status);
            self::assertSame('HTTP_' . $status, $delivery->last_error_code);
            self::assertSame($status, (int) $delivery->http_status);
            self::assertLessThanOrEqual(1000, mb_strlen((string) $delivery->response_excerpt));
            self::assertStringNotContainsString("\x00", (string) $delivery->response_excerpt);
            self::assertSame(1, (int) DB::table('group_webhooks')->where('id', $webhookId)->value('failure_count'));
        }
    }

    public function test_timeout_is_a_persisted_retry_and_tenant_context_is_restored(): void
    {
        $webhookId = $this->insertWebhook();
        $deliveryId = $this->fireAndGetDelivery($webhookId);
        Http::fake(static function (): never {
            throw new ConnectionException('Timed out');
        });
        $sentinelTenantId = (int) Tenant::factory()->create()->id;
        self::assertTrue(TenantContext::setById($sentinelTenantId));

        self::assertSame('retry', GroupWebhookService::deliver($deliveryId, $this->testTenantId));
        self::assertSame($sentinelTenantId, TenantContext::getId());
        self::assertSame('NETWORK_ERROR', DB::table('group_webhook_deliveries')->where('id', $deliveryId)->value('last_error_code'));
        self::assertSame(1, (int) DB::table('group_webhooks')->where('id', $webhookId)->value('failure_count'));
    }

    public function test_tenth_consecutive_failure_disables_endpoint_and_stops_retry(): void
    {
        $webhookId = $this->insertWebhook(failureCount: 9);
        $deliveryId = $this->fireAndGetDelivery($webhookId);
        Http::fake(static fn () => Http::response('still failing', 500));

        self::assertSame('failed', GroupWebhookService::deliver($deliveryId, $this->testTenantId));
        $webhook = DB::table('group_webhooks')->where('id', $webhookId)->first();
        self::assertSame(10, (int) $webhook->failure_count);
        self::assertFalse((bool) $webhook->is_active);
        self::assertNotNull($webhook->disabled_at);
        self::assertSame('failed', DB::table('group_webhook_deliveries')->where('id', $deliveryId)->value('status'));
    }

    public function test_ssrf_is_rechecked_at_delivery_and_unsafe_endpoint_is_disabled(): void
    {
        $webhookId = $this->insertWebhook(url: 'https://127.0.0.1/internal');
        $deliveryId = $this->fireAndGetDelivery($webhookId);
        Http::fake();

        self::assertSame('failed', GroupWebhookService::deliver($deliveryId, $this->testTenantId));
        self::assertSame('UNSAFE_URL', DB::table('group_webhook_deliveries')->where('id', $deliveryId)->value('last_error_code'));
        self::assertFalse((bool) DB::table('group_webhooks')->where('id', $webhookId)->value('is_active'));
        Http::assertNothingSent();
        self::assertNull(GroupWebhookService::register(
            (int) $this->group->id,
            'https://127.0.0.1/internal',
            [GroupWebhookService::EVENT_FILE_UPLOADED],
        ));
    }

    public function test_retry_backoff_is_not_dispatched_early_but_is_recoverable_when_due(): void
    {
        $webhookId = $this->insertWebhook();
        $deliveryId = $this->fireAndGetDelivery($webhookId);
        Http::fake(static fn () => Http::response('retry me', 500));
        self::assertSame('retry', GroupWebhookService::deliver($deliveryId, $this->testTenantId));

        Queue::fake();
        self::assertSame(0, GroupWebhookService::dispatchDueDeliveries());
        DB::table('group_webhook_deliveries')->where('id', $deliveryId)->update([
            'available_at' => now()->subSecond(),
            'dispatched_at' => null,
        ]);
        self::assertSame(1, GroupWebhookService::dispatchDueDeliveries());
        Queue::assertPushed(DeliverGroupWebhook::class, static fn (DeliverGroupWebhook $job): bool =>
            $job->deliveryId === $deliveryId
        );
    }

    public function test_delivery_claim_is_tenant_scoped(): void
    {
        $webhookId = $this->insertWebhook();
        $deliveryId = $this->fireAndGetDelivery($webhookId);
        Http::fake();

        self::assertSame('skipped', GroupWebhookService::deliver($deliveryId, 1));
        self::assertSame('queued', DB::table('group_webhook_deliveries')->where('id', $deliveryId)->value('status'));
        self::assertSame(0, (int) DB::table('group_webhook_deliveries')->where('id', $deliveryId)->value('attempt_count'));
        Http::assertNothingSent();
    }

    public function test_toggle_parses_string_false_idempotently_and_enforces_route_ownership(): void
    {
        $webhookId = $this->insertWebhook();
        $otherWebhookId = $this->insertWebhook(groupId: (int) $this->otherGroup->id);

        $this->apiPut("/v2/groups/{$this->group->id}/webhooks/{$webhookId}/toggle", ['is_active' => 'false'])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
        self::assertFalse((bool) DB::table('group_webhooks')->where('id', $webhookId)->value('is_active'));

        $this->apiPut("/v2/groups/{$this->group->id}/webhooks/{$webhookId}/toggle", ['is_active' => 'false'])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->apiPut("/v2/groups/{$this->group->id}/webhooks/{$webhookId}/toggle", ['is_active' => 'not-a-boolean'])
            ->assertStatus(422);
        $this->apiPut("/v2/groups/{$this->group->id}/webhooks/{$otherWebhookId}/toggle", ['is_active' => true])
            ->assertNotFound();
    }

    public function test_registration_rejects_unwired_events_and_encrypts_supported_secret(): void
    {
        $this->apiPost("/v2/groups/{$this->group->id}/webhooks", [
            'url' => 'https://8.8.8.8/group-hook',
            'events' => ['group.updated'],
        ])->assertStatus(422);

        $this->apiPost("/v2/groups/{$this->group->id}/webhooks", [
            'url' => 'https://8.8.8.8/group-hook',
            'events' => [GroupWebhookService::EVENT_FILE_UPLOADED],
            'secret' => 'signed-secret',
        ])->assertCreated();

        $webhook = DB::table('group_webhooks')
            ->where('group_id', $this->group->id)
            ->latest('id')
            ->first();
        self::assertNotNull($webhook);
        self::assertNotSame('signed-secret', $webhook->secret);
        self::assertSame('signed-secret', Crypt::decryptString((string) $webhook->secret));
    }

    public function test_signature_matches_exact_body_and_unsupported_fire_is_ignored(): void
    {
        $webhookId = $this->insertWebhook(secret: 'hmac-secret');
        GroupWebhookService::fire((int) $this->group->id, 'group.updated', ['ignored' => true]);
        self::assertSame(0, DB::table('group_webhook_deliveries')->where('webhook_id', $webhookId)->count());

        $deliveryId = $this->fireAndGetDelivery($webhookId, ['file_id' => 7]);
        Http::fake(static function ($request) {
            $signature = $request->header('X-Webhook-Signature')[0] ?? null;
            self::assertSame(hash_hmac('sha256', $request->body(), 'hmac-secret'), $signature);

            return Http::response('ok', 200);
        });

        self::assertSame('delivered', GroupWebhookService::deliver($deliveryId, $this->testTenantId));
    }

    public function test_membership_mutation_rolls_back_when_its_outbox_insert_fails(): void
    {
        $webhookId = $this->insertWebhook(events: [GroupWebhookService::EVENT_MEMBER_JOINED]);
        $joiningUser = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        TenantContext::setById($this->testTenantId);
        $this->forceNextOutboxUuidCollision($webhookId, GroupWebhookService::EVENT_MEMBER_JOINED);

        try {
            GroupService::join((int) $this->group->id, (int) $joiningUser->id);
            self::fail('The forced outbox primary-key collision must abort membership activation.');
        } catch (Throwable) {
            // Expected: action row and outbox append share one transaction.
        } finally {
            Str::createUuidsNormally();
        }

        self::assertFalse(DB::table('group_members')
            ->where('group_id', $this->group->id)
            ->where('user_id', $joiningUser->id)
            ->exists());
    }

    public function test_discussion_reply_rolls_back_when_its_outbox_insert_fails(): void
    {
        $discussion = GroupService::createDiscussion((int) $this->group->id, (int) $this->owner->id, [
            'title' => 'Outbox rollback discussion',
            'content' => 'Root post remains, failed reply does not.',
        ]);
        self::assertIsArray($discussion);
        $discussionId = (int) $discussion['id'];
        $webhookId = $this->insertWebhook(events: [GroupWebhookService::EVENT_POST_CREATED]);
        $this->forceNextOutboxUuidCollision($webhookId, GroupWebhookService::EVENT_POST_CREATED);

        try {
            GroupService::postToDiscussion(
                (int) $this->group->id,
                $discussionId,
                (int) $this->owner->id,
                ['content' => 'This reply must roll back.'],
            );
            self::fail('The forced outbox primary-key collision must abort the reply.');
        } catch (Throwable) {
            // Expected.
        } finally {
            Str::createUuidsNormally();
        }

        self::assertSame(1, DB::table('group_posts')->where('discussion_id', $discussionId)->count());
    }

    public function test_file_row_and_private_bytes_are_compensated_when_outbox_insert_fails(): void
    {
        Storage::fake('local');
        $webhookId = $this->insertWebhook(events: [GroupWebhookService::EVENT_FILE_UPLOADED]);
        $this->forceNextOutboxUuidCollision($webhookId, GroupWebhookService::EVENT_FILE_UPLOADED);
        $service = new GroupFileService();

        try {
            $service->upload((int) $this->group->id, (int) $this->owner->id, [
                'file' => UploadedFile::fake()->createWithContent('outbox.txt', 'rollback bytes'),
            ]);
            self::fail('The forced outbox primary-key collision must abort the file row.');
        } catch (Throwable) {
            // Expected.
        } finally {
            Str::createUuidsNormally();
        }

        self::assertFalse(DB::table('group_files')
            ->where('group_id', $this->group->id)
            ->where('file_name', 'outbox.txt')
            ->exists());
        self::assertSame([], Storage::disk('local')->allFiles(
            "groups/{$this->testTenantId}/{$this->group->id}",
        ));
    }

    private function fireAndGetDelivery(int $webhookId, array $payload = []): string
    {
        TenantContext::setById($this->testTenantId);
        DB::table('group_webhooks')
            ->where('tenant_id', $this->testTenantId)
            ->where('group_id', $this->group->id)
            ->where('id', '!=', $webhookId)
            ->update(['is_active' => false]);
        $existingDeliveryIds = DB::table('group_webhook_deliveries')
            ->where('webhook_id', $webhookId)
            ->pluck('id')
            ->all();
        GroupWebhookService::fire(
            (int) $this->group->id,
            GroupWebhookService::EVENT_FILE_UPLOADED,
            $payload + ['file_id' => random_int(1000, 9999)],
        );

        $query = DB::table('group_webhook_deliveries')->where('webhook_id', $webhookId);
        if ($existingDeliveryIds !== []) {
            $query->whereNotIn('id', $existingDeliveryIds);
        }

        return (string) $query->value('id');
    }

    /** @param list<string> $events */
    private function insertWebhook(
        array $events = [GroupWebhookService::EVENT_FILE_UPLOADED],
        string $url = 'https://8.8.8.8/group-hook',
        int $failureCount = 0,
        ?string $secret = null,
        ?int $groupId = null,
    ): int {
        return (int) DB::table('group_webhooks')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'group_id' => $groupId ?? (int) $this->group->id,
            'url' => $url,
            'events' => json_encode($events, JSON_THROW_ON_ERROR),
            'secret' => $secret !== null ? Crypt::encryptString($secret) : null,
            'is_active' => true,
            'failure_count' => $failureCount,
            'disabled_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function forceNextOutboxUuidCollision(int $webhookId, string $event): void
    {
        $uuid = '57a20370-9bbd-47c7-9822-851033b1a028';
        DB::table('group_webhook_deliveries')->insert([
            'id' => $uuid,
            'tenant_id' => $this->testTenantId,
            'group_id' => $this->group->id,
            'webhook_id' => $webhookId,
            'event' => $event,
            'payload' => json_encode([
                'event' => $event,
                'group_id' => $this->group->id,
                'tenant_id' => $this->testTenantId,
                'timestamp' => now()->toIso8601String(),
                'data' => ['collision_fixture' => true],
            ], JSON_THROW_ON_ERROR),
            'status' => 'delivered',
            'attempt_count' => 1,
            'available_at' => now(),
            'delivered_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Str::createUuidsUsing(static fn () => Uuid::fromString($uuid));
    }
}
