<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

/**
 * Smoke tests for MarketplaceGroupController.
 */
class MarketplaceGroupControllerTest extends TestCase
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

    public function test_listings_requires_auth(): void
    {
        $response = $this->apiGet('/v2/marketplace/groups/1/listings');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_stats_requires_auth(): void
    {
        $response = $this->apiGet('/v2/marketplace/groups/1/stats');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_listings_authenticated_smoke(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/marketplace/groups/1/listings');
        $this->assertLessThan(500, $response->status());
    }

    public function test_stats_authenticated_smoke(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/marketplace/groups/1/stats');
        $this->assertLessThan(500, $response->status());
    }
}
