<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\MessageSent;
use App\Listeners\PushMessageToFederatedPartner;
use App\Models\Message;
use App\Models\User;
use App\Services\FederationAuditService;
use App\Services\FederationExternalApiClient;
use App\Services\FederationFeatureService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * PushMessageToFederatedPartnerTest
 *
 * Tenant 99673 is used exclusively by this file. All writes roll back.
 *
 * The listener resolves the partner from:
 *   1. $message->external_partner_id / $message->external_receiver_id (dynamic props)
 *   2. Fallback: federation_messages shadow row
 *
 * Both paths are tested here. We always set is_federated=1 on the message
 * (without it the listener returns early before any HTTP call).
 */
class PushMessageToFederatedPartnerTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99673;
    private const PARTNER_BASE_URL = 'https://93.184.216.34';
    private const TEST_SIGNING_SECRET = 'msg-test-signing-secret-abcdefgh';

    private FederationFeatureService $featureService;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        DB::table('tenants')->insert([
            'id'         => self::TENANT_ID,
            'name'       => 'Test Message Federation Tenant',
            'slug'       => 'test-msg-fed-' . self::TENANT_ID,
            'features'   => json_encode(['federation' => true]),
            'created_at' => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);

        DB::table('federation_system_control')->updateOrInsert(
            ['id' => 1],
            [
                'federation_enabled'                 => 1,
                'whitelist_mode_enabled'             => 0,
                'emergency_lockdown_active'          => 0,
                'cross_tenant_profiles_enabled'      => 1,
                'cross_tenant_messaging_enabled'     => 1,
                'cross_tenant_transactions_enabled'  => 1,
                'cross_tenant_listings_enabled'      => 1,
                'cross_tenant_events_enabled'        => 1,
                'cross_tenant_groups_enabled'        => 1,
                'max_federation_level'               => 4,
                'created_at'                         => now(),
            ]
        );

        DB::table('federation_tenant_features')->updateOrInsert(
            ['tenant_id' => self::TENANT_ID, 'feature_key' => FederationFeatureService::TENANT_FEDERATION_ENABLED],
            ['is_enabled' => 1, 'updated_at' => now()]
        );

        $this->featureService = new FederationFeatureService(new FederationAuditService());
        FederationExternalApiClient::clearAdapterCache();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function insertPartner(string $signingSecret = self::TEST_SIGNING_SECRET): int
    {
        static $counter = 0;
        $counter++;
        $uniqueUrl = self::PARTNER_BASE_URL . '/m' . $counter . self::TENANT_ID;
        $encryptedSecret = Crypt::encryptString($signingSecret);

        return (int) DB::table('federation_external_partners')->insertGetId([
            'tenant_id'       => self::TENANT_ID,
            'name'            => 'Msg Test Partner ' . $counter,
            'base_url'        => $uniqueUrl,
            'api_path'        => '/api/v1/federation',
            'auth_method'     => 'hmac',
            'signing_secret'  => $encryptedSecret,
            'protocol_type'   => 'nexus',
            'status'          => 'active',
            'allow_messaging' => 1,
            'created_at'      => now(),
        ]);
    }

    private function insertUser(): int
    {
        return (int) DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Msg User ' . uniqid(),
            'email'      => 'msguser' . uniqid() . '@test.local',
            'password'   => 'hashed',
            'created_at' => now(),
        ]);
    }

    /**
     * Insert a message into DB and return a Message Eloquent model.
     * Extra dynamic properties (is_federated, external_partner_id, etc.) are
     * set after retrieval because they are not DB columns.
     */
    private function insertMessage(
        int $senderId,
        int $receiverId,
        bool $isFederated = true
    ): Message {
        $id = (int) DB::table('messages')->insertGetId([
            'tenant_id'    => self::TENANT_ID,
            'sender_id'    => $senderId,
            'receiver_id'  => $receiverId,
            'body'         => 'Hello cross-community',
            'subject'      => 'Test federation message',
            'is_federated' => $isFederated ? 1 : 0,
            'created_at'   => now(),
        ]);

        return Message::withoutGlobalScopes()->find($id);
    }

    private function insertFederationMessageRow(
        int $referenceMessageId,
        int $senderId,
        int $partnerId,
        int $externalReceiverId
    ): void {
        DB::table('federation_messages')->insert([
            'sender_tenant_id'   => self::TENANT_ID,
            'sender_user_id'     => $senderId,
            'receiver_tenant_id' => self::TENANT_ID + 1, // remote tenant
            'receiver_user_id'   => $externalReceiverId,
            'body'               => 'Hello cross-community',
            'direction'          => 'outbound',
            'status'             => 'pending',
            'reference_message_id' => $referenceMessageId,
            'external_partner_id'  => $partnerId,
            'created_at'           => now(),
        ]);
    }

    private function makeListener(): PushMessageToFederatedPartner
    {
        return new PushMessageToFederatedPartner($this->featureService);
    }

    private function makeSender(): User
    {
        return User::find($this->insertUser());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_implements_should_queue(): void
    {
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $this->makeListener());
    }

    public function test_queue_is_federation_high(): void
    {
        $this->assertSame('federation-high', $this->makeListener()->queue);
    }

    public function test_nothing_sent_when_message_is_not_flagged_federated(): void
    {
        Http::fake();

        $senderId   = $this->insertUser();
        $receiverId = $this->insertUser();
        $message    = $this->insertMessage($senderId, $receiverId, isFederated: false);
        $sender     = User::find($senderId);

        $event = new MessageSent($message, $sender, conversationId: 1, tenantId: self::TENANT_ID);
        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    public function test_nothing_sent_when_no_partner_id_available(): void
    {
        // Message is_federated=1 but no external_partner_id on message and no
        // matching federation_messages shadow row.
        Http::fake();

        $senderId   = $this->insertUser();
        $receiverId = $this->insertUser();
        $message    = $this->insertMessage($senderId, $receiverId, isFederated: true);
        $sender     = User::find($senderId);
        // No dynamic props set — message->external_partner_id is null.

        $event = new MessageSent($message, $sender, conversationId: 1, tenantId: self::TENANT_ID);
        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    public function test_posts_to_partner_via_dynamic_message_props(): void
    {
        $partnerId  = $this->insertPartner();
        $senderId   = $this->insertUser();
        $receiverId = $this->insertUser();
        $message    = $this->insertMessage($senderId, $receiverId, isFederated: true);
        $sender     = User::find($senderId);

        // Set routing info as dynamic properties (not DB columns).
        $message->external_partner_id   = $partnerId;
        $message->external_receiver_id  = 'ext-rcv-dynamic-1';

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new MessageSent($message, $sender, conversationId: 42, tenantId: self::TENANT_ID);
        $this->makeListener()->handle($event);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/messages')
                && $request->method() === 'POST';
        });
    }

    public function test_payload_contains_required_fields_via_dynamic_props(): void
    {
        $partnerId   = $this->insertPartner();
        $senderId    = $this->insertUser();
        $receiverId  = $this->insertUser();
        $message     = $this->insertMessage($senderId, $receiverId, isFederated: true);
        $sender      = User::find($senderId);
        $extReceiver = 'ext-rcv-payload-check';

        $message->external_partner_id  = $partnerId;
        $message->external_receiver_id = $extReceiver;

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new MessageSent($message, $sender, conversationId: 55, tenantId: self::TENANT_ID);
        $this->makeListener()->handle($event);

        Http::assertSent(function ($request) use ($senderId, $extReceiver) {
            $body = $request->data();
            return isset($body['sender_id'])
                && (int) $body['sender_id'] === $senderId
                && isset($body['receiver_id'])
                && $body['receiver_id'] === $extReceiver
                && isset($body['sender_tenant_id'])
                && (int) $body['sender_tenant_id'] === self::TENANT_ID
                && isset($body['body'])
                // body is redacted in the raw request but the transformed payload
                // must still include it — Nexus adapter is passthrough.
                && isset($body['conversation_id'])
                && (int) $body['conversation_id'] === 55;
        });
    }

    public function test_falls_back_to_federation_messages_shadow_row(): void
    {
        $partnerId  = $this->insertPartner();
        $senderId   = $this->insertUser();
        $receiverId = $this->insertUser();
        $message    = $this->insertMessage($senderId, $receiverId, isFederated: true);
        $sender     = User::find($senderId);
        // Do NOT set external_partner_id on the message object.
        // Seed the federation_messages shadow row instead.
        $this->insertFederationMessageRow($message->id, $senderId, $partnerId, externalReceiverId: 777);

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new MessageSent($message, $sender, conversationId: 10, tenantId: self::TENANT_ID);
        $this->makeListener()->handle($event);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/messages')
                && $request->method() === 'POST';
        });
    }

    public function test_sends_hmac_signature_header(): void
    {
        $secret    = 'unique-msg-signing-secret-auth-xyz';
        $partnerId = $this->insertPartner($secret);
        $senderId  = $this->insertUser();
        $receiverId = $this->insertUser();
        $message   = $this->insertMessage($senderId, $receiverId, isFederated: true);
        $sender    = User::find($senderId);

        $message->external_partner_id  = $partnerId;
        $message->external_receiver_id = 'ext-rcv-auth';

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new MessageSent($message, $sender, conversationId: 1, tenantId: self::TENANT_ID);
        $this->makeListener()->handle($event);

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Federation-Signature')
                && $request->hasHeader('X-Federation-Timestamp')
                && $request->hasHeader('X-Federation-Nonce');
        });
    }

    public function test_skips_when_federation_feature_disabled_for_tenant(): void
    {
        DB::table('tenants')->where('id', self::TENANT_ID)->update([
            'features' => json_encode(['federation' => false]),
        ]);
        TenantContext::setById(self::TENANT_ID);

        Http::fake();

        $partnerId  = $this->insertPartner();
        $senderId   = $this->insertUser();
        $receiverId = $this->insertUser();
        $message    = $this->insertMessage($senderId, $receiverId, isFederated: true);
        $sender     = User::find($senderId);

        $message->external_partner_id  = $partnerId;
        $message->external_receiver_id = 'ext-rcv-fdisabled';

        $event = new MessageSent($message, $sender, conversationId: 1, tenantId: self::TENANT_ID);
        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    public function test_throws_on_retryable_5xx_from_partner(): void
    {
        $partnerId  = $this->insertPartner();
        $senderId   = $this->insertUser();
        $receiverId = $this->insertUser();
        $message    = $this->insertMessage($senderId, $receiverId, isFederated: true);
        $sender     = User::find($senderId);

        $message->external_partner_id  = $partnerId;
        $message->external_receiver_id = 'ext-rcv-5xx';

        Http::fake(['*' => Http::response(['error' => 'gateway timeout'], 503)]);

        $this->expectException(\RuntimeException::class);

        $event = new MessageSent($message, $sender, conversationId: 1, tenantId: self::TENANT_ID);
        $this->makeListener()->handle($event);
    }

    public function test_does_not_throw_on_4xx_partner_rejection(): void
    {
        // A 4xx response is non-retryable (terminal). The listener must not
        // throw for 4xx so the queue job is NOT retried.
        $partnerId  = $this->insertPartner();
        $senderId   = $this->insertUser();
        $receiverId = $this->insertUser();
        $message    = $this->insertMessage($senderId, $receiverId, isFederated: true);
        $sender     = User::find($senderId);

        $message->external_partner_id  = $partnerId;
        $message->external_receiver_id = 'ext-rcv-4xx';

        Http::fake(['*' => Http::response(['error' => 'not found'], 404)]);

        // Should complete without exception.
        $event = new MessageSent($message, $sender, conversationId: 1, tenantId: self::TENANT_ID);
        $this->makeListener()->handle($event);

        Http::assertSentCount(1);
    }

    public function test_skips_when_tenant_id_is_invalid(): void
    {
        Http::fake();

        $senderId   = $this->insertUser();
        $receiverId = $this->insertUser();
        $message    = $this->insertMessage($senderId, $receiverId, isFederated: true);
        $sender     = User::find($senderId);

        $message->external_partner_id  = 1;
        $message->external_receiver_id = 'ext-rcv-badtenant';

        // tenant 0 is invalid — setById(0) returns false.
        $event = new MessageSent($message, $sender, conversationId: 1, tenantId: 0);
        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    public function test_tries_is_three(): void
    {
        $this->assertSame(3, $this->makeListener()->tries);
    }
}
