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
 * Feature tests for PostAnalyticsController — feed post analytics.
 */
class PostAnalyticsControllerTest extends TestCase
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

    public function test_record_view_requires_auth(): void
    {
        $response = $this->apiPost('/v2/feed/posts/1/view');

        $response->assertStatus(401);
    }

    public function test_analytics_requires_auth(): void
    {
        $response = $this->apiGet('/v2/feed/posts/1/analytics');

        $response->assertStatus(401);
    }

    public function test_analytics_authenticated_smoke(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/feed/posts/1/analytics');

        $this->assertLessThan(500, $response->status());
    }
}
