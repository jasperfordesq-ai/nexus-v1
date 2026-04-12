<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Models\MarketplaceListing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for MarketplaceAiController.
 *
 * Covers the AI auto-reply endpoint for marketplace listings:
 *   POST /v2/marketplace/listings/{id}/auto-reply
 *
 * Owner-only gating and input validation are exercised without
 * touching the OpenAI service (we stop at the validation/gating layer).
 */
class MarketplaceAiControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    private function createListing(int $userId, int $tenantId): int
    {
        return (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'title' => 'Test Widget',
            'description' => 'A widget for sale',
            'price' => 10.00,
            'price_currency' => 'EUR',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_auto_reply_requires_auth(): void
    {
        $response = $this->apiPost('/v2/marketplace/listings/1/auto-reply', ['message' => 'hello there']);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_auto_reply_returns_404_for_missing_listing(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/marketplace/listings/9999999/auto-reply', [
            'message' => 'Is this still available?',
        ]);

        // 404 if feature enabled, 403 if feature gated off
        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    public function test_auto_reply_forbidden_for_non_owner(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create();
        $listingId = $this->createListing($owner->id, $this->testTenantId);

        $other = $this->authenticatedUser();
        $this->assertNotSame($owner->id, $other->id);

        $response = $this->apiPost("/v2/marketplace/listings/{$listingId}/auto-reply", [
            'message' => 'Hi, is this available?',
        ]);

        $this->assertContains($response->getStatusCode(), [403]);
    }

    public function test_auto_reply_validates_message_length(): void
    {
        $user = $this->authenticatedUser();
        $listingId = $this->createListing($user->id, $this->testTenantId);

        $response = $this->apiPost("/v2/marketplace/listings/{$listingId}/auto-reply", [
            'message' => 'hi', // too short, min:5
        ]);

        $this->assertContains($response->getStatusCode(), [403, 422]);
    }

    public function test_auto_reply_rejects_missing_message(): void
    {
        $user = $this->authenticatedUser();
        $listingId = $this->createListing($user->id, $this->testTenantId);

        $response = $this->apiPost("/v2/marketplace/listings/{$listingId}/auto-reply", []);

        $this->assertContains($response->getStatusCode(), [403, 422]);
    }
}
