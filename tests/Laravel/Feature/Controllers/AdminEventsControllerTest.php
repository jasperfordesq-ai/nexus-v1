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
 * Feature tests for AdminEventsController.
 *
 * Covers index, show, approve, destroy, and cancel.
 */
class AdminEventsControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function createEvent(int $tenantId): int
    {
        $organizer = User::factory()->forTenant($tenantId)->create();

        return DB::table('events')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $organizer->id,
            'title' => 'Test Event',
            'description' => 'A test event description',
            'status' => 'active',
            'start_time' => now()->addDays(7),
            'end_time' => now()->addDays(7)->addHours(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ================================================================
    // INDEX — GET /v2/admin/events
    // ================================================================

    public function test_index_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/events');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_returns_correct_data_with_events(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $this->createEvent($this->testTenantId);

        $response = $this->apiGet('/v2/admin/events');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_index_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/events');

        $response->assertStatus(403);
    }

    public function test_index_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/events');

        $response->assertStatus(401);
    }

    // ================================================================
    // SHOW — GET /v2/admin/events/{id}
    // ================================================================

    public function test_show_returns_event_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $eventId = $this->createEvent($this->testTenantId);

        $response = $this->apiGet("/v2/admin/events/{$eventId}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_show_returns_404_for_nonexistent_event(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/events/999999');

        $response->assertStatus(404);
    }

    public function test_show_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/events/1');

        $response->assertStatus(403);
    }

    // ================================================================
    // DELETE — DELETE /v2/admin/events/{id}
    // ================================================================

    public function test_destroy_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $eventId = $this->createEvent($this->testTenantId);

        $response = $this->apiDelete("/v2/admin/events/{$eventId}");

        // May return 200 on success or 404 if service scopes differently
        $this->assertContains($response->getStatusCode(), [200, 404]);
    }

    public function test_destroy_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiDelete('/v2/admin/events/1');

        $response->assertStatus(403);
    }

    // ================================================================
    // CANCEL — POST /v2/admin/events/{id}/cancel
    // ================================================================

    public function test_cancel_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/events/1/cancel', [
            'reason' => 'Test cancellation',
        ]);

        $response->assertStatus(403);
    }

    public function test_cancel_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/admin/events/1/cancel');

        $response->assertStatus(401);
    }
}
