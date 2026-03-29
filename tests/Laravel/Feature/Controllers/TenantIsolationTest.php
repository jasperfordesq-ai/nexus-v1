<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Cross-cutting tenant isolation tests.
 *
 * Verifies that tenant scoping works correctly across multiple modules.
 * Each test creates data in tenant 999 and verifies that a user
 * authenticated in tenant 2 cannot access it.
 *
 * These are integration tests that exercise the full middleware + controller
 * + service + model stack.
 */
class TenantIsolationTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ================================================================
    // LISTINGS — Show endpoint isolation
    // ================================================================

    public function test_cannot_view_single_listing_from_other_tenant(): void
    {
        $this->authenticatedUser();

        $otherUser = User::factory()->forTenant(999)->create();
        $otherListing = Listing::factory()->forTenant(999)->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->apiGet("/v2/listings/{$otherListing->id}");

        $response->assertStatus(404);
    }

    public function test_can_view_own_tenant_listing(): void
    {
        $user = $this->authenticatedUser();
        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
        ]);

        $response = $this->apiGet("/v2/listings/{$listing->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // LISTINGS — Index endpoint isolation
    // ================================================================

    public function test_listings_index_excludes_other_tenant_data(): void
    {
        $user = $this->authenticatedUser();

        // Create listings in both tenants
        Listing::factory()->forTenant($this->testTenantId)->count(2)->create([
            'user_id' => $user->id,
        ]);

        $otherUser = User::factory()->forTenant(999)->create();
        Listing::factory()->forTenant(999)->count(3)->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->apiGet('/v2/listings');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertIsArray($data);

        // All returned listings must belong to our tenant
        foreach ($data as $listing) {
            if (isset($listing['tenant_id'])) {
                $this->assertEquals(
                    $this->testTenantId,
                    $listing['tenant_id'],
                    'Listing from wrong tenant leaked through'
                );
            }
        }
    }

    // ================================================================
    // WALLET — Transaction isolation
    // ================================================================

    public function test_cannot_view_transaction_from_other_tenant(): void
    {
        $this->authenticatedUser();

        $otherTransaction = Transaction::factory()->forTenant(999)->create([
            'status' => 'completed',
        ]);

        $response = $this->apiGet("/v2/wallet/transactions/{$otherTransaction->id}");

        $response->assertStatus(404);
    }

    // ================================================================
    // USERS — Profile isolation
    // ================================================================

    public function test_cannot_view_profile_of_user_in_other_tenant(): void
    {
        $this->authenticatedUser();

        $otherUser = User::factory()->forTenant(999)->create();

        $response = $this->apiGet("/v2/users/{$otherUser->id}");

        // Should be 404 (not found in this tenant) or 403 (cross-tenant blocked)
        $this->assertContains(
            $response->getStatusCode(),
            [403, 404],
            'Cross-tenant profile access should be blocked'
        );
    }

    public function test_can_view_profile_of_user_in_same_tenant(): void
    {
        $this->authenticatedUser();
        $otherUser = User::factory()->forTenant($this->testTenantId)->create();

        $response = $this->apiGet("/v2/users/{$otherUser->id}");

        // Should return 200 or at minimum not a 404 for wrong tenant
        $this->assertContains($response->getStatusCode(), [200, 301, 302]);
    }
}
