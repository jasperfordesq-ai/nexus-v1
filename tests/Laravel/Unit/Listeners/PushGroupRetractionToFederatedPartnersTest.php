<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\GroupDeleted;
use App\Events\GroupMemberLeft;
use App\Listeners\PushGroupRetractionToFederatedPartners;
use App\Services\FederationExternalApiClient;
use App\Services\FederationFeatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * PushGroupRetractionToFederatedPartnersTest
 *
 * Covers the outbound federation retraction push for:
 *   - GroupDeleted   → action='deleted'
 *   - GroupMemberLeft → action='member_left'
 *
 * Uses Http::fake(); real DB rows rolled back via DatabaseTransactions.
 *
 * Unique tenant ID: 99682
 */
class PushGroupRetractionToFederatedPartnersTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99682;

    /** Public IP that passes OutboundUrlGuard::isPublicIp() — example.com */
    private const PARTNER_BASE_URL = 'https://93.184.216.34';

    private int $partnerId;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // Force correct APP_KEY so Crypt::decryptString() can initialize the
        // Encrypter singleton (Docker .env has an invalid key).
        config(['app.key' => 'base64:HfQEDtbtr90JIXhsaAhSFWnzIo1f31VZ2e5qLqKKnls=']);
        app()->forgetInstance('encrypter');

        // Ensure the tenant row exists.
        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Test Tenant 99682',
            'slug'       => 'test-tenant-99682',
            'domain'     => 'test-tenant-99682.example.test',
            'created_at' => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);

        // Ensure federation system control row exists with federation enabled.
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

        // Insert active partner with allow_groups=1.
        // Plaintext api_key — decryptCredential() catches DecryptException when
        // Crypt::decryptString() rejects the unencrypted value and falls back to plaintext.
        $this->partnerId = (int) DB::table('federation_external_partners')->insertGetId([
            'tenant_id'           => self::TENANT_ID,
            'name'                => 'Retraction Partner 99682',
            'base_url'            => self::PARTNER_BASE_URL,
            'api_path'            => '/api/v2/federation',
            'api_key'             => 'test-api-key-99682',
            'auth_method'         => 'api_key',
            'protocol_type'       => 'nexus',
            'status'              => 'active',
            'allow_member_search' => 1,
            'allow_listing_search'=> 1,
            'allow_messaging'     => 1,
            'allow_transactions'  => 1,
            'allow_events'        => 0,
            'allow_groups'        => 1,
            'allow_connections'   => 0,
            'allow_volunteering'  => 0,
            'allow_member_sync'   => 0,
            'error_count'         => 0,
            'created_at'          => now(),
        ]);

        FederationExternalApiClient::clearAdapterCache();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeListener(): PushGroupRetractionToFederatedPartners
    {
        return new PushGroupRetractionToFederatedPartners(
            new FederationFeatureService(
                new \App\Services\FederationAuditService()
            )
        );
    }

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

    private function enableTenantFeatureFlag(): void
    {
        $tenant   = DB::table('tenants')->where('id', self::TENANT_ID)->first();
        $features = $tenant && $tenant->features ? json_decode($tenant->features, true) : [];
        $features['federation'] = true;
        DB::table('tenants')
            ->where('id', self::TENANT_ID)
            ->update(['features' => json_encode($features)]);
        // Reload so hasFeature() sees the updated value.
        TenantContext::setById(self::TENANT_ID);
    }

    // ── Structural ────────────────────────────────────────────────────────────

    public function test_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, $this->makeListener());
    }

    public function test_queue_is_federation(): void
    {
        $this->assertSame('federation', $this->makeListener()->queue);
    }

    // ── GroupDeleted happy path ───────────────────────────────────────────────

    public function test_handle_group_deleted_sends_action_deleted_to_partner(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new GroupDeleted(
            groupId: 5001,
            tenantId: self::TENANT_ID,
            groupName: 'My Deleted Group',
        );

        $this->makeListener()->handle($event);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return $request->method() === 'POST'
                && str_contains($request->url(), self::PARTNER_BASE_URL)
                && str_contains($request->url(), '/groups')
                && ($body['action'] ?? '') === 'deleted'
                && ($body['id'] ?? 0) === 5001
                && ($body['tenant_id'] ?? 0) === self::TENANT_ID
                && ($body['name'] ?? '') === 'My Deleted Group';
        });
    }

    // ── GroupMemberLeft happy path ────────────────────────────────────────────

    public function test_handle_group_member_left_sends_action_member_left_to_partner(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new GroupMemberLeft(
            groupId: 5002,
            userId: 999,
            tenantId: self::TENANT_ID,
        );

        $this->makeListener()->handle($event);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return $request->method() === 'POST'
                && str_contains($request->url(), self::PARTNER_BASE_URL)
                && str_contains($request->url(), '/groups')
                && ($body['action'] ?? '') === 'member_left'
                && ($body['id'] ?? 0) === 5002
                && ($body['user_id'] ?? 0) === 999
                && ($body['tenant_id'] ?? 0) === self::TENANT_ID;
        });
    }

    // ── Authorization header ──────────────────────────────────────────────────

    public function test_handle_sends_authorization_header_for_group_deleted(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new GroupDeleted(5003, self::TENANT_ID, 'Auth Header Group');
        $this->makeListener()->handle($event);

        Http::assertSent(fn ($r) => $r->hasHeader('Authorization'));
    }

    // ── No partners gate ──────────────────────────────────────────────────────

    public function test_handle_sends_nothing_when_no_active_partner_with_allow_groups(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        DB::table('federation_external_partners')
            ->where('id', $this->partnerId)
            ->update(['status' => 'suspended']);

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new GroupDeleted(5004, self::TENANT_ID, 'No Partner Group');
        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    public function test_handle_sends_nothing_when_partner_does_not_have_allow_groups(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        DB::table('federation_external_partners')
            ->where('id', $this->partnerId)
            ->update(['allow_groups' => 0]);

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new GroupMemberLeft(5005, 888, self::TENANT_ID);
        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    // ── Feature flag gates ────────────────────────────────────────────────────

    public function test_handle_skips_push_when_tenant_feature_flag_disabled(): void
    {
        $tenant   = DB::table('tenants')->where('id', self::TENANT_ID)->first();
        $features = $tenant && $tenant->features ? json_decode($tenant->features, true) : [];
        $features['federation'] = false;
        DB::table('tenants')
            ->where('id', self::TENANT_ID)
            ->update(['features' => json_encode($features)]);
        TenantContext::setById(self::TENANT_ID);

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new GroupDeleted(5006, self::TENANT_ID, 'Disabled Feature Group');
        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    // ── Unknown tenant guard ──────────────────────────────────────────────────

    public function test_handle_skips_when_tenant_does_not_exist(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new GroupDeleted(5007, 999999999, 'Unknown Tenant Group');
        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    // ── Partner failure is swallowed ──────────────────────────────────────────

    public function test_handle_continues_without_exception_when_partner_returns_5xx(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        Http::fake(['*' => Http::response(['error' => 'server error'], 502)]);

        $event = new GroupDeleted(5008, self::TENANT_ID, 'Error Group');
        $this->makeListener()->handle($event);

        // Made the attempt; no exception propagated.
        Http::assertSent(fn ($r) => str_contains($r->url(), self::PARTNER_BASE_URL));
    }

    // ── GroupDeleted payload structure ────────────────────────────────────────

    public function test_group_deleted_payload_contains_id_and_name(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new GroupDeleted(5009, self::TENANT_ID, 'Full Payload Group');
        $this->makeListener()->handle($event);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return array_key_exists('action', $body)
                && array_key_exists('id', $body)
                && array_key_exists('name', $body)
                && array_key_exists('tenant_id', $body)
                && $body['action'] === 'deleted'
                && $body['name'] === 'Full Payload Group';
        });
    }

    // ── GroupMemberLeft payload structure ─────────────────────────────────────

    public function test_group_member_left_payload_contains_user_id(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new GroupMemberLeft(5010, 12345, self::TENANT_ID);
        $this->makeListener()->handle($event);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return array_key_exists('user_id', $body)
                && $body['user_id'] === 12345
                && $body['action'] === 'member_left';
        });
    }

    // ── GroupDeleted with null groupName ──────────────────────────────────────

    public function test_group_deleted_handles_null_group_name(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        // groupName is nullable in the event.
        $event = new GroupDeleted(5011, self::TENANT_ID, null);
        $this->makeListener()->handle($event);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return ($body['action'] ?? '') === 'deleted'
                && array_key_exists('name', $body)
                && $body['name'] === null;
        });
    }
}
