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
 * Feature tests for MetricsController — frontend performance metrics.
 */
class MetricsControllerTest extends TestCase
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

    // ------------------------------------------------------------------
    //  POST /v2/metrics
    // ------------------------------------------------------------------

    public function test_store_requires_auth(): void
    {
        $response = $this->apiPost('/v2/metrics', [
            'name' => 'page_load',
            'value' => 1200,
        ]);

        $response->assertStatus(401);
    }

    public function test_store_accepts_metrics(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/metrics', [
            'name' => 'page_load',
            'value' => 1200,
            'page' => '/dashboard',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201, 204]);
    }

    // ------------------------------------------------------------------
    //  GET /v2/metrics/summary
    // ------------------------------------------------------------------

    public function test_summary_requires_auth(): void
    {
        $response = $this->apiGet('/v2/metrics/summary');

        $response->assertStatus(401);
    }

    public function test_summary_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/metrics/summary');

        $response->assertStatus(200);
    }
}
