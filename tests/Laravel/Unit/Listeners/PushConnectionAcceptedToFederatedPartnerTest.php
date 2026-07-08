<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\ConnectionAccepted;
use App\Listeners\PushConnectionAcceptedToFederatedPartner;
use App\Models\Connection;
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
 * PushConnectionAcceptedToFederatedPartnerTest
 *
 * Tenant 99672 is used exclusively by this file. All writes roll back.
 *
 * The listener only pushes to partners that have `allow_connections = 1` AND
 * have a matching `federated_identities` row for one of the two participants.
 */
class PushConnectionAcceptedToFederatedPartnerTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99672;
    private const PARTNER_BASE_URL = 'https://93.184.216.34';
    private const TEST_SIGNING_SECRET = 'conn-test-signing-secret-abcdefgh';

    private FederationFeatureService $featureService;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        DB::table('tenants')->insert([
            'id'         => self::TENANT_ID,
            'name'       => 'Test Connection Federation Tenant',
            'slug'       => 'test-conn-fed-' . self::TENANT_ID,
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

    private function insertPartner(bool $allowConnections = true, string $signingSecret = self::TEST_SIGNING_SECRET): int
    {
        static $counter = 0;
        $counter++;
        $uniqueUrl = self::PARTNER_BASE_URL . '/c' . $counter . self::TENANT_ID;
        $encryptedSecret = Crypt::encryptString($signingSecret);

        return (int) DB::table('federation_external_partners')->insertGetId([
            'tenant_id'         => self::TENANT_ID,
            'name'              => 'Conn Test Partner ' . $counter,
            'base_url'          => $uniqueUrl,
            'api_path'          => '/api/v1/federation',
            'auth_method'       => 'hmac',
            'signing_secret'    => $encryptedSecret,
            'protocol_type'     => 'nexus',
            'status'            => 'active',
            'allow_connections' => $allowConnections ? 1 : 0,
            'created_at'        => now(),
        ]);
    }

    private function insertUser(): int
    {
        return (int) DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Conn User ' . uniqid(),
            'email'      => 'connuser' . uniqid() . '@test.local',
            'password'   => 'hashed',
            'created_at' => now(),
        ]);
    }

    private function insertFederatedIdentity(int $localUserId, int $partnerId, string $externalId): void
    {
        DB::table('federated_identities')->insert([
            'tenant_id'        => self::TENANT_ID,
            'local_user_id'    => $localUserId,
            'partner_id'       => $partnerId,
            'external_user_id' => $externalId,
            'created_at'       => now(),
        ]);
    }

    /**
     * Build a ConnectionAccepted event using Eloquent User objects and a
     * Connection model with minimal required fields set as dynamic properties.
     */
    private function makeEvent(int $requesterId, int $receiverId): ConnectionAccepted
    {
        $connId = (int) DB::table('connections')->insertGetId([
            'tenant_id'    => self::TENANT_ID,
            'requester_id' => $requesterId,
            'receiver_id'  => $receiverId,
            'status'       => 'accepted',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $connection = Connection::find($connId);
        $requester  = User::find($requesterId);
        $acceptor   = User::find($receiverId);

        return new ConnectionAccepted($connection, $requester, $acceptor, self::TENANT_ID);
    }

    private function makeListener(): PushConnectionAcceptedToFederatedPartner
    {
        return new PushConnectionAcceptedToFederatedPartner($this->featureService);
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

    public function test_nothing_sent_when_no_federated_identities(): void
    {
        Http::fake();

        $requesterId = $this->insertUser();
        $receiverId  = $this->insertUser();
        // No federated_identities rows — purely local connection.
        $event = $this->makeEvent($requesterId, $receiverId);

        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    public function test_nothing_sent_when_partner_does_not_allow_connections(): void
    {
        Http::fake();

        $partnerId   = $this->insertPartner(allowConnections: false);
        $requesterId = $this->insertUser();
        $receiverId  = $this->insertUser();
        $this->insertFederatedIdentity($requesterId, $partnerId, 'ext-rq-noconn');

        $event = $this->makeEvent($requesterId, $receiverId);
        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    public function test_posts_to_partner_when_requester_is_federated_and_partner_allows_connections(): void
    {
        $partnerId   = $this->insertPartner(allowConnections: true);
        $requesterId = $this->insertUser();
        $receiverId  = $this->insertUser();
        $this->insertFederatedIdentity($requesterId, $partnerId, 'ext-rq-ok');

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = $this->makeEvent($requesterId, $receiverId);
        $this->makeListener()->handle($event);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/connections')
                && $request->method() === 'POST';
        });
    }

    public function test_payload_contains_correct_fields(): void
    {
        $partnerId   = $this->insertPartner(allowConnections: true);
        $requesterId = $this->insertUser();
        $receiverId  = $this->insertUser();
        $this->insertFederatedIdentity($requesterId, $partnerId, 'ext-rq-payload');

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = $this->makeEvent($requesterId, $receiverId);
        $this->makeListener()->handle($event);

        Http::assertSent(function ($request) use ($requesterId, $receiverId) {
            $body = $request->data();
            return ($body['action'] ?? '') === 'accepted'
                && isset($body['requester_id'])
                && (int) $body['requester_id'] === $requesterId
                && isset($body['receiver_id'])
                && (int) $body['receiver_id'] === $receiverId
                && isset($body['external_user_id'])
                && $body['external_user_id'] === 'ext-rq-payload'
                && isset($body['tenant_id'])
                && (int) $body['tenant_id'] === self::TENANT_ID
                && isset($body['idempotency_key'])
                && str_contains($body['idempotency_key'], 'connection:' . self::TENANT_ID);
        });
    }

    public function test_sends_hmac_signature_header(): void
    {
        $secret      = 'unique-conn-signing-secret-auth-test';
        $partnerId   = $this->insertPartner(allowConnections: true, signingSecret: $secret);
        $requesterId = $this->insertUser();
        $receiverId  = $this->insertUser();
        $this->insertFederatedIdentity($requesterId, $partnerId, 'ext-rq-auth');

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = $this->makeEvent($requesterId, $receiverId);
        $this->makeListener()->handle($event);

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Federation-Signature')
                && $request->hasHeader('X-Federation-Timestamp')
                && $request->hasHeader('X-Federation-Nonce');
        });
    }

    public function test_pushes_to_multiple_partners_when_both_participants_federated(): void
    {
        $partner1Id  = $this->insertPartner(allowConnections: true, signingSecret: 'conn-secret-multi-1-abcdefgh');
        $partner2Id  = $this->insertPartner(allowConnections: true, signingSecret: 'conn-secret-multi-2-abcdefgh');
        $requesterId = $this->insertUser();
        $receiverId  = $this->insertUser();

        // Both participants are federated to different partners.
        $this->insertFederatedIdentity($requesterId, $partner1Id, 'ext-rq-multi');
        $this->insertFederatedIdentity($receiverId, $partner2Id, 'ext-rv-multi');

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = $this->makeEvent($requesterId, $receiverId);
        $this->makeListener()->handle($event);

        Http::assertSentCount(2);
    }

    public function test_skips_when_federation_feature_disabled_for_tenant(): void
    {
        DB::table('tenants')->where('id', self::TENANT_ID)->update([
            'features' => json_encode(['federation' => false]),
        ]);
        TenantContext::setById(self::TENANT_ID);

        Http::fake();

        $partnerId   = $this->insertPartner(allowConnections: true);
        $requesterId = $this->insertUser();
        $receiverId  = $this->insertUser();
        $this->insertFederatedIdentity($requesterId, $partnerId, 'ext-rq-fdisabled');

        $event = $this->makeEvent($requesterId, $receiverId);
        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    public function test_throws_on_5xx_partner_response_for_retry(): void
    {
        $partnerId   = $this->insertPartner(allowConnections: true);
        $requesterId = $this->insertUser();
        $receiverId  = $this->insertUser();
        $this->insertFederatedIdentity($requesterId, $partnerId, 'ext-rq-5xx');

        Http::fake(['*' => Http::response(['error' => 'server error'], 503)]);

        $this->expectException(\RuntimeException::class);

        $event = $this->makeEvent($requesterId, $receiverId);
        $this->makeListener()->handle($event);
    }

    public function test_does_not_push_to_federated_identity_belonging_to_another_tenant(): void
    {
        // Regression (audit L2): FederatedIdentity is NOT tenant-auto-scoped, so
        // the listener MUST filter tenant_id. A same-local_user_id identity row
        // scoped to a DIFFERENT tenant must never receive this tenant's push.
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $partnerId   = $this->insertPartner(allowConnections: true);
        $requesterId = $this->insertUser();
        $receiverId  = $this->insertUser();

        // Identity row exists for the requester but under a FOREIGN tenant.
        DB::table('federated_identities')->insert([
            'tenant_id'        => self::TENANT_ID + 1,
            'local_user_id'    => $requesterId,
            'partner_id'       => $partnerId,
            'external_user_id' => 'ext-foreign-tenant',
            'created_at'       => now(),
        ]);

        $event = $this->makeEvent($requesterId, $receiverId);
        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    public function test_tries_is_three(): void
    {
        $this->assertSame(3, $this->makeListener()->tries);
    }

    public function test_nothing_sent_when_participant_ids_are_both_zero(): void
    {
        // Construct a Connection model with no requester_id/receiver_id set.
        Http::fake();

        $connection = new Connection();
        $connection->id           = 99;
        $connection->requester_id = 0;
        $connection->receiver_id  = 0;
        $connection->tenant_id    = self::TENANT_ID;

        $requesterId = $this->insertUser();
        $receiverId  = $this->insertUser();
        $requester   = User::find($requesterId);
        $acceptor    = User::find($receiverId);

        $event = new ConnectionAccepted($connection, $requester, $acceptor, self::TENANT_ID);
        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }
}
