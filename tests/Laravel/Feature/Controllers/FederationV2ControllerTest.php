<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use App\Services\FederationFeatureService;
use Tests\Laravel\TestCase;
use Tests\Laravel\Concerns\FederationIntegrationHarness;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

/**
 * Feature tests for FederationV2Controller — user-facing federation endpoints.
 */
class FederationV2ControllerTest extends TestCase
{
    use DatabaseTransactions;
    use FederationIntegrationHarness;

    private function seedPartnerTenant(string $name = 'Partner Timebank'): int
    {
        $tenantId = (int) DB::table('tenants')->insertGetId([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . substr(uniqid(), -6),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->enableFederationForTenant($this->testTenantId);
        $this->enableFederationForTenant($tenantId);
        $this->app->make(FederationFeatureService::class)->clearCache();
        TenantContext::setById($this->testTenantId);

        return $tenantId;
    }

    private function seedPartnership(int $partnerTenantId, array $overrides = []): int
    {
        $data = array_merge([
            'tenant_id' => $this->testTenantId,
            'partner_tenant_id' => $partnerTenantId,
            'status' => 'active',
            'federation_level' => 4,
            'profiles_enabled' => 1,
            'messaging_enabled' => 1,
            'transactions_enabled' => 1,
            'listings_enabled' => 1,
            'events_enabled' => 1,
            'groups_enabled' => 1,
            'requested_at' => now(),
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        if ($this->columnExists('federation_partnerships', 'canonical_pair')) {
            $data['canonical_pair'] = min($this->testTenantId, $partnerTenantId) . '-' . max($this->testTenantId, $partnerTenantId);
        }

        return (int) DB::table('federation_partnerships')->insertGetId($data);
    }

    private function seedFederatedUser(int $tenantId, array $userAttributes = [], array $settings = []): User
    {
        $user = User::factory()->forTenant($tenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $userAttributes));

        DB::table('federation_user_settings')->updateOrInsert(
            ['user_id' => $user->id],
            array_merge([
                'federation_optin' => 1,
                'profile_visible_federated' => 1,
                'messaging_enabled_federated' => 1,
                'transactions_enabled_federated' => 1,
                'appear_in_federated_search' => 1,
                'show_skills_federated' => 1,
                'show_location_federated' => 1,
                'show_reviews_federated' => 1,
                'service_reach' => 'remote_ok',
                'travel_radius_km' => 50,
                'email_notifications' => 1,
                'updated_at' => now(),
            ], $settings)
        );

        return $user;
    }

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ------------------------------------------------------------------
    //  GET /v2/federation/status
    // ------------------------------------------------------------------

    public function test_federation_status_requires_auth(): void
    {
        $response = $this->apiGet('/v2/federation/status');

        $response->assertStatus(401);
    }

    public function test_federation_status_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/federation/status');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/federation/opt-in
    // ------------------------------------------------------------------

    public function test_opt_in_requires_auth(): void
    {
        $response = $this->apiPost('/v2/federation/opt-in');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/federation/opt-out
    // ------------------------------------------------------------------

    public function test_opt_out_requires_auth(): void
    {
        $response = $this->apiPost('/v2/federation/opt-out');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/federation/partners
    // ------------------------------------------------------------------

    public function test_partners_requires_auth(): void
    {
        $response = $this->apiGet('/v2/federation/partners');

        $response->assertStatus(401);
    }

    public function test_partners_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/federation/partners');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/federation/activity
    // ------------------------------------------------------------------

    public function test_activity_requires_auth(): void
    {
        $response = $this->apiGet('/v2/federation/activity');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/federation/settings
    // ------------------------------------------------------------------

    public function test_get_settings_requires_auth(): void
    {
        $response = $this->apiGet('/v2/federation/settings');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/federation/connections
    // ------------------------------------------------------------------

    public function test_connections_requires_auth(): void
    {
        $response = $this->apiGet('/v2/federation/connections');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/federation/members
    // ------------------------------------------------------------------

    public function test_federation_members_requires_auth(): void
    {
        $response = $this->apiGet('/v2/federation/members');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/federation/listings
    // ------------------------------------------------------------------

    public function test_federation_listings_requires_auth(): void
    {
        $response = $this->apiGet('/v2/federation/listings');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/federation/events
    // ------------------------------------------------------------------

    public function test_federation_events_requires_auth(): void
    {
        $response = $this->apiGet('/v2/federation/events');

        $response->assertStatus(401);
    }

    public function test_send_message_records_delivery_evidence_on_receiver_copy(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Api/FederationV2Controller.php'));

        $this->assertStringContainsString('$inboundId = (int)DB::getPdo()->lastInsertId();', $source);
        $this->assertStringContainsString('$emailSent = $this->federationEmailService->sendNewMessageNotification(', $source);
        $this->assertStringContainsString('SET email_sent_at = ?', $source);
        $this->assertStringContainsString('email_failed_at = ?', $source);
        $this->assertStringContainsString('WHERE id = ? AND receiver_tenant_id = ?', $source);
        $this->assertStringContainsString('SET notification_sent_at = NOW()', $source);
    }

    public function test_messages_endpoint_only_returns_the_viewers_copy_of_internal_messages(): void
    {
        $partnerTenantId = $this->seedPartnerTenant('Message Partner');
        $this->seedPartnership($partnerTenantId);
        $sender = $this->seedFederatedUser($this->testTenantId, ['first_name' => 'Sender']);
        $receiver = $this->seedFederatedUser($partnerTenantId, ['first_name' => 'Receiver']);

        $outboundId = (int) DB::table('federation_messages')->insertGetId([
            'sender_tenant_id' => $this->testTenantId,
            'sender_user_id' => $sender->id,
            'receiver_tenant_id' => $partnerTenantId,
            'receiver_user_id' => $receiver->id,
            'subject' => 'Hello & welcome',
            'body' => 'Plain & readable',
            'direction' => 'outbound',
            'status' => 'delivered',
            'created_at' => now(),
        ]);
        $inboundId = (int) DB::table('federation_messages')->insertGetId([
            'sender_tenant_id' => $this->testTenantId,
            'sender_user_id' => $sender->id,
            'receiver_tenant_id' => $partnerTenantId,
            'receiver_user_id' => $receiver->id,
            'subject' => 'Hello & welcome',
            'body' => 'Plain & readable',
            'direction' => 'inbound',
            'status' => 'unread',
            'created_at' => now(),
        ]);

        Sanctum::actingAs($sender, ['*']);
        $senderResponse = $this->apiGet('/v2/federation/messages');
        $senderResponse->assertOk();
        $senderIds = array_column($senderResponse->json('data'), 'id');
        $this->assertContains($outboundId, $senderIds);
        $this->assertNotContains($inboundId, $senderIds);
        $this->assertSame('Plain & readable', $senderResponse->json('data.0.body'));

        Sanctum::actingAs($receiver, ['*']);
        $receiverResponse = $this->getJson('/api/v2/federation/messages', [
            'X-Tenant-ID' => (string) $partnerTenantId,
            'Accept' => 'application/json',
        ]);
        $receiverResponse->assertOk();
        $receiverIds = array_column($receiverResponse->json('data'), 'id');
        $this->assertContains($inboundId, $receiverIds);
        $this->assertNotContains($outboundId, $receiverIds);
    }

    public function test_members_endpoint_hides_searchable_profiles_when_profile_visibility_is_disabled(): void
    {
        $partnerTenantId = $this->seedPartnerTenant('Hidden Profile Partner');
        $this->seedPartnership($partnerTenantId);
        $viewer = $this->seedFederatedUser($this->testTenantId);
        $hidden = $this->seedFederatedUser(
            $partnerTenantId,
            ['first_name' => 'Hidden', 'last_name' => 'Member'],
            ['profile_visible_federated' => 0, 'appear_in_federated_search' => 1]
        );

        Sanctum::actingAs($viewer, ['*']);
        $response = $this->apiGet('/v2/federation/members');

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertNotContains($hidden->id, $ids);
    }

    public function test_listings_and_events_require_item_level_federated_visibility(): void
    {
        $partnerTenantId = $this->seedPartnerTenant('Visibility Partner');
        $this->seedPartnership($partnerTenantId);
        $viewer = $this->seedFederatedUser($this->testTenantId);
        $owner = $this->seedFederatedUser($partnerTenantId);

        DB::table('listings')->insert([
            [
                'tenant_id' => $partnerTenantId,
                'user_id' => $owner->id,
                'title' => 'Local-only listing',
                'description' => 'Should stay local.',
                'type' => 'offer',
                'status' => 'active',
                'federated_visibility' => 'none',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $partnerTenantId,
                'user_id' => $owner->id,
                'title' => 'Federated listing',
                'description' => 'Should be visible.',
                'type' => 'offer',
                'status' => 'active',
                'federated_visibility' => 'listed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('events')->insert([
            [
                'tenant_id' => $partnerTenantId,
                'user_id' => $owner->id,
                'title' => 'Local-only event',
                'description' => 'Should stay local.',
                'location' => 'Online',
                'start_time' => now()->addDay(),
                'end_time' => now()->addDay()->addHour(),
                'status' => 'active',
                'federated_visibility' => 'none',
                'created_at' => now(),
            ],
            [
                'tenant_id' => $partnerTenantId,
                'user_id' => $owner->id,
                'title' => 'Federated event',
                'description' => 'Should be visible.',
                'location' => 'Online',
                'start_time' => now()->addDays(2),
                'end_time' => now()->addDays(2)->addHour(),
                'status' => 'active',
                'federated_visibility' => 'listed',
                'created_at' => now(),
            ],
        ]);

        Sanctum::actingAs($viewer, ['*']);

        $listingResponse = $this->apiGet('/v2/federation/listings');
        $listingResponse->assertOk();
        $listingTitles = array_column($listingResponse->json('data'), 'title');
        $this->assertContains('Federated listing', $listingTitles);
        $this->assertNotContains('Local-only listing', $listingTitles);

        $eventResponse = $this->apiGet('/v2/federation/events');
        $eventResponse->assertOk();
        $eventTitles = array_column($eventResponse->json('data'), 'title');
        $this->assertContains('Federated event', $eventTitles);
        $this->assertNotContains('Local-only event', $eventTitles);
    }

    public function test_admin_partnership_stats_count_logical_messages_and_internal_transactions(): void
    {
        $partnerTenantId = $this->seedPartnerTenant('Stats Partner');
        $partnershipId = $this->seedPartnership($partnerTenantId);
        $admin = $this->seedFederatedUser($this->testTenantId, ['role' => 'admin']);
        $receiver = $this->seedFederatedUser($partnerTenantId);

        DB::table('federation_messages')->insert([
            [
                'sender_tenant_id' => $this->testTenantId,
                'sender_user_id' => $admin->id,
                'receiver_tenant_id' => $partnerTenantId,
                'receiver_user_id' => $receiver->id,
                'subject' => 'Stats',
                'body' => 'One logical message',
                'direction' => 'outbound',
                'status' => 'delivered',
                'created_at' => now(),
            ],
            [
                'sender_tenant_id' => $this->testTenantId,
                'sender_user_id' => $admin->id,
                'receiver_tenant_id' => $partnerTenantId,
                'receiver_user_id' => $receiver->id,
                'subject' => 'Stats',
                'body' => 'One logical message',
                'direction' => 'inbound',
                'status' => 'unread',
                'created_at' => now(),
            ],
        ]);
        DB::table('transactions')->insert([
            'tenant_id' => $this->testTenantId,
            'sender_id' => $admin->id,
            'receiver_id' => $receiver->id,
            'amount' => 3,
            'description' => 'Internal federation stats transfer',
            'status' => 'completed',
            'is_federated' => 1,
            'sender_tenant_id' => $this->testTenantId,
            'receiver_tenant_id' => $partnerTenantId,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($admin, ['*']);
        $response = $this->apiGet("/v2/admin/federation/partnerships/{$partnershipId}/stats");

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('data.messages_exchanged'));
        $this->assertSame(1, (int) $response->json('data.transactions_completed'));
    }
}
