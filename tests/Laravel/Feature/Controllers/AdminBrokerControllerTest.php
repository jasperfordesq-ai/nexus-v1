<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for AdminBrokerController.
 *
 * Covers dashboard, exchanges, risk tags, messages, monitoring, and configuration.
 */
class AdminBrokerControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // DASHBOARD — GET /v2/admin/broker/dashboard
    // ================================================================

    public function test_dashboard_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/broker/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_dashboard_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/broker/dashboard');

        $response->assertStatus(403);
    }

    public function test_dashboard_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/broker/dashboard');

        $response->assertStatus(401);
    }

    // ================================================================
    // EXCHANGES — GET /v2/admin/broker/exchanges
    // ================================================================

    public function test_exchanges_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/broker/exchanges');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_exchanges_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/broker/exchanges');

        $response->assertStatus(403);
    }

    // ================================================================
    // RISK TAGS — GET /v2/admin/broker/risk-tags
    // ================================================================

    public function test_risk_tags_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/broker/risk-tags');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // MESSAGES — GET /v2/admin/broker/messages
    // ================================================================

    public function test_messages_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/broker/messages');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_messages_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/broker/messages');

        $response->assertStatus(403);
    }

    // ================================================================
    // UNREVIEWED COUNT — GET /v2/admin/broker/messages/unreviewed-count
    // ================================================================

    public function test_unreviewed_count_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/broker/messages/unreviewed-count');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // MONITORING — GET /v2/admin/broker/monitoring
    // ================================================================

    public function test_monitoring_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/broker/monitoring');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // CONFIGURATION — GET /v2/admin/broker/configuration
    // ================================================================

    public function test_get_configuration_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/broker/configuration');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_get_configuration_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/broker/configuration');

        $response->assertStatus(403);
    }

    // ================================================================
    // ARCHIVES — GET /v2/admin/broker/archives
    // ================================================================

    public function test_archives_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/broker/archives');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_archives_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/broker/archives');

        $response->assertStatus(401);
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * Insert a real messages row (required by broker_message_copies FK) and a
     * broker_message_copies row.  Returns the broker_message_copies.id.
     *
     * @param array<string, mixed> $overrides  Extra columns for broker_message_copies
     */
    private function insertMessageCopy(int $senderId, int $receiverId, array $overrides = []): int
    {
        $msgId = DB::table('messages')->insertGetId([
            'tenant_id'   => $this->testTenantId,
            'sender_id'   => $senderId,
            'receiver_id' => $receiverId,
            'body'        => 'Test message for broker review',
            'is_read'     => false,
            'created_at'  => now()->subHour(),
        ]);

        return DB::table('broker_message_copies')->insertGetId(array_merge([
            'tenant_id'           => $this->testTenantId,
            'original_message_id' => $msgId,
            'sender_id'           => $senderId,
            'receiver_id'         => $receiverId,
            'message_body'        => 'Hello, need help.',
            'sent_at'             => now()->subHour(),
            'copy_reason'         => 'first_contact',
            'flagged'             => false,
            'archive_id'          => null,
            'conversation_key'    => 'key-' . $senderId . '-' . $receiverId . '-' . uniqid(),
            'created_at'          => now(),
        ], $overrides));
    }

    // ================================================================
    // APPROVE EXCHANGE — POST /v2/admin/broker/exchanges/{id}/approve
    // ================================================================

    public function test_approve_exchange_succeeds(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $requester = User::factory()->forTenant($this->testTenantId)->create();
        $provider = User::factory()->forTenant($this->testTenantId)->create();

        $exchangeId = DB::table('exchange_requests')->insertGetId([
            'tenant_id'      => $this->testTenantId,
            'requester_id'   => $requester->id,
            'provider_id'    => $provider->id,
            'proposed_hours' => 2.0,
            'status'         => 'pending_broker',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->apiPost("/v2/admin/broker/exchanges/{$exchangeId}/approve", [
            'notes' => 'Looks good',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $exchangeId);
        $response->assertJsonPath('data.status', 'accepted');
    }

    public function test_approve_exchange_returns_404_for_wrong_tenant(): void
    {
        $adminB = User::factory()->forTenant(999)->admin()->create();
        $requester = User::factory()->forTenant($this->testTenantId)->create();
        $provider = User::factory()->forTenant($this->testTenantId)->create();

        $exchangeId = DB::table('exchange_requests')->insertGetId([
            'tenant_id'      => $this->testTenantId,
            'requester_id'   => $requester->id,
            'provider_id'    => $provider->id,
            'proposed_hours' => 2.0,
            'status'         => 'pending_broker',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Act as admin of tenant 999 but exchange belongs to tenant 2
        \App\Core\TenantContext::setById(999);
        Sanctum::actingAs($adminB);

        $response = $this->withHeaders(['X-Tenant-ID' => '999'])
            ->postJson("/api/v2/admin/broker/exchanges/{$exchangeId}/approve", ['notes' => '']);

        $response->assertStatus(404);
        // Reset context
        \App\Core\TenantContext::setById($this->testTenantId);
    }

    public function test_approve_exchange_returns_403_for_non_broker(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        $requester = User::factory()->forTenant($this->testTenantId)->create();
        $provider = User::factory()->forTenant($this->testTenantId)->create();

        $exchangeId = DB::table('exchange_requests')->insertGetId([
            'tenant_id'      => $this->testTenantId,
            'requester_id'   => $requester->id,
            'provider_id'    => $provider->id,
            'proposed_hours' => 2.0,
            'status'         => 'pending_broker',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        Sanctum::actingAs($member);

        $response = $this->apiPost("/v2/admin/broker/exchanges/{$exchangeId}/approve");

        $response->assertStatus(403);
    }

    public function test_approve_exchange_returns_401_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/admin/broker/exchanges/1/approve');

        $response->assertStatus(401);
    }

    public function test_approve_exchange_returns_422_for_invalid_status(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $requester = User::factory()->forTenant($this->testTenantId)->create();
        $provider = User::factory()->forTenant($this->testTenantId)->create();

        // Status is 'pending' (not 'pending_broker') — not approvable
        $exchangeId = DB::table('exchange_requests')->insertGetId([
            'tenant_id'      => $this->testTenantId,
            'requester_id'   => $requester->id,
            'provider_id'    => $provider->id,
            'proposed_hours' => 2.0,
            'status'         => 'pending',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->apiPost("/v2/admin/broker/exchanges/{$exchangeId}/approve");

        // Controller returns error (not a 422 HTTP code — it uses respondWithError without explicit status)
        $response->assertStatus(200);
        $response->assertJsonPath('errors.0.code', 'INVALID_STATUS');
    }

    // ================================================================
    // REJECT EXCHANGE — POST /v2/admin/broker/exchanges/{id}/reject
    // ================================================================

    public function test_reject_exchange_succeeds(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $requester = User::factory()->forTenant($this->testTenantId)->create();
        $provider = User::factory()->forTenant($this->testTenantId)->create();

        $exchangeId = DB::table('exchange_requests')->insertGetId([
            'tenant_id'      => $this->testTenantId,
            'requester_id'   => $requester->id,
            'provider_id'    => $provider->id,
            'proposed_hours' => 2.0,
            'status'         => 'pending_broker',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->apiPost("/v2/admin/broker/exchanges/{$exchangeId}/reject", [
            'reason' => 'Does not meet criteria',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'cancelled');
    }

    public function test_reject_exchange_returns_422_without_reason(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $requester = User::factory()->forTenant($this->testTenantId)->create();
        $provider = User::factory()->forTenant($this->testTenantId)->create();

        $exchangeId = DB::table('exchange_requests')->insertGetId([
            'tenant_id'      => $this->testTenantId,
            'requester_id'   => $requester->id,
            'provider_id'    => $provider->id,
            'proposed_hours' => 2.0,
            'status'         => 'pending_broker',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->apiPost("/v2/admin/broker/exchanges/{$exchangeId}/reject", [
            'reason' => '',
        ]);

        // reason is required — controller returns error response
        $response->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
    }

    public function test_reject_exchange_returns_404_for_wrong_tenant(): void
    {
        $adminB = User::factory()->forTenant(999)->admin()->create();
        $requester = User::factory()->forTenant($this->testTenantId)->create();
        $provider = User::factory()->forTenant($this->testTenantId)->create();

        $exchangeId = DB::table('exchange_requests')->insertGetId([
            'tenant_id'      => $this->testTenantId,
            'requester_id'   => $requester->id,
            'provider_id'    => $provider->id,
            'proposed_hours' => 2.0,
            'status'         => 'pending_broker',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        \App\Core\TenantContext::setById(999);
        Sanctum::actingAs($adminB);

        $response = $this->withHeaders(['X-Tenant-ID' => '999'])
            ->postJson("/api/v2/admin/broker/exchanges/{$exchangeId}/reject", ['reason' => 'Cross-tenant attempt']);

        $response->assertStatus(404);
        \App\Core\TenantContext::setById($this->testTenantId);
    }

    public function test_reject_exchange_returns_403_for_non_broker(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        $exchangeId = DB::table('exchange_requests')->insertGetId([
            'tenant_id'      => $this->testTenantId,
            'requester_id'   => User::factory()->forTenant($this->testTenantId)->create()->id,
            'provider_id'    => User::factory()->forTenant($this->testTenantId)->create()->id,
            'proposed_hours' => 2.0,
            'status'         => 'pending_broker',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        Sanctum::actingAs($member);

        $response = $this->apiPost("/v2/admin/broker/exchanges/{$exchangeId}/reject", ['reason' => 'Test']);

        $response->assertStatus(403);
    }

    // ================================================================
    // SHOW EXCHANGE — GET /v2/admin/broker/exchanges/{id}
    // ================================================================

    public function test_show_exchange_returns_details(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $requester = User::factory()->forTenant($this->testTenantId)->create();
        $provider = User::factory()->forTenant($this->testTenantId)->create();

        $exchangeId = DB::table('exchange_requests')->insertGetId([
            'tenant_id'      => $this->testTenantId,
            'requester_id'   => $requester->id,
            'provider_id'    => $provider->id,
            'proposed_hours' => 3.0,
            'status'         => 'pending_broker',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->apiGet("/v2/admin/broker/exchanges/{$exchangeId}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['exchange', 'history', 'risk_tag']]);
        $response->assertJsonPath('data.exchange.id', $exchangeId);
    }

    public function test_show_exchange_returns_404_for_wrong_tenant(): void
    {
        $adminB = User::factory()->forTenant(999)->admin()->create();
        $exchangeId = DB::table('exchange_requests')->insertGetId([
            'tenant_id'      => $this->testTenantId,
            'requester_id'   => User::factory()->forTenant($this->testTenantId)->create()->id,
            'provider_id'    => User::factory()->forTenant($this->testTenantId)->create()->id,
            'proposed_hours' => 2.0,
            'status'         => 'pending_broker',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        \App\Core\TenantContext::setById(999);
        Sanctum::actingAs($adminB);

        $response = $this->withHeaders(['X-Tenant-ID' => '999'])
            ->getJson("/api/v2/admin/broker/exchanges/{$exchangeId}");

        $response->assertStatus(404);
        \App\Core\TenantContext::setById($this->testTenantId);
    }

    // ================================================================
    // SAVE RISK TAG — POST /v2/admin/broker/risk-tags/{listingId}
    // ================================================================

    public function test_save_risk_tag_succeeds_creates_new(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $listing = \App\Models\Listing::factory()->forTenant($this->testTenantId)->create();

        Sanctum::actingAs($admin);

        $response = $this->apiPost("/v2/admin/broker/risk-tags/{$listing->id}", [
            'risk_level'    => 'medium',
            'risk_category' => 'safeguarding',
            'risk_notes'    => 'Initial assessment',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.listing_id', $listing->id);
        $response->assertJsonPath('data.risk_level', 'medium');

        $this->assertDatabaseHas('listing_risk_tags', [
            'listing_id' => $listing->id,
            'tenant_id'  => $this->testTenantId,
            'risk_level' => 'medium',
        ]);
    }

    public function test_save_risk_tag_updates_existing(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $listing = \App\Models\Listing::factory()->forTenant($this->testTenantId)->create();

        // Pre-insert a tag
        DB::table('listing_risk_tags')->insert([
            'listing_id'    => $listing->id,
            'tenant_id'     => $this->testTenantId,
            'risk_level'    => 'low',
            'risk_category' => 'other',
            'tagged_by'     => $admin->id,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->apiPost("/v2/admin/broker/risk-tags/{$listing->id}", [
            'risk_level'    => 'high',
            'risk_category' => 'safeguarding',
            'risk_notes'    => 'Upgraded',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.risk_level', 'high');

        $this->assertDatabaseHas('listing_risk_tags', [
            'listing_id' => $listing->id,
            'risk_level' => 'high',
        ]);
    }

    public function test_save_risk_tag_returns_422_for_invalid_category(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $listing = \App\Models\Listing::factory()->forTenant($this->testTenantId)->create();

        Sanctum::actingAs($admin);

        $response = $this->apiPost("/v2/admin/broker/risk-tags/{$listing->id}", [
            'risk_level'    => 'medium',
            'risk_category' => 'not_a_real_category',
        ]);

        $response->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
    }

    public function test_save_risk_tag_returns_422_for_missing_risk_category(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $listing = \App\Models\Listing::factory()->forTenant($this->testTenantId)->create();

        Sanctum::actingAs($admin);

        $response = $this->apiPost("/v2/admin/broker/risk-tags/{$listing->id}", [
            'risk_level' => 'low',
            // risk_category intentionally omitted
        ]);

        $response->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
    }

    public function test_save_risk_tag_returns_404_for_wrong_tenant(): void
    {
        $adminB = User::factory()->forTenant(999)->admin()->create();
        $listing = \App\Models\Listing::factory()->forTenant($this->testTenantId)->create();

        \App\Core\TenantContext::setById(999);
        Sanctum::actingAs($adminB);

        $response = $this->withHeaders(['X-Tenant-ID' => '999'])
            ->postJson("/api/v2/admin/broker/risk-tags/{$listing->id}", [
                'risk_level'    => 'medium',
                'risk_category' => 'safeguarding',
            ]);

        $response->assertStatus(404);
        \App\Core\TenantContext::setById($this->testTenantId);
    }

    // ================================================================
    // REMOVE RISK TAG — DELETE /v2/admin/broker/risk-tags/{listingId}
    // ================================================================

    public function test_remove_risk_tag_succeeds(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $listing = \App\Models\Listing::factory()->forTenant($this->testTenantId)->create();

        DB::table('listing_risk_tags')->insert([
            'listing_id'    => $listing->id,
            'tenant_id'     => $this->testTenantId,
            'risk_level'    => 'medium',
            'risk_category' => 'other',
            'tagged_by'     => $admin->id,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->apiDelete("/v2/admin/broker/risk-tags/{$listing->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.removed', true);

        $this->assertDatabaseMissing('listing_risk_tags', [
            'listing_id' => $listing->id,
            'tenant_id'  => $this->testTenantId,
        ]);
    }

    public function test_remove_risk_tag_returns_404_for_wrong_tenant(): void
    {
        $adminB = User::factory()->forTenant(999)->admin()->create();
        $listing = \App\Models\Listing::factory()->forTenant($this->testTenantId)->create();

        // Tag exists on tenant 2
        DB::table('listing_risk_tags')->insert([
            'listing_id'    => $listing->id,
            'tenant_id'     => $this->testTenantId,
            'risk_level'    => 'low',
            'risk_category' => 'other',
            'tagged_by'     => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        \App\Core\TenantContext::setById(999);
        Sanctum::actingAs($adminB);

        $response = $this->withHeaders(['X-Tenant-ID' => '999'])
            ->deleteJson("/api/v2/admin/broker/risk-tags/{$listing->id}");

        $response->assertStatus(404);
        \App\Core\TenantContext::setById($this->testTenantId);
    }

    // ================================================================
    // SET MONITORING — POST /v2/admin/broker/monitoring/{userId}
    // ================================================================

    public function test_set_monitoring_adds_user(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $target = User::factory()->forTenant($this->testTenantId)->create();

        Sanctum::actingAs($admin);

        $response = $this->apiPost("/v2/admin/broker/monitoring/{$target->id}", [
            'under_monitoring' => true,
            'reason'           => 'Suspicious activity',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.under_monitoring', true);

        $this->assertDatabaseHas('user_messaging_restrictions', [
            'user_id'         => $target->id,
            'tenant_id'       => $this->testTenantId,
            'under_monitoring' => 1,
        ]);
    }

    public function test_set_monitoring_removes_user(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $target = User::factory()->forTenant($this->testTenantId)->create();

        // Pre-insert monitoring record
        DB::table('user_messaging_restrictions')->insert([
            'user_id'                => $target->id,
            'tenant_id'              => $this->testTenantId,
            'under_monitoring'       => 1,
            'monitoring_reason'      => 'Test reason',
            'restriction_reason'     => 'Test reason',
            'messaging_disabled'     => 0,
            'monitoring_started_at'  => now(),
            'monitoring_expires_at'  => null,
            'restricted_by'          => $admin->id,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->apiPost("/v2/admin/broker/monitoring/{$target->id}", [
            'under_monitoring' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.under_monitoring', false);

        $this->assertDatabaseHas('user_messaging_restrictions', [
            'user_id'         => $target->id,
            'under_monitoring' => 0,
        ]);
    }

    public function test_set_monitoring_returns_404_for_wrong_tenant(): void
    {
        $adminB = User::factory()->forTenant(999)->admin()->create();
        $target = User::factory()->forTenant($this->testTenantId)->create();

        \App\Core\TenantContext::setById(999);
        Sanctum::actingAs($adminB);

        $response = $this->withHeaders(['X-Tenant-ID' => '999'])
            ->postJson("/api/v2/admin/broker/monitoring/{$target->id}", [
                'under_monitoring' => true,
                'reason'           => 'Cross-tenant attempt',
            ]);

        $response->assertStatus(404);
        \App\Core\TenantContext::setById($this->testTenantId);
    }

    public function test_set_monitoring_returns_403_for_non_broker(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        $target = User::factory()->forTenant($this->testTenantId)->create();

        Sanctum::actingAs($member);

        $response = $this->apiPost("/v2/admin/broker/monitoring/{$target->id}", [
            'under_monitoring' => true,
            'reason'           => 'Test',
        ]);

        $response->assertStatus(403);
    }

    // ================================================================
    // APPROVE MESSAGE — POST /v2/admin/broker/messages/{id}/approve
    // ================================================================

    public function test_approve_message_creates_archive(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $sender = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        $copyId = $this->insertMessageCopy($sender->id, $receiver->id);

        Sanctum::actingAs($admin);

        $response = $this->apiPost("/v2/admin/broker/messages/{$copyId}/approve", [
            'notes' => 'No issues found',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $copyId);
        $this->assertNotNull($response->json('data.archive_id'));

        // Archive record should exist
        $archiveId = $response->json('data.archive_id');
        $this->assertDatabaseHas('broker_review_archives', [
            'id'             => $archiveId,
            'tenant_id'      => $this->testTenantId,
            'broker_copy_id' => $copyId,
        ]);
    }

    public function test_approve_message_returns_409_when_already_archived(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $sender = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        // Create a copy first, then create an archive for it
        $copyId = $this->insertMessageCopy($sender->id, $receiver->id);

        $archiveId = DB::table('broker_review_archives')->insertGetId([
            'tenant_id'              => $this->testTenantId,
            'broker_copy_id'         => $copyId,
            'sender_id'              => $sender->id,
            'sender_name'            => $sender->first_name . ' ' . $sender->last_name,
            'receiver_id'            => $receiver->id,
            'receiver_name'          => $receiver->first_name . ' ' . $receiver->last_name,
            'related_listing_id'     => null,
            'listing_title'          => null,
            'copy_reason'            => 'first_contact',
            'target_message_body'    => 'Hello',
            'target_message_sent_at' => now()->subHour(),
            'conversation_snapshot'  => '[]',
            'decision'               => 'approved',
            'decided_by'             => $admin->id,
            'decided_by_name'        => 'Admin User',
            'decided_at'             => now(),
            'created_at'             => now(),
        ]);

        // Mark the copy as already archived
        DB::table('broker_message_copies')
            ->where('id', $copyId)
            ->update(['archive_id' => $archiveId, 'archived_at' => now()]);

        Sanctum::actingAs($admin);

        $response = $this->apiPost("/v2/admin/broker/messages/{$copyId}/approve");

        $response->assertStatus(409);
    }

    public function test_approve_message_returns_404_for_wrong_tenant(): void
    {
        $adminB = User::factory()->forTenant(999)->admin()->create();
        $sender = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        $copyId = $this->insertMessageCopy($sender->id, $receiver->id);

        \App\Core\TenantContext::setById(999);
        Sanctum::actingAs($adminB);

        $response = $this->withHeaders(['X-Tenant-ID' => '999'])
            ->postJson("/api/v2/admin/broker/messages/{$copyId}/approve");

        $response->assertStatus(404);
        \App\Core\TenantContext::setById($this->testTenantId);
    }

    // ================================================================
    // FLAG MESSAGE — POST /v2/admin/broker/messages/{id}/flag
    // ================================================================

    public function test_flag_message_succeeds(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $sender = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        $copyId = $this->insertMessageCopy($sender->id, $receiver->id, ['message_body' => 'Potentially harmful content']);

        Sanctum::actingAs($admin);

        $response = $this->apiPost("/v2/admin/broker/messages/{$copyId}/flag", [
            'reason'   => 'Inappropriate content',
            'severity' => 'warning',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.flagged', true);
        $response->assertJsonPath('data.flag_severity', 'warning');

        $this->assertDatabaseHas('broker_message_copies', [
            'id'            => $copyId,
            'flagged'       => 1,
            'flag_reason'   => 'Inappropriate content',
            'flag_severity' => 'warning',
        ]);
    }

    public function test_flag_message_returns_422_for_invalid_severity(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $sender = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        $copyId = $this->insertMessageCopy($sender->id, $receiver->id);

        Sanctum::actingAs($admin);

        $response = $this->apiPost("/v2/admin/broker/messages/{$copyId}/flag", [
            'reason'   => 'Some reason',
            'severity' => 'not_valid_severity',
        ]);

        $response->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
    }

    public function test_flag_message_returns_422_without_reason(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $sender = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        $copyId = $this->insertMessageCopy($sender->id, $receiver->id);

        Sanctum::actingAs($admin);

        $response = $this->apiPost("/v2/admin/broker/messages/{$copyId}/flag", [
            'reason'   => '',
            'severity' => 'warning',
        ]);

        $response->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
    }

    public function test_flag_message_returns_404_for_wrong_tenant(): void
    {
        $adminB = User::factory()->forTenant(999)->admin()->create();
        $sender = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        $copyId = $this->insertMessageCopy($sender->id, $receiver->id);

        \App\Core\TenantContext::setById(999);
        Sanctum::actingAs($adminB);

        $response = $this->withHeaders(['X-Tenant-ID' => '999'])
            ->postJson("/api/v2/admin/broker/messages/{$copyId}/flag", [
                'reason'   => 'Cross-tenant',
                'severity' => 'warning',
            ]);

        $response->assertStatus(404);
        \App\Core\TenantContext::setById($this->testTenantId);
    }

    // ================================================================
    // REVIEW MESSAGE — POST /v2/admin/broker/messages/{id}/review
    // ================================================================

    public function test_review_message_marks_as_reviewed(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $sender = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        $copyId = $this->insertMessageCopy($sender->id, $receiver->id);

        Sanctum::actingAs($admin);

        $response = $this->apiPost("/v2/admin/broker/messages/{$copyId}/review");

        $response->assertStatus(200);
        $response->assertJsonPath('data.reviewed', true);

        $this->assertDatabaseHas('broker_message_copies', [
            'id'          => $copyId,
            'reviewed_by' => $admin->id,
        ]);
    }

    public function test_review_message_returns_404_for_wrong_tenant(): void
    {
        $adminB = User::factory()->forTenant(999)->admin()->create();
        $sender = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        $copyId = $this->insertMessageCopy($sender->id, $receiver->id);

        \App\Core\TenantContext::setById(999);
        Sanctum::actingAs($adminB);

        $response = $this->withHeaders(['X-Tenant-ID' => '999'])
            ->postJson("/api/v2/admin/broker/messages/{$copyId}/review");

        $response->assertStatus(404);
        \App\Core\TenantContext::setById($this->testTenantId);
    }

    // ================================================================
    // SHOW MESSAGE — GET /v2/admin/broker/messages/{id}
    // ================================================================

    public function test_show_message_returns_details(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $sender = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        $copyId = $this->insertMessageCopy($sender->id, $receiver->id, ['message_body' => 'Hello there']);

        Sanctum::actingAs($admin);

        $response = $this->apiGet("/v2/admin/broker/messages/{$copyId}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['copy', 'thread', 'archive']]);
        $response->assertJsonPath('data.copy.id', $copyId);
    }

    public function test_show_message_returns_404_for_wrong_tenant(): void
    {
        $adminB = User::factory()->forTenant(999)->admin()->create();
        $sender = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        $copyId = $this->insertMessageCopy($sender->id, $receiver->id);

        \App\Core\TenantContext::setById(999);
        Sanctum::actingAs($adminB);

        $response = $this->withHeaders(['X-Tenant-ID' => '999'])
            ->getJson("/api/v2/admin/broker/messages/{$copyId}");

        $response->assertStatus(404);
        \App\Core\TenantContext::setById($this->testTenantId);
    }

    // ================================================================
    // SHOW ARCHIVE — GET /v2/admin/broker/archives/{id}
    // ================================================================

    public function test_show_archive_returns_details(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $sender = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        $copyId = $this->insertMessageCopy($sender->id, $receiver->id);

        $archiveId = DB::table('broker_review_archives')->insertGetId([
            'tenant_id'              => $this->testTenantId,
            'broker_copy_id'         => $copyId,
            'sender_id'              => $sender->id,
            'sender_name'            => 'Test Sender',
            'receiver_id'            => $receiver->id,
            'receiver_name'          => 'Test Receiver',
            'related_listing_id'     => null,
            'listing_title'          => null,
            'copy_reason'            => 'first_contact',
            'target_message_body'    => 'Archived message',
            'target_message_sent_at' => now()->subDay(),
            'conversation_snapshot'  => json_encode([]),
            'decision'               => 'approved',
            'decided_by'             => $admin->id,
            'decided_by_name'        => 'Admin',
            'decided_at'             => now(),
            'created_at'             => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->apiGet("/v2/admin/broker/archives/{$archiveId}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $archiveId);
        $response->assertJsonPath('data.decision', 'approved');
    }

    public function test_show_archive_returns_404_for_wrong_tenant(): void
    {
        $adminB = User::factory()->forTenant(999)->admin()->create();
        $sender = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        $copyId = $this->insertMessageCopy($sender->id, $receiver->id);

        $archiveId = DB::table('broker_review_archives')->insertGetId([
            'tenant_id'              => $this->testTenantId,
            'broker_copy_id'         => $copyId,
            'sender_id'              => $sender->id,
            'sender_name'            => 'Sender',
            'receiver_id'            => $receiver->id,
            'receiver_name'          => 'Receiver',
            'related_listing_id'     => null,
            'listing_title'          => null,
            'copy_reason'            => 'first_contact',
            'target_message_body'    => 'Message',
            'target_message_sent_at' => now()->subDay(),
            'conversation_snapshot'  => '[]',
            'decision'               => 'approved',
            'decided_by'             => $admin->id,
            'decided_by_name'        => 'Admin',
            'decided_at'             => now(),
            'created_at'             => now(),
        ]);

        \App\Core\TenantContext::setById(999);
        Sanctum::actingAs($adminB);

        $response = $this->withHeaders(['X-Tenant-ID' => '999'])
            ->getJson("/api/v2/admin/broker/archives/{$archiveId}");

        $response->assertStatus(404);
        \App\Core\TenantContext::setById($this->testTenantId);
    }

    // ================================================================
    // SAVE CONFIGURATION — POST /v2/admin/broker/configuration
    // ================================================================

    public function test_save_configuration_succeeds_as_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/broker/configuration', [
            'retention_days'          => 120,
            'new_member_monitoring_days' => 45,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.retention_days', 120);

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id'   => $this->testTenantId,
            'setting_key' => 'broker_config',
        ]);
    }

    public function test_save_configuration_returns_403_when_broker_submits_admin_only_keys(): void
    {
        // Create a user with broker role
        $broker = User::factory()->forTenant($this->testTenantId)->create(['role' => 'broker']);
        Sanctum::actingAs($broker);

        // broker_messaging_enabled is an admin-only key
        $response = $this->apiPost('/v2/admin/broker/configuration', [
            'broker_messaging_enabled' => false,
        ]);

        $response->assertStatus(403);
    }

    public function test_save_configuration_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/broker/configuration', [
            'retention_days' => 60,
        ]);

        $response->assertStatus(403);
    }

    // ================================================================
    // CROSS-TENANT / SUPER-ADMIN
    // ================================================================

    public function test_super_admin_can_read_exchanges_for_specific_tenant(): void
    {
        // Create a platform super-admin (role = super_admin)
        $superAdmin = User::factory()->forTenant($this->testTenantId)->create([
            'role'           => 'super_admin',
            'is_super_admin' => true,
        ]);
        Sanctum::actingAs($superAdmin);

        // Create an exchange on the test tenant
        $requester = User::factory()->forTenant($this->testTenantId)->create();
        $provider = User::factory()->forTenant($this->testTenantId)->create();
        DB::table('exchange_requests')->insert([
            'tenant_id'      => $this->testTenantId,
            'requester_id'   => $requester->id,
            'provider_id'    => $provider->id,
            'proposed_hours' => 1.0,
            'status'         => 'pending',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Super admin requests tenant 2's data explicitly
        $response = $this->withHeaders(['X-Tenant-ID' => (string) $this->testTenantId])
            ->getJson("/api/v2/admin/broker/exchanges?tenant_id={$this->testTenantId}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_non_super_admin_cannot_override_tenant_via_query_param(): void
    {
        // Regular admin of tenant 2
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();

        // Create an exchange on tenant 999
        $requesterB = User::factory()->forTenant(999)->create();
        $providerB = User::factory()->forTenant(999)->create();
        $exchangeIdB = DB::table('exchange_requests')->insertGetId([
            'tenant_id'      => 999,
            'requester_id'   => $requesterB->id,
            'provider_id'    => $providerB->id,
            'proposed_hours' => 1.0,
            'status'         => 'pending',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        Sanctum::actingAs($admin);

        // Try to read tenant 999's exchange — non-super-admin gets own tenant data only
        $response = $this->apiGet("/v2/admin/broker/exchanges?tenant_id=999");

        $response->assertStatus(200);
        // The response should not contain the exchange from tenant 999
        $data = $response->json('data');
        $ids = collect($data)->pluck('id')->toArray();
        $this->assertNotContains($exchangeIdB, $ids);
    }
}
