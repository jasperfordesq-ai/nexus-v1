<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Authorization contract for the match-approval endpoints shared between the
 * admin panel and the broker panel (/broker/match-approvals).
 *
 * Reviewing smart-match proposals is a core broker duty, so the five approval
 * endpoints under /v2/admin/matching/approvals* are broker-or-admin. This test
 * pins the security boundary:
 *   - brokers CAN list, view, approve and reject match approvals
 *   - brokers CANNOT review a match they are a party to (self-dealing guard,
 *     mirroring the adjust-balance guard)
 *   - matching configuration / cache stay admin-only
 *   - regular members are rejected outright
 * See routes/api.php (matching approvals broker-or-admin group) and
 * AdminMatchingController::guardBrokerNotParty().
 */
class BrokerMatchApprovalAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private function broker(): User
    {
        return User::factory()->forTenant($this->testTenantId)->create(['role' => 'broker']);
    }

    private function admin(): User
    {
        return User::factory()->forTenant($this->testTenantId)->admin()->create();
    }

    private function member(array $attrs = []): User
    {
        return User::factory()->forTenant($this->testTenantId)->create($attrs);
    }

    /**
     * Insert a pending match_approvals row between $matchedUser and the owner
     * of a fresh listing. Returns [approvalId, matchedUser, owner].
     */
    private function pendingApproval(?User $matchedUser = null, ?User $owner = null): array
    {
        $matchedUser ??= $this->member();
        $owner ??= $this->member();

        $listing = Listing::factory()->forTenant($this->testTenantId)->offer()->create([
            'user_id' => $owner->id,
        ]);

        $id = DB::table('match_approvals')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $matchedUser->id,
            'listing_id' => $listing->id,
            'listing_owner_id' => $owner->id,
            'match_score' => 82.50,
            'match_type' => 'one_way',
            'match_reasons' => json_encode(['Category match', 'Nearby']),
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        return [$id, $matchedUser, $owner];
    }

    // ================================================================
    // Brokers CAN — reviewing matches is a core broker duty
    // ================================================================

    public function test_broker_can_list_match_approvals(): void
    {
        $this->pendingApproval();
        Sanctum::actingAs($this->broker());

        $response = $this->apiGet('/v2/admin/matching/approvals');

        $response->assertStatus(200);
    }

    public function test_broker_can_view_approval_stats(): void
    {
        Sanctum::actingAs($this->broker());

        $response = $this->apiGet('/v2/admin/matching/approvals/stats');

        $response->assertStatus(200);
    }

    public function test_broker_can_view_a_match_approval(): void
    {
        [$id] = $this->pendingApproval();
        Sanctum::actingAs($this->broker());

        $response = $this->apiGet("/v2/admin/matching/approvals/{$id}");

        $response->assertStatus(200);
    }

    public function test_broker_can_approve_a_match_between_other_members(): void
    {
        [$id] = $this->pendingApproval();
        Sanctum::actingAs($this->broker());

        $response = $this->apiPost("/v2/admin/matching/approvals/{$id}/approve");

        $response->assertStatus(200);
        $this->assertSame(
            'approved',
            DB::table('match_approvals')->where('id', $id)->value('status'),
            'Approval row must flip to approved.'
        );
    }

    public function test_broker_can_reject_a_match_with_reason(): void
    {
        [$id] = $this->pendingApproval();
        Sanctum::actingAs($this->broker());

        $response = $this->apiPost("/v2/admin/matching/approvals/{$id}/reject", [
            'reason' => 'Members are too far apart for this service.',
        ]);

        $response->assertStatus(200);
        $this->assertSame(
            'rejected',
            DB::table('match_approvals')->where('id', $id)->value('status')
        );
    }

    public function test_reject_without_reason_is_a_validation_error(): void
    {
        [$id] = $this->pendingApproval();
        Sanctum::actingAs($this->broker());

        $response = $this->apiPost("/v2/admin/matching/approvals/{$id}/reject", []);

        $response->assertStatus(422);
    }

    // ================================================================
    // Brokers CANNOT — self-dealing guard
    // ================================================================

    public function test_broker_cannot_approve_a_match_they_are_the_matched_member_of(): void
    {
        $broker = $this->broker();
        [$id] = $this->pendingApproval(matchedUser: $broker);
        Sanctum::actingAs($broker);

        $response = $this->apiPost("/v2/admin/matching/approvals/{$id}/approve");

        $response->assertStatus(403);
        $this->assertSame(
            'pending',
            DB::table('match_approvals')->where('id', $id)->value('status'),
            'Self-guarded approval must stay pending.'
        );
    }

    public function test_broker_cannot_approve_a_match_for_their_own_listing(): void
    {
        $broker = $this->broker();
        [$id] = $this->pendingApproval(owner: $broker);
        Sanctum::actingAs($broker);

        $response = $this->apiPost("/v2/admin/matching/approvals/{$id}/approve");

        $response->assertStatus(403);
    }

    public function test_broker_cannot_reject_a_match_they_are_a_party_to(): void
    {
        $broker = $this->broker();
        [$id] = $this->pendingApproval(matchedUser: $broker);
        Sanctum::actingAs($broker);

        $response = $this->apiPost("/v2/admin/matching/approvals/{$id}/reject", [
            'reason' => 'Trying to bury my own match record.',
        ]);

        $response->assertStatus(403);
        $this->assertSame(
            'pending',
            DB::table('match_approvals')->where('id', $id)->value('status')
        );
    }

    // ================================================================
    // Admins retain full latitude (the guard only restricts brokers)
    // ================================================================

    public function test_admin_can_approve_a_match_they_are_a_party_to(): void
    {
        $admin = $this->admin();
        [$id] = $this->pendingApproval(matchedUser: $admin);
        Sanctum::actingAs($admin);

        $response = $this->apiPost("/v2/admin/matching/approvals/{$id}/approve");

        $response->assertStatus(200);
    }

    // ================================================================
    // Matching configuration / cache stay admin-only
    // ================================================================

    public function test_broker_cannot_read_matching_config(): void
    {
        Sanctum::actingAs($this->broker());

        $response = $this->apiGet('/v2/admin/matching/config');

        $response->assertStatus(403);
    }

    public function test_broker_cannot_update_matching_config(): void
    {
        Sanctum::actingAs($this->broker());

        $response = $this->apiPut('/v2/admin/matching/config', ['min_match_score' => 1]);

        $response->assertStatus(403);
    }

    public function test_broker_cannot_clear_match_cache(): void
    {
        Sanctum::actingAs($this->broker());

        $response = $this->apiPost('/v2/admin/matching/cache/clear');

        $response->assertStatus(403);
    }

    // ================================================================
    // Regular members are rejected outright
    // ================================================================

    public function test_regular_member_cannot_list_match_approvals(): void
    {
        Sanctum::actingAs($this->member());

        $response = $this->apiGet('/v2/admin/matching/approvals');

        $response->assertStatus(403);
    }

    public function test_regular_member_cannot_approve_a_match(): void
    {
        [$id] = $this->pendingApproval();
        Sanctum::actingAs($this->member());

        $response = $this->apiPost("/v2/admin/matching/approvals/{$id}/approve");

        $response->assertStatus(403);
    }
}
