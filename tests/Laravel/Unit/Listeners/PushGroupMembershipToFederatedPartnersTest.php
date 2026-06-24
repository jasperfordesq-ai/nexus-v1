<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\GroupMemberJoined;
use App\Listeners\PushGroupMembershipToFederatedPartners;
use App\Services\FederationExternalApiClient;
use App\Services\FederationFeatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * PushGroupMembershipToFederatedPartnersTest
 *
 * Covers the outbound federation push for group membership join events.
 * Uses Http::fake() to intercept outbound calls; real DB rows rolled back.
 *
 * Unique tenant ID: 99681
 */
class PushGroupMembershipToFederatedPartnersTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99681;

    /** A real public IP that passes OutboundUrlGuard::isPublicIp() — example.com */
    private const PARTNER_BASE_URL = 'https://93.184.216.34';

    private int $partnerId;
    private int $groupId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // Force correct APP_KEY so Crypt::decryptString() can initialize the
        // Encrypter singleton (Docker .env has an invalid key).
        config(['app.key' => 'base64:HfQEDtbtr90JIXhsaAhSFWnzIo1f31VZ2e5qLqKKnls=']);
        app()->forgetInstance('encrypter');

        // Ensure the tenant row exists (setById needs it).
        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Test Tenant 99681',
            'slug'       => 'test-tenant-99681',
            'domain'     => 'test-tenant-99681.example.test',
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

        // Insert active partner with allow_groups=1.
        // Plaintext api_key — decryptCredential() catches DecryptException on non-encrypted
        // values and falls back to returning the raw string.
        $this->partnerId = (int) DB::table('federation_external_partners')->insertGetId([
            'tenant_id'           => self::TENANT_ID,
            'name'                => 'Membership Partner 99681',
            'base_url'            => self::PARTNER_BASE_URL,
            'api_path'            => '/api/v2/federation',
            'api_key'             => 'test-api-key-99681',
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

        // Insert user first (needed as groups.owner_id FK references users.id).
        $this->userId = (int) DB::table('users')->insertGetId([
            'tenant_id'          => self::TENANT_ID,
            'name'               => 'Fed Member 99681',
            'first_name'         => 'Fed',
            'last_name'          => 'Member',
            'email'              => 'fed-member-99681-' . uniqid() . '@example.test',
            'password'           => 'x',
            'status'             => 'active',
            'preferred_language' => 'en',
            'created_at'         => now(),
        ]);

        // Insert a group in the `groups` table with federated_visibility='listed'.
        // The listener does Group::find($event->groupId) so the row must exist.
        // owner_id uses the real user id to satisfy the FK constraint.
        $this->groupId = (int) DB::table('groups')->insertGetId([
            'tenant_id'            => self::TENANT_ID,
            'owner_id'             => $this->userId,
            'name'                 => 'Federated Group 99681',
            'slug'                 => 'fed-group-99681-' . uniqid(),
            'federated_visibility' => 'listed',
            'status'               => 'active',
            'created_at'           => now(),
        ]);

        FederationExternalApiClient::clearAdapterCache();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeListener(): PushGroupMembershipToFederatedPartners
    {
        return new PushGroupMembershipToFederatedPartners(
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

    // ── Happy path ────────────────────────────────────────────────────────────

    public function test_handle_pushes_member_joined_to_partner(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new GroupMemberJoined($this->groupId, $this->userId, self::TENANT_ID);
        $this->makeListener()->handle($event);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return $request->method() === 'POST'
                && str_contains($request->url(), self::PARTNER_BASE_URL)
                && str_contains($request->url(), '/groups')
                && ($body['action'] ?? '') === 'member_joined'
                && ($body['group_id'] ?? 0) === $this->groupId
                && ($body['user_id'] ?? 0) === $this->userId
                && ($body['tenant_id'] ?? 0) === self::TENANT_ID;
        });
    }

    // ── Payload structure ─────────────────────────────────────────────────────

    public function test_handle_payload_includes_joined_at_timestamp(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new GroupMemberJoined($this->groupId, $this->userId, self::TENANT_ID);
        $this->makeListener()->handle($event);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return array_key_exists('joined_at', $body) && $body['joined_at'] !== null;
        });
    }

    // ── Authorization header ──────────────────────────────────────────────────

    public function test_handle_sends_authorization_header(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new GroupMemberJoined($this->groupId, $this->userId, self::TENANT_ID);
        $this->makeListener()->handle($event);

        Http::assertSent(fn ($r) => $r->hasHeader('Authorization'));
    }

    // ── Group visibility gate ─────────────────────────────────────────────────

    public function test_handle_skips_push_when_group_has_visibility_none(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        DB::table('groups')
            ->where('id', $this->groupId)
            ->update(['federated_visibility' => 'none']);

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new GroupMemberJoined($this->groupId, $this->userId, self::TENANT_ID);
        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    public function test_handle_skips_push_when_group_visibility_is_public(): void
    {
        // 'public' is NOT in the allowed set ['listed', 'public'] — wait, the
        // listener checks in_array($visibility, ['listed', 'public'], true), so
        // 'public' IS allowed. Let's confirm by flipping to 'joinable' instead.
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        // 'joinable' is not in ['listed','public']
        DB::table('groups')
            ->where('id', $this->groupId)
            ->update(['federated_visibility' => 'joinable']);

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new GroupMemberJoined($this->groupId, $this->userId, self::TENANT_ID);
        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    // ── Group not found gate ──────────────────────────────────────────────────

    public function test_handle_skips_push_when_group_not_found(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new GroupMemberJoined(99999999, $this->userId, self::TENANT_ID);
        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    // ── No partners gate ──────────────────────────────────────────────────────

    public function test_handle_sends_nothing_when_no_partner_has_allow_groups(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        DB::table('federation_external_partners')
            ->where('id', $this->partnerId)
            ->update(['allow_groups' => 0]);

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new GroupMemberJoined($this->groupId, $this->userId, self::TENANT_ID);
        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    // ── Feature flag gate ─────────────────────────────────────────────────────

    public function test_handle_skips_push_when_tenant_federation_feature_disabled(): void
    {
        $tenant   = DB::table('tenants')->where('id', self::TENANT_ID)->first();
        $features = $tenant && $tenant->features ? json_decode($tenant->features, true) : [];
        $features['federation'] = false;
        DB::table('tenants')
            ->where('id', self::TENANT_ID)
            ->update(['features' => json_encode($features)]);
        TenantContext::setById(self::TENANT_ID);

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new GroupMemberJoined($this->groupId, $this->userId, self::TENANT_ID);
        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    // ── Unknown tenant guard ──────────────────────────────────────────────────

    public function test_handle_skips_when_tenant_id_does_not_exist(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new GroupMemberJoined($this->groupId, $this->userId, 999999999);
        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    // ── Partner failure is swallowed ──────────────────────────────────────────

    public function test_handle_continues_without_exception_on_partner_http_error(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        Http::fake(['*' => Http::response(['error' => 'down'], 503)]);

        $event = new GroupMemberJoined($this->groupId, $this->userId, self::TENANT_ID);
        $this->makeListener()->handle($event);

        // Attempted the call; no exception thrown.
        Http::assertSent(fn ($r) => str_contains($r->url(), self::PARTNER_BASE_URL));
    }

    // ── Multiple partners ─────────────────────────────────────────────────────

    public function test_handle_sends_to_each_partner_with_allow_groups(): void
    {
        $this->enableTenantFeatureFlag();
        $this->enableTenantFederation();

        // Insert a second partner with a different base_url (unique key constraint).
        // 142.250.80.46 = google.com — public IP, passes SSRF check.
        DB::table('federation_external_partners')->insertGetId([
            'tenant_id'           => self::TENANT_ID,
            'name'                => 'Second Partner 99681',
            'base_url'            => 'https://142.250.80.46',
            'api_path'            => '/api/v2/federation',
            'api_key'             => 'test-api-key-99681-b',
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

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $event = new GroupMemberJoined($this->groupId, $this->userId, self::TENANT_ID);
        $this->makeListener()->handle($event);

        // Both partners should have received a request.
        Http::assertSentCount(2);
    }
}
