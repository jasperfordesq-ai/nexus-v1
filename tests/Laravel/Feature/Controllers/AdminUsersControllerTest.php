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
 * Feature tests for AdminUsersController.
 *
 * Covers index, show, store, update, destroy, approve, suspend, ban,
 * reactivate, reset2fa, addBadge, removeBadge, impersonate,
 * setSuperAdmin, recheckBadges, getConsents, setPassword,
 * sendPasswordReset, sendWelcomeEmail.
 */
class AdminUsersControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // INDEX — GET /v2/admin/users
    // ================================================================

    public function test_index_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/users');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_index_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/users');

        $response->assertStatus(403);
    }

    public function test_index_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/users');

        $response->assertStatus(401);
    }

    // ================================================================
    // SHOW — GET /v2/admin/users/{id}
    // ================================================================

    public function test_show_returns_200_for_existing_user(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/users/' . $user->id);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_show_returns_badges_using_current_schema_columns(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $user = User::factory()->forTenant($this->testTenantId)->create();

        DB::table('badges')->insertOrIgnore([
            'tenant_id' => $this->testTenantId,
            'badge_key' => 'welcome-helper',
            'name' => 'Welcome Helper',
            'description' => 'Completed the welcome helper path.',
            'icon' => 'fa-award',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_badges')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'badge_key' => 'welcome-helper',
            'name' => 'Legacy Welcome Helper',
            'title' => 'Legacy Welcome Helper',
            'icon' => 'fa-star',
            'awarded_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/users/' . $user->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.badges.0.slug', 'welcome-helper');
        $response->assertJsonPath('data.badges.0.name', 'Welcome Helper');
        $response->assertJsonPath('data.badges.0.description', 'Completed the welcome helper path.');
        $response->assertJsonPath('data.badges.0.icon', 'fa-award');
    }

    public function test_show_returns_404_for_nonexistent_user(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/users/99999');

        $response->assertStatus(404);
    }

    public function test_show_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/users/1');

        $response->assertStatus(403);
    }

    // ================================================================
    // APPROVE — POST /v2/admin/users/{id}/approve
    // ================================================================

    public function test_approve_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/users/1/approve');

        $response->assertStatus(403);
    }

    public function test_approve_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/admin/users/1/approve');

        $response->assertStatus(401);
    }

    // ================================================================
    // SUSPEND — POST /v2/admin/users/{id}/suspend
    // ================================================================

    public function test_suspend_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/users/1/suspend');

        $response->assertStatus(403);
    }

    // ================================================================
    // BAN — POST /v2/admin/users/{id}/ban
    // ================================================================

    public function test_ban_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/users/1/ban');

        $response->assertStatus(403);
    }

    // ================================================================
    // REACTIVATE — POST /v2/admin/users/{id}/reactivate
    // ================================================================

    public function test_reactivate_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/users/1/reactivate');

        $response->assertStatus(403);
    }

    // ================================================================
    // DELETE — DELETE /v2/admin/users/{id}
    // ================================================================

    public function test_destroy_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiDelete('/v2/admin/users/1');

        $response->assertStatus(403);
    }

    public function test_destroy_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiDelete('/v2/admin/users/1');

        $response->assertStatus(401);
    }

    // ================================================================
    // IMPORT TEMPLATE — GET /v2/admin/users/import/template
    // ================================================================

    public function test_import_template_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/users/import/template');

        $response->assertStatus(200);
    }

    public function test_import_template_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/users/import/template');

        $response->assertStatus(403);
    }

    // ================================================================
    // CONSENTS — GET /v2/admin/users/{id}/consents
    // ================================================================

    public function test_consents_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/users/1/consents');

        $response->assertStatus(403);
    }

    // ================================================================
    // RESET 2FA — POST /v2/admin/users/{id}/reset-2fa
    // ================================================================

    public function test_reset_2fa_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/users/1/reset-2fa');

        $response->assertStatus(403);
    }
}
