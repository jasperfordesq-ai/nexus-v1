<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use App\Enums\GroupStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

/**
 * Smoke tests for GroupNotificationPrefController.
 */
class GroupNotificationPrefControllerTest extends TestCase
{
    use DatabaseTransactions;

    private User $member;
    private int $groupId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $this->groupId = (int) DB::table('groups')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'owner_id' => $this->member->id,
            'name' => 'Notification contract group',
            'description' => 'Preference test fixture.',
            'visibility' => 'private',
            'status' => GroupStatus::Active->value,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('group_members')->insert([
            'tenant_id' => $this->testTenantId,
            'group_id' => $this->groupId,
            'user_id' => $this->member->id,
            'role' => 'member',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $raw = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $features = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        $features['groups'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode($features, JSON_THROW_ON_ERROR),
        ]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    protected function tearDown(): void
    {
        Cache::forget('group_config:' . $this->testTenantId);
        parent::tearDown();
    }

    private function authenticatedUser(): User
    {
        Sanctum::actingAs($this->member, ['*']);

        return $this->member;
    }

    public function test_get_requires_auth(): void
    {
        $response = $this->apiGet("/v2/groups/{$this->groupId}/notification-prefs");
        $response->assertStatus(401);
    }

    public function test_set_requires_auth(): void
    {
        $response = $this->apiPut("/v2/groups/{$this->groupId}/notification-prefs", []);
        $response->assertStatus(401);
    }

    public function test_get_returns_a_truthful_typed_default_contract(): void
    {
        $this->authenticatedUser();
        $this->apiGet("/v2/groups/{$this->groupId}/notification-prefs")
            ->assertOk()
            ->assertJsonPath('data.frequency', 'instant')
            ->assertJsonPath('data.email_enabled', true)
            ->assertJsonPath('data.push_enabled', true)
            ->assertJsonPath('data.updated_at', null);
    }

    public function test_set_validates_and_returns_the_persisted_iso_timestamped_contract(): void
    {
        $this->authenticatedUser();
        $response = $this->apiPut("/v2/groups/{$this->groupId}/notification-prefs", [
            'frequency' => 'digest',
            'email_enabled' => false,
            'push_enabled' => true,
        ])->assertOk()
            ->assertJsonPath('data.preferences.frequency', 'digest')
            ->assertJsonPath('data.preferences.email_enabled', false)
            ->assertJsonPath('data.preferences.push_enabled', true);

        $updatedAt = $response->json('data.preferences.updated_at');
        self::assertIsString($updatedAt);
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/',
            $updatedAt,
        );
        $this->assertDatabaseHas('group_notification_preferences', [
            'tenant_id' => $this->testTenantId,
            'group_id' => $this->groupId,
            'user_id' => $this->member->id,
            'frequency' => 'digest',
            'email_enabled' => 0,
            'push_enabled' => 1,
        ]);
    }

    public function test_set_rejects_invalid_or_partial_channel_contracts(): void
    {
        $this->authenticatedUser();
        $uri = "/v2/groups/{$this->groupId}/notification-prefs";

        $this->apiPut($uri, [
            'frequency' => 'hourly',
            'email_enabled' => true,
            'push_enabled' => true,
        ])->assertUnprocessable()->assertJsonPath('errors.0.field', 'frequency');
        $this->apiPut($uri, [
            'frequency' => 'instant',
            'email_enabled' => 'sometimes',
            'push_enabled' => true,
        ])->assertUnprocessable()->assertJsonPath('errors.0.field', 'email_enabled');
        $this->apiPut($uri, [
            'frequency' => 'instant',
            'email_enabled' => true,
        ])->assertUnprocessable()->assertJsonPath('errors.0.field', 'push_enabled');
    }
}
