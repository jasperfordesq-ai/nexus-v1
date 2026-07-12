<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Enums\GroupStatus;
use App\Core\TenantContext;
use App\Events\GroupCreated;
use App\Events\GroupUpdated;
use App\Listeners\PushGroupToFederatedPartners;
use App\Models\Group;
use App\Services\FederationExternalApiClient;
use App\Services\FederationFeatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * PushGroupToFederatedPartnersTest
 *
 * Covers the outbound federation push for group create/update events.
 * Uses Http::fake() to intercept all outbound calls; real DB rows
 * (rolled back via DatabaseTransactions) for partners, tenants, etc.
 *
 * Unique tenant ID: 99680
 */
class PushGroupToFederatedPartnersTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99680;

    /** A real public IP that passes OutboundUrlGuard::isPublicIp() — example.com */
    private const PARTNER_BASE_URL = 'https://93.184.216.34';

    private int $partnerId;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // The Docker .env has an invalid APP_KEY; phpunit.xml sets the correct one via
        // <env force="true"> but Laravel's config cache is populated before phpunit's
        // env override takes effect. Force it here so Crypt::decryptString() can
        // initialize the Encrypter singleton correctly.
        config(['app.key' => 'base64:HfQEDtbtr90JIXhsaAhSFWnzIo1f31VZ2e5qLqKKnls=']);
        app()->forgetInstance('encrypter');

        // Ensure the tenant row exists (setById needs it).
        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Test Tenant 99680',
            'slug'       => 'test-tenant-99680',
            'domain'     => 'test-tenant-99680.example.test',
            'created_at' => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);

        // Ensure federation_system_control row exists with federation enabled.
        DB::table('federation_system_control')->insertOrIgnore([
            'id'                               => 1,
            'federation_enabled'               => 1,
            'whitelist_mode_enabled'           => 0,
            'emergency_lockdown_active'        => 0,
            'max_federation_level'             => 4,
            'cross_tenant_profiles_enabled'    => 1,
            'cross_tenant_messaging_enabled'   => 1,
            'cross_tenant_transactions_enabled'=> 1,
            'cross_tenant_listings_enabled'    => 1,
            'cross_tenant_events_enabled'      => 1,
            'cross_tenant_groups_enabled'      => 1,
            'created_at'                       => now(),
        ]);

        // Insert a partner with allow_groups=1, status=active.
        // Store api_key as plaintext — decryptCredential() catches DecryptException when
        // Crypt::decryptString() rejects a non-encrypted value, then returns the raw string.
        $this->partnerId = (int) DB::table('federation_external_partners')->insertGetId([
            'tenant_id'          => self::TENANT_ID,
            'name'               => 'Test Partner 99680',
            'base_url'           => self::PARTNER_BASE_URL,
            'api_path'           => '/api/v2/federation',
            'api_key'            => 'test-api-key-99680',
            'auth_method'        => 'api_key',
            'protocol_type'      => 'nexus',
            'status'             => 'active',
            'allow_member_search'=> 1,
            'allow_listing_search'=> 1,
            'allow_messaging'    => 1,
            'allow_transactions' => 1,
            'allow_events'       => 0,
            'allow_groups'       => 1,
            'allow_connections'  => 0,
            'allow_volunteering' => 0,
            'allow_member_sync'  => 0,
            'error_count'        => 0,
            'created_at'         => now(),
        ]);

        // Clear the adapter cache so stale entries don't bleed between tests.
        FederationExternalApiClient::clearAdapterCache();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeListener(): PushGroupToFederatedPartners
    {
        return new PushGroupToFederatedPartners(
            new FederationFeatureService(
                new \App\Services\FederationAuditService()
            )
        );
    }

    /**
     * Build a Group model stub without hitting the DB.
     */
    private function makeGroup(
        int $id = 1001,
        string $visibility = 'listed',
        ?string $name = 'Federated Group'
    ): Group {
        $group = new Group();
        $group->id                   = $id;
        $group->name                 = $name;
        $group->description          = 'A test group';
        $group->visibility           = 'public';
        $group->federated_visibility = $visibility;
        $group->owner_id             = 1;
        $group->tenant_id            = self::TENANT_ID;
        $group->status               = GroupStatus::Active;
        $group->created_at           = now();
        return $group;
    }

    /** Activate the tenant-level federation feature in the DB for our tenant. */
    private function enableTenantFederation(): void
    {
        DB::table('federation_tenant_features')->insertOrIgnore([
            'tenant_id'   => self::TENANT_ID,
            'feature_key' => 'tenant_federation_enabled',
            'is_enabled'  => 1,
            'updated_at'  => now(),
            'updated_by'  => null,
        ]);
    }

    /**
     * Enable the 'federation' feature in the tenant's features JSON and
     * reload TenantContext so hasFeature() sees the updated value.
     */
    private function enableTenantFeatureFlag(): void
    {
        $tenant = DB::table('tenants')->where('id', self::TENANT_ID)->first();
        $features = $tenant && $tenant->features ? json_decode($tenant->features, true) : [];
        $features['federation'] = true;
        DB::table('tenants')
            ->where('id', self::TENANT_ID)
            ->update(['features' => json_encode($features)]);
        // Re-load TenantContext so the in-memory cache reflects the updated features.
        TenantContext::setById(self::TENANT_ID);
    }

    // ── Structural ────────────────────────────────────────────────────────────

    public function test_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, $this->makeListener());
    }

    public function test_queue_is_federation(): void
    {
        $listener = $this->makeListener();
        $this->assertSame('federation', $listener->queue);
    }

    // ── Happy path: GroupCreated ──────────────────────────────────────────────

    public function test_handle_group_created_pushes_to_partner_with_allow_groups(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $group = $this->makeGroup(id: 2001, visibility: 'listed');
        $event = new GroupCreated($group, self::TENANT_ID);

        $this->makeListener()->handle($event);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return $request->method() === 'POST'
                && str_contains($request->url(), self::PARTNER_BASE_URL)
                && str_contains($request->url(), '/groups')
                && ($body['action'] ?? '') === 'created'
                && ($body['id'] ?? 0) === 2001
                && ($body['tenant_id'] ?? 0) === self::TENANT_ID;
        });
    }

    // ── Happy path: GroupUpdated ──────────────────────────────────────────────

    public function test_handle_group_updated_sends_action_updated(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $group = $this->makeGroup(id: 2002, visibility: 'public');
        $event = new GroupUpdated($group, self::TENANT_ID);

        $this->makeListener()->handle($event);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return ($body['action'] ?? '') === 'updated'
                && ($body['id'] ?? 0) === 2002;
        });
    }

    public function test_pending_review_group_is_never_federated_on_creation(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $group = $this->makeGroup(id: 2010, visibility: 'listed');
        $group->status = GroupStatus::PendingReview;

        $this->makeListener()->handle(new GroupCreated($group, self::TENANT_ID));

        Http::assertNothingSent();
    }

    public function test_non_active_update_retracts_a_previously_federated_group(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $group = $this->makeGroup(id: 2011, visibility: 'none');
        $group->status = GroupStatus::Archived;

        $this->makeListener()->handle(new GroupUpdated($group, self::TENANT_ID));

        Http::assertSent(static function ($request): bool {
            $body = $request->data();

            return ($body['action'] ?? null) === 'deleted'
                && ($body['id'] ?? null) === 2011;
        });
    }

    // ── Visibility gate ───────────────────────────────────────────────────────

    public function test_handle_skips_push_when_federated_visibility_is_none(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $group = $this->makeGroup(id: 2003, visibility: 'none');
        $event = new GroupCreated($group, self::TENANT_ID);

        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    public function test_handle_skips_push_when_federated_visibility_is_joinable(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        // 'joinable' is a valid enum value for events but not in the groups check;
        // the listener only allows 'listed' or 'public' — so joinable is skipped.
        $group = $this->makeGroup(id: 2004, visibility: 'joinable');
        $event = new GroupCreated($group, self::TENANT_ID);

        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    // ── No partners gate ──────────────────────────────────────────────────────

    public function test_handle_sends_nothing_when_no_active_partner_with_allow_groups(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        // Deactivate the partner.
        DB::table('federation_external_partners')
            ->where('id', $this->partnerId)
            ->update(['status' => 'suspended']);

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $group = $this->makeGroup(id: 2005, visibility: 'listed');
        $event = new GroupCreated($group, self::TENANT_ID);

        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    // ── Federation feature gate ───────────────────────────────────────────────

    public function test_handle_skips_push_when_tenant_context_federation_feature_disabled(): void
    {
        // Explicitly disable 'federation' feature flag on the tenant.
        $tenant = DB::table('tenants')->where('id', self::TENANT_ID)->first();
        $features = $tenant && $tenant->features ? json_decode($tenant->features, true) : [];
        $features['federation'] = false;
        DB::table('tenants')
            ->where('id', self::TENANT_ID)
            ->update(['features' => json_encode($features)]);
        TenantContext::setById(self::TENANT_ID);

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $group = $this->makeGroup(id: 2006, visibility: 'listed');
        $event = new GroupCreated($group, self::TENANT_ID);

        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    // ── Payload structure ─────────────────────────────────────────────────────

    public function test_handle_payload_contains_required_group_fields(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $group = $this->makeGroup(id: 3001, visibility: 'listed', name: 'My Federated Group');
        $event = new GroupCreated($group, self::TENANT_ID);

        $this->makeListener()->handle($event);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return array_key_exists('action', $body)
                && array_key_exists('id', $body)
                && array_key_exists('external_id', $body)
                && array_key_exists('name', $body)
                && array_key_exists('tenant_id', $body)
                && $body['name'] === 'My Federated Group'
                && $body['external_id'] === '3001';
        });
    }

    // ── Authorization header is present ──────────────────────────────────────

    public function test_handle_sends_authorization_header_to_partner(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $group = $this->makeGroup(id: 3002, visibility: 'listed');
        $event = new GroupCreated($group, self::TENANT_ID);

        $this->makeListener()->handle($event);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization');
        });
    }

    // ── Partner rejection is swallowed (no exception) ─────────────────────────

    public function test_handle_continues_without_exception_when_partner_returns_failure(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        Http::fake(['*' => Http::response(['error' => 'Nope'], 500)]);

        $group = $this->makeGroup(id: 3003, visibility: 'listed');
        $event = new GroupCreated($group, self::TENANT_ID);

        // Must not throw.
        $this->makeListener()->handle($event);

        // The attempt was made.
        Http::assertSent(fn ($r) => str_contains($r->url(), self::PARTNER_BASE_URL));
    }

    // ── Unknown tenant → skips silently ──────────────────────────────────────

    public function test_handle_skips_when_tenant_id_does_not_exist(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $group = $this->makeGroup(id: 3004, visibility: 'listed');
        // Use a tenant ID that has no DB row.
        $event = new GroupCreated($group, 999999999);

        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }
}
