<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Smoke tests for AdminFederationWebhooksController.
 */
class AdminFederationWebhooksControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_index_requires_auth(): void
    {
        $this->apiGet('/v2/admin/federation/webhooks')->assertStatus(401);
    }

    public function test_index_rejects_non_admin(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->create());
        $this->apiGet('/v2/admin/federation/webhooks')->assertStatus(403);
    }

    public function test_index_allows_admin(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->admin()->create());
        $response = $this->apiGet('/v2/admin/federation/webhooks');
        // Not unauthorized (403) — smoke only; 500 acceptable if deps missing in test env
        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    public function test_store_requires_auth(): void
    {
        $this->apiPost('/v2/admin/federation/webhooks', [])->assertStatus(401);
    }

    public function test_logs_requires_auth(): void
    {
        $this->apiGet('/v2/admin/federation/webhooks/1/logs')->assertStatus(401);
    }

    public function test_test_requires_auth(): void
    {
        $this->apiPost('/v2/admin/federation/webhooks/1/test', [])->assertStatus(401);
    }

    public function test_retry_requires_auth(): void
    {
        $this->apiPost('/v2/admin/federation/webhook-logs/1/retry', [])->assertStatus(401);
    }

    public function test_destroy_requires_auth(): void
    {
        $this->apiDelete('/v2/admin/federation/webhooks/1')->assertStatus(401);
    }
}
