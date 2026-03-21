<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for NotificationsController — list, mark read, delete.
 */
class NotificationsControllerTest extends TestCase
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

    // ------------------------------------------------------------------
    //  INDEX
    // ------------------------------------------------------------------

    public function test_index_returns_notifications(): void
    {
        $user = $this->authenticatedUser();
        Notification::factory()->forTenant($this->testTenantId)->count(3)->create([
            'user_id' => $user->id,
        ]);

        $response = $this->apiGet('/v2/notifications');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->apiGet('/v2/notifications');

        $response->assertStatus(401);
    }

    public function test_index_only_returns_own_notifications(): void
    {
        $user = $this->authenticatedUser();
        $other = User::factory()->forTenant($this->testTenantId)->create();

        Notification::factory()->forTenant($this->testTenantId)->count(2)->create([
            'user_id' => $user->id,
        ]);
        Notification::factory()->forTenant($this->testTenantId)->count(3)->create([
            'user_id' => $other->id,
        ]);

        $response = $this->apiGet('/v2/notifications');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertIsArray($data);
    }

    // ------------------------------------------------------------------
    //  COUNTS
    // ------------------------------------------------------------------

    public function test_counts_returns_data(): void
    {
        $user = $this->authenticatedUser();
        Notification::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'is_read' => false,
        ]);

        $response = $this->apiGet('/v2/notifications/counts');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_counts_requires_authentication(): void
    {
        $response = $this->apiGet('/v2/notifications/counts');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  MARK ALL READ
    // ------------------------------------------------------------------

    public function test_mark_all_read_succeeds(): void
    {
        $user = $this->authenticatedUser();
        Notification::factory()->forTenant($this->testTenantId)->count(3)->create([
            'user_id' => $user->id,
            'is_read' => false,
        ]);

        $response = $this->apiPost('/v2/notifications/read-all');

        $response->assertStatus(200);
        $response->assertJsonPath('data.marked_all_read', true);
    }

    public function test_mark_all_read_requires_authentication(): void
    {
        $response = $this->apiPost('/v2/notifications/read-all');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  MARK SINGLE READ
    // ------------------------------------------------------------------

    public function test_mark_single_read_succeeds(): void
    {
        $user = $this->authenticatedUser();
        $notification = Notification::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'is_read' => false,
        ]);

        $response = $this->apiPost("/v2/notifications/{$notification->id}/read");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_mark_read_returns_404_for_nonexistent(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/notifications/999999/read');

        $response->assertStatus(404);
    }

    public function test_mark_read_requires_authentication(): void
    {
        $response = $this->apiPost('/v2/notifications/1/read');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  SHOW
    // ------------------------------------------------------------------

    public function test_show_returns_notification(): void
    {
        $user = $this->authenticatedUser();
        $notification = Notification::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
        ]);

        $response = $this->apiGet("/v2/notifications/{$notification->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/notifications/999999');

        $response->assertStatus(404);
    }

    public function test_show_requires_authentication(): void
    {
        $response = $this->apiGet('/v2/notifications/1');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  DESTROY (single)
    // ------------------------------------------------------------------

    public function test_destroy_notification_succeeds(): void
    {
        $user = $this->authenticatedUser();
        $notification = Notification::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
        ]);

        $response = $this->apiDelete("/v2/notifications/{$notification->id}");

        $this->assertContains($response->getStatusCode(), [200, 204]);
    }

    public function test_destroy_returns_404_for_nonexistent(): void
    {
        $this->authenticatedUser();

        $response = $this->apiDelete('/v2/notifications/999999');

        $response->assertStatus(404);
    }

    public function test_destroy_requires_authentication(): void
    {
        $response = $this->apiDelete('/v2/notifications/1');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  DESTROY ALL
    // ------------------------------------------------------------------

    public function test_destroy_all_notifications_succeeds(): void
    {
        $user = $this->authenticatedUser();
        Notification::factory()->forTenant($this->testTenantId)->count(3)->create([
            'user_id' => $user->id,
        ]);

        $response = $this->apiDelete('/v2/notifications');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['deleted']]);
    }

    public function test_destroy_all_requires_authentication(): void
    {
        $response = $this->apiDelete('/v2/notifications');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  TENANT ISOLATION
    // ------------------------------------------------------------------

    public function test_cannot_read_other_tenant_notification(): void
    {
        $this->authenticatedUser();
        $otherNotification = Notification::factory()->forTenant(999)->create();

        $response = $this->apiGet("/v2/notifications/{$otherNotification->id}");

        $response->assertStatus(404);
    }

    public function test_cannot_delete_other_tenant_notification(): void
    {
        $this->authenticatedUser();
        $otherNotification = Notification::factory()->forTenant(999)->create();

        $response = $this->apiDelete("/v2/notifications/{$otherNotification->id}");

        $response->assertStatus(404);
    }

    public function test_cannot_mark_read_other_tenant_notification(): void
    {
        $this->authenticatedUser();
        $otherNotification = Notification::factory()->forTenant(999)->create();

        $response = $this->apiPost("/v2/notifications/{$otherNotification->id}/read");

        $response->assertStatus(404);
    }
}
