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
 * Feature smoke tests for GroupAnalyticsController.
 */
class GroupAnalyticsControllerTest extends TestCase
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

    public function test_dashboard_requires_auth(): void
    {
        $this->apiGet('/v2/groups/1/analytics')->assertStatus(401);
    }

    public function test_growth_requires_auth(): void
    {
        $this->apiGet('/v2/groups/1/analytics/growth')->assertStatus(401);
    }

    public function test_engagement_requires_auth(): void
    {
        $this->apiGet('/v2/groups/1/analytics/engagement')->assertStatus(401);
    }

    public function test_contributors_requires_auth(): void
    {
        $this->apiGet('/v2/groups/1/analytics/contributors')->assertStatus(401);
    }

    public function test_retention_requires_auth(): void
    {
        $this->apiGet('/v2/groups/1/analytics/retention')->assertStatus(401);
    }

    public function test_comparative_requires_auth(): void
    {
        $this->apiGet('/v2/groups/1/analytics/comparative')->assertStatus(401);
    }

    public function test_export_members_requires_auth(): void
    {
        $this->apiGet('/v2/groups/1/analytics/export/members')->assertStatus(401);
    }

    public function test_export_activity_requires_auth(): void
    {
        $this->apiGet('/v2/groups/1/analytics/export/activity')->assertStatus(401);
    }

    public function test_dashboard_returns_non_5xx_when_authenticated(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/groups/1/analytics');
        $this->assertTrue($response->status() < 500, "Got 5xx: {$response->status()}");
    }

    public function test_growth_returns_non_5xx_when_authenticated(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/groups/1/analytics/growth');
        $this->assertTrue($response->status() < 500, "Got 5xx: {$response->status()}");
    }
}
