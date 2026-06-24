<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\CommunityEventCreated;
use App\Events\CommunityEventUpdated;
use App\Listeners\PushCommunityEventToFederatedPartners;
use App\Models\Event as CommunityEventModel;
use App\Services\FederationAuditService;
use App\Services\FederationFeatureService;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * PushCommunityEventToFederatedPartnersTest
 *
 * Verifies that PushCommunityEventToFederatedPartners::handle() broadcasts a
 * community event (action='created'/'updated') to every active partner that has
 * allow_events=1, respects the federated_visibility guard, and sends nothing
 * when prerequisites are not met.
 *
 * Unique tenant id: 99679 — isolated from all other listener test files.
 *
 * NOTE on Crypt in Docker dev environment:
 * The Docker compose.yml sets APP_KEY to a placeholder value that is not a
 * valid AES-256 key. We swap in a fresh Encrypter with a known-good random key
 * in setUp() so that Crypt::encryptString / Crypt::decryptString work correctly,
 * and we store partner api_key values encrypted with that same key.
 */
class PushCommunityEventToFederatedPartnersTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99679;
    private const PARTNER_IP_BASE = '93.184.216.34';
    private const PARTNER_API_PATH = '/api/v1/federation';

    private PushCommunityEventToFederatedPartners $listener;
    private int $hostUserId;
    private int $partnerId;
    private int $partnerCounter = 0;
    /** Encrypted api_key value generated with the test-safe Encrypter. */
    private string $encryptedApiKey;

    protected function setUp(): void
    {
        parent::setUp();

        // Swap the encrypter singleton — Docker compose sets an invalid APP_KEY so we
        // replace the singleton with a fresh instance using a valid random key.
        // This ensures Crypt::encryptString/decryptString work correctly in tests.
        $safeKey = random_bytes(32);
        $this->app->forgetInstance('encrypter');
        $this->app->instance('encrypter', new Encrypter($safeKey, 'AES-256-CBC'));
        \App\Services\FederationExternalApiClient::clearAdapterCache();

        $this->encryptedApiKey = Crypt::encryptString('test-api-key');

        Queue::fake();

        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'              => 'Events Fed Test Tenant',
                'slug'              => 'events-fed-test-' . self::TENANT_ID,
                'domain'            => null,
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        TenantContext::setById(self::TENANT_ID);

        $this->enableFederationForTenant(self::TENANT_ID);

        $this->hostUserId = $this->insertUser(self::TENANT_ID);
        $this->partnerId  = $this->insertPartner(self::TENANT_ID);

        $auditSvc   = $this->createMock(FederationAuditService::class);
        $featureSvc = new FederationFeatureService($auditSvc);
        $this->listener = new PushCommunityEventToFederatedPartners($featureSvc);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function enableFederationForTenant(int $tenantId): void
    {
        DB::table('federation_system_control')->updateOrInsert(
            ['id' => 1],
            [
                'federation_enabled'                => 1,
                'whitelist_mode_enabled'            => 0,
                'emergency_lockdown_active'         => 0,
                'max_federation_level'              => 4,
                'cross_tenant_profiles_enabled'     => 1,
                'cross_tenant_messaging_enabled'    => 1,
                'cross_tenant_transactions_enabled' => 1,
                'cross_tenant_listings_enabled'     => 1,
                'cross_tenant_events_enabled'       => 1,
                'cross_tenant_groups_enabled'       => 1,
                'created_at'                        => now(),
            ]
        );

        DB::table('federation_tenant_features')->updateOrInsert(
            ['tenant_id' => $tenantId, 'feature_key' => 'tenant_federation_enabled'],
            ['is_enabled' => 1, 'updated_at' => now()]
        );
    }

    private function nextPartnerBase(): string
    {
        $port = 9101 + ($this->partnerCounter++);
        return 'https://' . self::PARTNER_IP_BASE . ':' . $port;
    }

    private function insertPartner(int $tenantId, string $status = 'active', bool $allowEvents = true): int
    {
        $base = $this->nextPartnerBase();
        return (int) DB::table('federation_external_partners')->insertGetId([
            'tenant_id'     => $tenantId,
            'name'          => 'Events Partner ' . uniqid(),
            'base_url'      => $base,
            'api_path'      => self::PARTNER_API_PATH,
            'auth_method'   => 'api_key',
            'api_key'       => $this->encryptedApiKey,
            'protocol_type' => 'nexus',
            'status'        => $status,
            'allow_events'  => $allowEvents ? 1 : 0,
            'created_at'    => now(),
        ]);
    }

    private function insertUser(int $tenantId): int
    {
        return (int) DB::table('users')->insertGetId([
            'tenant_id'  => $tenantId,
            'name'       => 'Event Host ' . uniqid(),
            'email'      => 'host-' . uniqid() . '@example.com',
            'status'     => 'active',
            'created_at' => now(),
        ]);
    }

    /** Insert a community event row and return it as an Eloquent model. */
    private function insertCommunityEvent(string $visibility = 'listed'): CommunityEventModel
    {
        $id = (int) DB::table('events')->insertGetId([
            'tenant_id'            => self::TENANT_ID,
            'user_id'              => $this->hostUserId,
            'title'                => 'Test Fed Event ' . uniqid(),
            'description'          => 'A federation test event',
            'location'             => 'Online',
            'start_time'           => now()->addHour(),
            'end_time'             => now()->addHours(2),
            'is_online'            => 1,
            'federated_visibility' => $visibility,
            'created_at'           => now(),
        ]);

        /** @var CommunityEventModel $model */
        $model = CommunityEventModel::withoutGlobalScopes()->find($id);

        return $model;
    }

    private function partnerEventUrlWildcard(): string
    {
        // NexusAdapter::mapEndpoint('events') = '/events'
        return 'https://' . self::PARTNER_IP_BASE . '*/events';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Happy-path: event payload sent on CommunityEventCreated
    // ─────────────────────────────────────────────────────────────────────────

    public function test_handle_sends_created_event_to_partner(): void
    {
        Http::fake([
            $this->partnerEventUrlWildcard() => Http::response(['success' => true], 200),
        ]);

        $model = $this->insertCommunityEvent('listed');
        $event = new CommunityEventCreated($model, self::TENANT_ID);

        $this->listener->handle($event);

        Http::assertSent(function (\Illuminate\Http\Client\Request $req): bool {
            $body = json_decode($req->body(), true);
            return str_contains($req->url(), '/events')
                && ($body['action'] ?? '') === 'created';
        });
    }

    public function test_handle_sends_updated_action_for_community_event_updated(): void
    {
        Http::fake([
            $this->partnerEventUrlWildcard() => Http::response(['success' => true], 200),
        ]);

        // 'listed' is a valid enum value for federated_visibility (schema: none|listed|joinable).
        $model = $this->insertCommunityEvent('listed');
        $event = new CommunityEventUpdated($model, self::TENANT_ID);

        $this->listener->handle($event);

        Http::assertSent(function (\Illuminate\Http\Client\Request $req): bool {
            $body = json_decode($req->body(), true);
            return ($body['action'] ?? '') === 'updated';
        });
    }

    public function test_handle_sends_to_every_active_partner_with_allow_events(): void
    {
        $partner2 = $this->insertPartner(self::TENANT_ID, 'active', true);

        Http::fake([
            $this->partnerEventUrlWildcard() => Http::response(['success' => true], 200),
        ]);

        $model = $this->insertCommunityEvent('listed');
        $event = new CommunityEventCreated($model, self::TENANT_ID);

        $this->listener->handle($event);

        // Two active partners with allow_events=1 → two HTTP calls.
        Http::assertSentCount(2);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Visibility guard
    // ─────────────────────────────────────────────────────────────────────────

    public function test_handle_sends_nothing_for_private_visibility(): void
    {
        Http::fake();

        $model = $this->insertCommunityEvent('none');
        $event = new CommunityEventCreated($model, self::TENANT_ID);

        $this->listener->handle($event);

        Http::assertNothingSent();
    }

    public function test_handle_sends_nothing_for_joinable_visibility(): void
    {
        // The listener allows ['listed', 'public'] but the DB enum only stores
        // 'none'|'listed'|'joinable'. 'joinable' is NOT in the listener's allowlist,
        // so events with this visibility are NOT pushed to partners.
        // SOURCE QUIRK: the listener includes 'public' which the DB enum cannot store
        // (MariaDB converts invalid enum inserts to empty string), making 'public'
        // effectively dead code in the listener. Tests should document actual behaviour.
        Http::fake();

        $model = $this->insertCommunityEvent('joinable');
        $event = new CommunityEventCreated($model, self::TENANT_ID);

        $this->listener->handle($event);

        Http::assertNothingSent();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Partner flag guard
    // ─────────────────────────────────────────────────────────────────────────

    public function test_handle_sends_nothing_when_partner_has_allow_events_disabled(): void
    {
        // Remove the default partner and insert one without allow_events.
        DB::table('federation_external_partners')->where('id', $this->partnerId)->delete();
        $this->insertPartner(self::TENANT_ID, 'active', false);

        Http::fake();

        $model = $this->insertCommunityEvent('listed');
        $event = new CommunityEventCreated($model, self::TENANT_ID);

        $this->listener->handle($event);

        Http::assertNothingSent();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Federation feature guards
    // ─────────────────────────────────────────────────────────────────────────

    public function test_handle_sends_nothing_when_federation_globally_disabled(): void
    {
        Http::fake();

        DB::table('federation_system_control')
            ->where('id', 1)
            ->update(['federation_enabled' => 0]);

        $model = $this->insertCommunityEvent('listed');
        $event = new CommunityEventCreated($model, self::TENANT_ID);

        $this->listener->handle($event);

        Http::assertNothingSent();
    }

    public function test_handle_sends_nothing_for_unknown_tenant(): void
    {
        Http::fake();

        $model = $this->insertCommunityEvent('listed');
        // Fire with a non-existent tenant id
        $event = new CommunityEventCreated($model, 99999999);

        $this->listener->handle($event);

        Http::assertNothingSent();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Listener configuration
    // ─────────────────────────────────────────────────────────────────────────

    public function test_listener_implements_should_queue(): void
    {
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $this->listener);
    }

    public function test_listener_is_on_federation_queue(): void
    {
        $this->assertSame('federation', $this->listener->queue);
    }

    public function test_listener_does_not_throw_on_partner_500(): void
    {
        // Per listener source: pushToPartner() has its own try/catch so a partner
        // 500 should NOT propagate and crash the listener.
        Http::fake([
            $this->partnerEventUrlWildcard() => Http::response('Gateway Error', 500),
        ]);

        $model = $this->insertCommunityEvent('listed');
        $event = new CommunityEventCreated($model, self::TENANT_ID);

        // Must not throw — listener swallows per-partner failures intentionally.
        $this->listener->handle($event);

        Http::assertSent(fn ($req) => str_contains($req->url(), '/events'));
    }
}
