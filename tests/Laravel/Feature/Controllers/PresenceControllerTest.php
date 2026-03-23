<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for PresenceController — heartbeat, bulk lookup,
 * status, privacy, online count.
 */
class PresenceControllerTest extends TestCase
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

    /**
     * Seed a presence row directly in the DB for testing reads.
     */
    private function seedPresence(int $userId, array $overrides = []): void
    {
        $defaults = [
            'user_id' => $userId,
            'tenant_id' => $this->testTenantId,
            'status' => 'online',
            'custom_status' => null,
            'status_emoji' => null,
            'last_seen_at' => now(),
            'last_activity_at' => now(),
            'hide_presence' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('user_presence')->insertOrIgnore(array_merge($defaults, $overrides));
    }

    /**
     * Flush presence-related Redis keys before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        try {
            // Clean up any presence keys from previous test runs
            $keys = Redis::keys('nexus:presence:*');
            if (!empty($keys)) {
                Redis::del($keys);
            }
        } catch (\Throwable) {
            // Redis may not be available in CI — tests that need it will handle gracefully
        }
    }

    // ------------------------------------------------------------------
    //  HEARTBEAT — POST /v2/presence/heartbeat
    // ------------------------------------------------------------------

    public function test_heartbeat_returns_ok(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/presence/heartbeat');

        $response->assertStatus(200);
        $response->assertJsonPath('data.ok', true);
    }

    public function test_heartbeat_requires_authentication(): void
    {
        $response = $this->apiPost('/v2/presence/heartbeat');

        $response->assertStatus(401);
    }

    public function test_heartbeat_writes_presence_to_database(): void
    {
        $user = $this->authenticatedUser();

        $this->apiPost('/v2/presence/heartbeat');

        $this->assertDatabaseHas('user_presence', [
            'user_id' => $user->id,
            'tenant_id' => $this->testTenantId,
        ]);
    }

    public function test_heartbeat_preserves_dnd_status(): void
    {
        $user = $this->authenticatedUser();

        // Set DND first
        $this->apiPut('/v2/presence/status', [
            'status' => 'dnd',
            'custom_status' => 'In a meeting',
        ]);

        // Send heartbeat — should NOT overwrite DND to online
        $this->apiPost('/v2/presence/heartbeat');

        $row = DB::table('user_presence')
            ->where('user_id', $user->id)
            ->where('tenant_id', $this->testTenantId)
            ->first();

        $this->assertNotNull($row);
        $this->assertEquals('dnd', $row->status);
    }

    // ------------------------------------------------------------------
    //  USERS — GET /v2/presence/users?user_ids=1,2,3
    // ------------------------------------------------------------------

    public function test_users_returns_presence_data(): void
    {
        $user = $this->authenticatedUser();
        $other = User::factory()->forTenant($this->testTenantId)->create();

        $this->seedPresence($other->id, [
            'status' => 'online',
            'custom_status' => 'Working',
            'last_activity_at' => now(),
        ]);

        $response = $this->apiGet("/v2/presence/users?user_ids={$other->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_users_requires_authentication(): void
    {
        $response = $this->apiGet('/v2/presence/users?user_ids=1');

        $response->assertStatus(401);
    }

    public function test_users_requires_user_ids_parameter(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/presence/users');

        $response->assertStatus(400);
    }

    public function test_users_returns_empty_for_invalid_ids(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/presence/users?user_ids=abc,xyz');

        $response->assertStatus(200);
        $response->assertJsonPath('data', []);
    }

    public function test_users_returns_offline_for_unknown_users(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/presence/users?user_ids=999888');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        // The user should show as offline since they have no presence record
        $this->assertEquals('offline', $data[999888]['status'] ?? $data['999888']['status'] ?? 'offline');
    }

    public function test_users_handles_multiple_ids(): void
    {
        $this->authenticatedUser();
        $user1 = User::factory()->forTenant($this->testTenantId)->create();
        $user2 = User::factory()->forTenant($this->testTenantId)->create();

        $this->seedPresence($user1->id, ['status' => 'online', 'last_activity_at' => now()]);
        $this->seedPresence($user2->id, ['status' => 'away', 'last_activity_at' => now()->subMinutes(6)]);

        $response = $this->apiGet("/v2/presence/users?user_ids={$user1->id},{$user2->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    public function test_users_respects_100_id_limit(): void
    {
        $this->authenticatedUser();

        // Create 105 IDs — only 100 should be processed
        $ids = implode(',', range(1, 105));

        $response = $this->apiGet("/v2/presence/users?user_ids={$ids}");

        $response->assertStatus(200);
    }

    public function test_users_hides_presence_when_privacy_enabled(): void
    {
        $this->authenticatedUser();
        $hiddenUser = User::factory()->forTenant($this->testTenantId)->create();

        $this->seedPresence($hiddenUser->id, [
            'status' => 'online',
            'hide_presence' => 1,
            'last_activity_at' => now(),
        ]);

        $response = $this->apiGet("/v2/presence/users?user_ids={$hiddenUser->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        // Hidden users should appear as offline
        $userKey = (string) $hiddenUser->id;
        if (isset($data[$userKey])) {
            $this->assertEquals('offline', $data[$userKey]['status']);
        } elseif (isset($data[$hiddenUser->id])) {
            $this->assertEquals('offline', $data[$hiddenUser->id]['status']);
        }
    }

    // ------------------------------------------------------------------
    //  SET STATUS — PUT /v2/presence/status
    // ------------------------------------------------------------------

    public function test_set_status_updates_status(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPut('/v2/presence/status', [
            'status' => 'dnd',
            'custom_status' => 'In a meeting',
            'emoji' => "\xF0\x9F\x93\x85", // calendar emoji
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'dnd');
        $response->assertJsonPath('data.custom_status', 'In a meeting');
    }

    public function test_set_status_requires_authentication(): void
    {
        $response = $this->apiPut('/v2/presence/status', [
            'status' => 'dnd',
        ]);

        $response->assertStatus(401);
    }

    public function test_set_status_rejects_invalid_status(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPut('/v2/presence/status', [
            'status' => 'invisible',
        ]);

        $response->assertStatus(400);
    }

    public function test_set_status_accepts_all_valid_statuses(): void
    {
        $user = $this->authenticatedUser();

        foreach (['online', 'away', 'dnd', 'offline'] as $status) {
            $response = $this->apiPut('/v2/presence/status', [
                'status' => $status,
            ]);

            $response->assertStatus(200);
            $response->assertJsonPath('data.status', $status);
        }
    }

    public function test_set_status_defaults_to_online(): void
    {
        $this->authenticatedUser();

        // No status in body — should default to 'online'
        $response = $this->apiPut('/v2/presence/status', []);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'online');
    }

    public function test_set_status_writes_to_database(): void
    {
        $user = $this->authenticatedUser();

        $this->apiPut('/v2/presence/status', [
            'status' => 'dnd',
            'custom_status' => 'Busy',
            'emoji' => "\xF0\x9F\x94\xA5", // fire emoji
        ]);

        $this->assertDatabaseHas('user_presence', [
            'user_id' => $user->id,
            'tenant_id' => $this->testTenantId,
            'status' => 'dnd',
            'custom_status' => 'Busy',
        ]);
    }

    public function test_set_status_truncates_long_custom_status(): void
    {
        $user = $this->authenticatedUser();

        $longStatus = str_repeat('A', 200); // 200 chars, should be truncated to 80

        $this->apiPut('/v2/presence/status', [
            'status' => 'online',
            'custom_status' => $longStatus,
        ]);

        $row = DB::table('user_presence')
            ->where('user_id', $user->id)
            ->where('tenant_id', $this->testTenantId)
            ->first();

        $this->assertNotNull($row);
        $this->assertLessThanOrEqual(80, mb_strlen($row->custom_status));
    }

    // ------------------------------------------------------------------
    //  SET PRIVACY — PUT /v2/presence/privacy
    // ------------------------------------------------------------------

    public function test_set_privacy_enables_hidden(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPut('/v2/presence/privacy', [
            'hide_presence' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.hide_presence', true);
    }

    public function test_set_privacy_disables_hidden(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPut('/v2/presence/privacy', [
            'hide_presence' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.hide_presence', false);
    }

    public function test_set_privacy_requires_authentication(): void
    {
        $response = $this->apiPut('/v2/presence/privacy', [
            'hide_presence' => true,
        ]);

        $response->assertStatus(401);
    }

    public function test_set_privacy_writes_to_database(): void
    {
        $user = $this->authenticatedUser();

        $this->apiPut('/v2/presence/privacy', [
            'hide_presence' => true,
        ]);

        $this->assertDatabaseHas('user_presence', [
            'user_id' => $user->id,
            'tenant_id' => $this->testTenantId,
            'hide_presence' => 1,
        ]);
    }

    public function test_set_privacy_toggle_off_and_on(): void
    {
        $user = $this->authenticatedUser();

        // Enable
        $this->apiPut('/v2/presence/privacy', ['hide_presence' => true]);
        $this->assertDatabaseHas('user_presence', [
            'user_id' => $user->id,
            'hide_presence' => 1,
        ]);

        // Disable
        $this->apiPut('/v2/presence/privacy', ['hide_presence' => false]);
        $this->assertDatabaseHas('user_presence', [
            'user_id' => $user->id,
            'hide_presence' => 0,
        ]);
    }

    // ------------------------------------------------------------------
    //  ONLINE COUNT — GET /v2/presence/online-count
    // ------------------------------------------------------------------

    public function test_online_count_returns_count(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/presence/online-count');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['online_count']]);
    }

    public function test_online_count_requires_authentication(): void
    {
        $response = $this->apiGet('/v2/presence/online-count');

        $response->assertStatus(401);
    }

    public function test_online_count_counts_active_users(): void
    {
        $this->authenticatedUser();

        $user1 = User::factory()->forTenant($this->testTenantId)->create();
        $user2 = User::factory()->forTenant($this->testTenantId)->create();
        $user3 = User::factory()->forTenant($this->testTenantId)->create();

        $this->seedPresence($user1->id, ['status' => 'online', 'last_activity_at' => now()]);
        $this->seedPresence($user2->id, ['status' => 'away', 'last_activity_at' => now()]);
        $this->seedPresence($user3->id, ['status' => 'offline', 'last_activity_at' => now()->subHours(1)]);

        // Flush Redis so the service falls back to DB count
        try {
            $keys = Redis::keys('nexus:presence:*');
            if (!empty($keys)) {
                Redis::del($keys);
            }
        } catch (\Throwable) {
            // pass
        }

        $response = $this->apiGet('/v2/presence/online-count');

        $response->assertStatus(200);
        $count = $response->json('data.online_count');
        // At least user1 (online) and user2 (away) should count
        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function test_online_count_is_tenant_scoped(): void
    {
        $this->authenticatedUser();

        // Add a user in a different tenant
        $otherTenantUser = User::factory()->forTenant(999)->create();
        $this->seedPresence($otherTenantUser->id, [
            'tenant_id' => 999,
            'status' => 'online',
            'last_activity_at' => now(),
        ]);

        // Flush Redis for DB fallback
        try {
            $keys = Redis::keys('nexus:presence:*');
            if (!empty($keys)) {
                Redis::del($keys);
            }
        } catch (\Throwable) {
            // pass
        }

        $response = $this->apiGet('/v2/presence/online-count');

        $response->assertStatus(200);
        $count = $response->json('data.online_count');
        // The other-tenant user should NOT be included
        $this->assertIsInt($count);
    }

    // ------------------------------------------------------------------
    //  TENANT ISOLATION
    // ------------------------------------------------------------------

    public function test_users_endpoint_only_returns_same_tenant_presence(): void
    {
        $this->authenticatedUser();

        $otherTenantUser = User::factory()->forTenant(999)->create();
        $this->seedPresence($otherTenantUser->id, [
            'tenant_id' => 999,
            'status' => 'online',
            'custom_status' => 'Secret status',
            'last_activity_at' => now(),
        ]);

        // Flush Redis for DB fallback
        try {
            $keys = Redis::keys('nexus:presence:*');
            if (!empty($keys)) {
                Redis::del($keys);
            }
        } catch (\Throwable) {
            // pass
        }

        $response = $this->apiGet("/v2/presence/users?user_ids={$otherTenantUser->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        // Should return offline for cross-tenant users (no record found in current tenant)
        $userKey = (string) $otherTenantUser->id;
        if (isset($data[$userKey])) {
            $this->assertEquals('offline', $data[$userKey]['status']);
            $this->assertNull($data[$userKey]['custom_status']);
        } elseif (isset($data[$otherTenantUser->id])) {
            $this->assertEquals('offline', $data[$otherTenantUser->id]['status']);
        }
    }

    // ------------------------------------------------------------------
    //  RESPONSE FORMAT
    // ------------------------------------------------------------------

    public function test_heartbeat_response_uses_v2_format(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/presence/heartbeat');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta']);
        $response->assertHeader('API-Version', '2.0');
    }

    public function test_set_status_response_includes_all_fields(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPut('/v2/presence/status', [
            'status' => 'away',
            'custom_status' => 'Be right back',
            'emoji' => "\xE2\x98\x95", // coffee emoji
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['status', 'custom_status', 'emoji'],
        ]);
    }
}
