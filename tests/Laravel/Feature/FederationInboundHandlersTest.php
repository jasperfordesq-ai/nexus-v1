<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature;

use App\Core\TenantContext;
use App\Events\FederatedCommunityEventReceived;
use App\Events\FederatedConnectionReceived;
use App\Events\FederatedGroupReceived;
use App\Events\FederatedListingReceived;
use App\Events\FederatedMemberUpdated;
use App\Events\FederatedReviewReceived;
use App\Events\FederatedVolunteeringReceived;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

/**
 * Feature tests for the expanded inbound federation webhook handlers.
 *
 * Exercises all 8 new entity handlers (review, listing, event, group,
 * group membership, connection, volunteering, member sync) via the public
 * POST /api/v2/federation/external/webhooks/receive endpoint — using API-key
 * Bearer auth (signing_secret stored in plaintext for test convenience).
 */
class FederationInboundHandlersTest extends TestCase
{
    use DatabaseTransactions;

    private const WEBHOOK_URL = '/api/v2/federation/external/webhooks/receive';

    private int $partnerId;
    private string $partnerToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure shadow tables exist for the test run (fallback for envs where
        // migrations aren't run before each test)
        foreach ([
            'federation_events',
            'federation_groups',
            'federation_inbound_connections',
            'federation_volunteering',
            'federation_listings',
            'federation_members',
        ] as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped("Shadow table {$table} missing — run migrations.");
            }
        }

        $this->partnerToken = 'test-token-' . bin2hex(random_bytes(8));

        $this->partnerId = (int) DB::table('federation_external_partners')->insertGetId([
            'tenant_id'          => $this->testTenantId,
            'name'               => 'Test Inbound Partner',
            'base_url'           => 'https://inbound-test.example',
            'api_path'           => '/api/v1/federation',
            'signing_secret'     => $this->partnerToken, // plaintext — decryptSecret falls back
            'status'             => 'active',
            'allow_messaging'    => 1,
            'allow_transactions' => 1,
            'allow_events'       => 1,
            'allow_groups'       => 1,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        TenantContext::setById($this->testTenantId);
    }

    /**
     * Post a webhook payload with the partner's Bearer token.
     *
     * @param array<string, mixed> $data
     */
    private function postWebhook(string $event, array $data, ?string $token = null): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . ($token ?? $this->partnerToken),
            'Content-Type'  => 'application/json',
        ])->postJson(self::WEBHOOK_URL, [
            'event' => $event,
            'data'  => $data,
        ]);
    }

    // ------------------------------------------------------------------
    // Auth gate
    // ------------------------------------------------------------------

    public function test_unauthorized_without_bearer_token(): void
    {
        $response = $this->postJson(self::WEBHOOK_URL, [
            'event' => 'review.created',
            'data'  => ['external_id' => 'r1', 'rating' => 5, 'receiver_id' => 1],
        ]);
        $response->assertStatus(401);
    }

    public function test_unauthorized_with_wrong_bearer_token(): void
    {
        $response = $this->postWebhook('review.created', ['external_id' => 'r1'], 'totally-wrong-token');
        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // 1. Review inbound
    // ------------------------------------------------------------------

    public function test_review_created_persists_and_dispatches_event(): void
    {
        Event::fake([FederatedReviewReceived::class]);

        $receiver = User::factory()->forTenant($this->testTenantId)->create();
        // Federated reviewer must exist as a local user row (schema has FK
        // reviews.reviewer_id → users.id). In production the identity-sync
        // subsystem pre-provisions a stub user for external reviewers.
        $reviewerStub = User::factory()->forTenant(999)->create();

        $response = $this->postWebhook('review.created', [
            'external_id'          => 'ext-review-123',
            'rating'               => 4,
            'receiver_id'          => $receiver->id,
            'reviewer_external_id' => $reviewerStub->id,
            'reviewer_tenant_id'   => 999,
            'comment'              => 'Great federated work',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.result.status', 'handled');

        $this->assertDatabaseHas('reviews', [
            'tenant_id'   => $this->testTenantId,
            'receiver_id' => $receiver->id,
            'review_type' => 'federated',
            'rating'      => 4,
        ]);

        Event::assertDispatched(FederatedReviewReceived::class);
    }

    public function test_review_missing_fields_returns_400(): void
    {
        $response = $this->postWebhook('review.created', [
            // missing external_id, rating, receiver_id
        ]);
        $response->assertStatus(400);
    }

    public function test_review_cannot_target_foreign_tenant_user(): void
    {
        // Foreign user on tenant 999
        $foreignUser = User::factory()->forTenant(999)->create();

        $response = $this->postWebhook('review.created', [
            'external_id'          => 'ext-review-xtenant',
            'rating'               => 5,
            'receiver_id'          => $foreignUser->id,
            'reviewer_external_id' => 1,
        ]);
        $response->assertStatus(400);
    }

    // ------------------------------------------------------------------
    // 2. Listing inbound
    // ------------------------------------------------------------------

    public function test_listing_created_persists_and_dispatches_event(): void
    {
        Event::fake([FederatedListingReceived::class]);

        $response = $this->postWebhook('listing.created', [
            'external_id' => 'ext-list-1',
            'title'       => 'Help me move a sofa',
            'description' => 'Need 2 hours',
            'type'        => 'inquiry',
            'category'    => 'help',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.result.status', 'handled');

        $this->assertDatabaseHas('federation_listings', [
            'tenant_id'           => $this->testTenantId,
            'external_partner_id' => $this->partnerId,
            'external_id'         => 'ext-list-1',
            'title'               => 'Help me move a sofa',
        ]);

        Event::assertDispatched(FederatedListingReceived::class);
    }

    public function test_listing_updated_is_upsert(): void
    {
        $this->postWebhook('listing.created', [
            'external_id' => 'ext-list-upsert',
            'title'       => 'Original',
        ])->assertStatus(200);

        $this->postWebhook('listing.updated', [
            'external_id' => 'ext-list-upsert',
            'title'       => 'Updated Title',
        ])->assertStatus(200);

        $count = DB::table('federation_listings')
            ->where('external_partner_id', $this->partnerId)
            ->where('external_id', 'ext-list-upsert')
            ->count();
        $this->assertSame(1, $count);

        $this->assertDatabaseHas('federation_listings', [
            'external_id' => 'ext-list-upsert',
            'title'       => 'Updated Title',
        ]);
    }

    public function test_listing_missing_title_returns_400(): void
    {
        $response = $this->postWebhook('listing.created', ['external_id' => 'only-id']);
        $response->assertStatus(400);
    }

    // ------------------------------------------------------------------
    // 3. Community event inbound
    // ------------------------------------------------------------------

    public function test_event_created_persists_and_dispatches_event(): void
    {
        Event::fake([FederatedCommunityEventReceived::class]);

        $response = $this->postWebhook('event.created', [
            'external_id' => 'ext-evt-1',
            'title'       => 'Timebank Picnic',
            'starts_at'   => '2026-05-01 12:00:00',
            'ends_at'     => '2026-05-01 16:00:00',
            'location'    => 'Phoenix Park',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('federation_events', [
            'external_partner_id' => $this->partnerId,
            'external_id'         => 'ext-evt-1',
            'tenant_id'           => $this->testTenantId,
        ]);

        Event::assertDispatched(FederatedCommunityEventReceived::class);
    }

    public function test_event_missing_fields_returns_400(): void
    {
        $this->postWebhook('event.created', ['title' => 'No external id'])
            ->assertStatus(400);
    }

    // ------------------------------------------------------------------
    // 4. Group inbound
    // ------------------------------------------------------------------

    public function test_group_created_persists_and_dispatches_event(): void
    {
        Event::fake([FederatedGroupReceived::class]);

        $response = $this->postWebhook('group.created', [
            'external_id'  => 'ext-grp-1',
            'name'         => 'Local Gardeners',
            'privacy'      => 'public',
            'member_count' => 12,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('federation_groups', [
            'external_partner_id' => $this->partnerId,
            'external_id'         => 'ext-grp-1',
            'name'                => 'Local Gardeners',
            'tenant_id'           => $this->testTenantId,
        ]);

        Event::assertDispatched(FederatedGroupReceived::class);
    }

    public function test_group_missing_name_returns_400(): void
    {
        $this->postWebhook('group.created', ['external_id' => 'g'])
            ->assertStatus(400);
    }

    // ------------------------------------------------------------------
    // 5. Group member joined
    // ------------------------------------------------------------------

    public function test_group_member_joined_increments_count(): void
    {
        $this->postWebhook('group.created', [
            'external_id'  => 'ext-grp-memjoin',
            'name'         => 'Chess Club',
            'member_count' => 5,
        ])->assertStatus(200);

        Event::fake([FederatedGroupReceived::class]);

        $response = $this->postWebhook('group.member_joined', [
            'external_id'      => 'ext-grp-memjoin',
            'external_user_id' => 'user-42',
        ]);
        $response->assertStatus(200);

        $group = DB::table('federation_groups')
            ->where('external_partner_id', $this->partnerId)
            ->where('external_id', 'ext-grp-memjoin')
            ->first();
        $this->assertSame(6, (int) $group->member_count);

        Event::assertDispatched(FederatedGroupReceived::class);
    }

    public function test_group_member_joined_unknown_group_returns_400(): void
    {
        $this->postWebhook('group.member_joined', [
            'external_id'      => 'does-not-exist',
            'external_user_id' => 'user-1',
        ])->assertStatus(400);
    }

    // ------------------------------------------------------------------
    // 6. Connection inbound
    // ------------------------------------------------------------------

    public function test_connection_requested_persists_and_dispatches_event(): void
    {
        Event::fake([FederatedConnectionReceived::class]);

        $localUser = User::factory()->forTenant($this->testTenantId)->create();

        $response = $this->postWebhook('connection.requested', [
            'local_user_id'    => $localUser->id,
            'external_user_id' => 'ext-usr-7',
            'message'          => 'Hi, let\'s connect!',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('federation_inbound_connections', [
            'external_partner_id' => $this->partnerId,
            'local_user_id'       => $localUser->id,
            'external_user_id'    => 'ext-usr-7',
            'status'              => 'pending',
            'tenant_id'           => $this->testTenantId,
        ]);

        Event::assertDispatched(FederatedConnectionReceived::class);
    }

    public function test_connection_accepted_sets_accepted_status(): void
    {
        $localUser = User::factory()->forTenant($this->testTenantId)->create();

        $this->postWebhook('connection.accepted', [
            'local_user_id'    => $localUser->id,
            'external_user_id' => 'ext-usr-8',
        ])->assertStatus(200);

        $this->assertDatabaseHas('federation_inbound_connections', [
            'local_user_id'    => $localUser->id,
            'external_user_id' => 'ext-usr-8',
            'status'           => 'accepted',
        ]);
    }

    public function test_connection_cannot_target_foreign_tenant_user(): void
    {
        $foreignUser = User::factory()->forTenant(999)->create();

        $response = $this->postWebhook('connection.requested', [
            'local_user_id'    => $foreignUser->id,
            'external_user_id' => 'x',
        ]);
        $response->assertStatus(400);
    }

    public function test_connection_missing_fields_returns_400(): void
    {
        $this->postWebhook('connection.requested', [])
            ->assertStatus(400);
    }

    // ------------------------------------------------------------------
    // 7. Volunteering inbound
    // ------------------------------------------------------------------

    public function test_volunteering_created_persists_and_dispatches_event(): void
    {
        Event::fake([FederatedVolunteeringReceived::class]);

        $response = $this->postWebhook('volunteering.created', [
            'external_id'     => 'ext-vol-1',
            'title'           => 'Dog walking for seniors',
            'hours_requested' => 2.5,
            'location'        => 'Cork',
            'starts_at'       => '2026-05-10 10:00:00',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('federation_volunteering', [
            'external_partner_id' => $this->partnerId,
            'external_id'         => 'ext-vol-1',
            'title'               => 'Dog walking for seniors',
            'tenant_id'           => $this->testTenantId,
        ]);

        Event::assertDispatched(FederatedVolunteeringReceived::class);
    }

    public function test_volunteering_missing_fields_returns_400(): void
    {
        $this->postWebhook('volunteering.created', ['title' => 'no id'])
            ->assertStatus(400);
    }

    // ------------------------------------------------------------------
    // 8. Member sync inbound
    // ------------------------------------------------------------------

    public function test_member_profile_updated_persists_and_dispatches_event(): void
    {
        Event::fake([FederatedMemberUpdated::class]);

        $response = $this->postWebhook('member.profile_updated', [
            'external_id'  => 'ext-mem-1',
            'username'     => 'alice_remote',
            'display_name' => 'Alice R.',
            'bio'          => 'Federated neighbour',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('federation_members', [
            'external_partner_id' => $this->partnerId,
            'external_id'         => 'ext-mem-1',
            'username'            => 'alice_remote',
            'tenant_id'           => $this->testTenantId,
        ]);

        Event::assertDispatched(FederatedMemberUpdated::class);
    }

    public function test_member_missing_external_id_returns_400(): void
    {
        $this->postWebhook('member.profile_updated', ['username' => 'x'])
            ->assertStatus(400);
    }

    // ------------------------------------------------------------------
    // Cross-tenant isolation
    // ------------------------------------------------------------------

    public function test_partner_for_tenant_A_cannot_inject_into_tenant_B(): void
    {
        // Create a user in foreign tenant 999
        $foreignUser = User::factory()->forTenant(999)->create();

        // Partner is bound to testTenantId — listing should be persisted under
        // testTenantId regardless of anything in the payload
        $this->postWebhook('listing.created', [
            'external_id'     => 'ext-xt-1',
            'title'           => 'Cross-tenant attempt',
            'tenant_id'       => 999, // attempt to poison via payload
            'external_user_id' => (string) $foreignUser->id,
        ])->assertStatus(200);

        // Should be scoped to the partner's real tenant
        $this->assertDatabaseHas('federation_listings', [
            'external_id' => 'ext-xt-1',
            'tenant_id'   => $this->testTenantId,
        ]);
        $this->assertDatabaseMissing('federation_listings', [
            'external_id' => 'ext-xt-1',
            'tenant_id'   => 999,
        ]);
    }

    public function test_webhook_logs_are_recorded_on_success(): void
    {
        $this->postWebhook('listing.created', [
            'external_id' => 'ext-log-1',
            'title'       => 'Logged listing',
        ])->assertStatus(200);

        $this->assertDatabaseHas('federation_external_partner_logs', [
            'partner_id'    => $this->partnerId,
            'response_code' => 200,
            'success'       => 1,
        ]);
    }
}
