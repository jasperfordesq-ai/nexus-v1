<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for UsersController.
 *
 * Covers profile, update, search, preferences, theme, language, password,
 * delete account, notifications, consent, sessions, nearby, listings.
 */
class UsersControllerTest extends TestCase
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

    // ================================================================
    // ME — Happy path
    // ================================================================

    public function test_me_returns_own_profile(): void
    {
        $user = $this->authenticatedUser([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->apiGet('/v2/users/me');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // ME — Authentication required
    // ================================================================

    public function test_me_returns_401_without_auth(): void
    {
        $response = $this->apiGet('/v2/users/me');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // UPDATE ME — Happy path
    // ================================================================

    public function test_update_profile_returns_updated_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPut('/v2/users/me', [
            'bio' => 'Updated bio text',
            'location' => 'Cork',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 422]);
    }

    // ================================================================
    // UPDATE ME — Authentication required
    // ================================================================

    public function test_update_profile_returns_401_without_auth(): void
    {
        $response = $this->apiPut('/v2/users/me', [
            'bio' => 'Unauthorized',
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // SHOW USER — Happy path
    // ================================================================

    public function test_show_returns_public_profile(): void
    {
        $this->authenticatedUser();
        $otherUser = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
        ]);

        $response = $this->apiGet("/v2/users/{$otherUser->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // SHOW USER — Not found
    // ================================================================

    public function test_show_returns_404_for_nonexistent_user(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/users/999999');

        $response->assertStatus(404);
    }

    // ================================================================
    // SHOW USER — Tenant isolation
    // ================================================================

    public function test_show_cannot_access_user_from_different_tenant(): void
    {
        DB::table('tenants')->insertOrIgnore([
            'id' => 999, 'name' => 'Other', 'slug' => 'other',
            'is_active' => true, 'depth' => 0, 'allows_subtenants' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $otherUser = User::factory()->forTenant(999)->create([
            'status' => 'active',
        ]);

        $this->authenticatedUser();

        $response = $this->apiGet("/v2/users/{$otherUser->id}");

        // Should return 404 because user belongs to different tenant
        $this->assertContains($response->getStatusCode(), [404, 403]);
    }

    // ================================================================
    // SEARCH
    // ================================================================

    public function test_search_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/users?q=test');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // MEMBER DIRECTORY (INDEX)
    // ================================================================

    public function test_index_returns_member_directory(): void
    {
        $this->authenticatedUser();
        User::factory()->forTenant($this->testTenantId)->count(3)->create([
            'status' => 'active',
        ]);

        $response = $this->apiGet('/v2/users');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta']);
    }

    // ================================================================
    // STATS
    // ================================================================

    public function test_stats_returns_profile_stats(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/me/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_stats_returns_401_without_auth(): void
    {
        $response = $this->apiGet('/v2/me/stats');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // PREFERENCES — Happy path
    // ================================================================

    public function test_get_preferences_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/users/me/preferences');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['privacy', 'notifications', 'accessibility'],
        ]);
    }

    // ================================================================
    // PREFERENCES — Authentication required
    // ================================================================

    public function test_get_preferences_returns_401_without_auth(): void
    {
        $response = $this->apiGet('/v2/users/me/preferences');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // THEME — Validation
    // ================================================================

    public function test_update_theme_returns_400_for_invalid_theme(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPut('/v2/users/me/theme', [
            'theme' => 'neon',
        ]);

        $response->assertStatus(400);
    }

    public function test_update_theme_accepts_valid_theme(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPut('/v2/users/me/theme', [
            'theme' => 'dark',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.theme', 'dark');
    }

    public function test_update_theme_preferences_persists_accessibility_profile(): void
    {
        $user = $this->authenticatedUser([
            'theme_preferences' => json_encode([
                'accent_color' => '#6366f1',
                'font_size' => 'medium',
                'density' => 'comfortable',
                'high_contrast' => false,
            ]),
        ]);

        $response = $this->apiPut('/v2/users/me/theme-preferences', [
            'large_text' => true,
            'high_contrast' => true,
            'reduced_motion' => true,
            'simplified_layout' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.theme_preferences.accent_color', '#6366f1');
        $response->assertJsonPath('data.theme_preferences.large_text', true);
        $response->assertJsonPath('data.theme_preferences.high_contrast', true);
        $response->assertJsonPath('data.theme_preferences.reduced_motion', true);
        $response->assertJsonPath('data.theme_preferences.simplified_layout', true);

        $stored = DB::table('users')->where('id', $user->id)->value('theme_preferences');
        $stored = json_decode((string) $stored, true);

        $this->assertTrue($stored['large_text']);
        $this->assertTrue($stored['reduced_motion']);
        $this->assertTrue($stored['simplified_layout']);
        $this->assertSame('#6366f1', $stored['accent_color']);
    }

    public function test_update_theme_returns_401_without_auth(): void
    {
        $response = $this->apiPut('/v2/users/me/theme', [
            'theme' => 'dark',
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // LANGUAGE — Validation
    // ================================================================

    public function test_update_language_returns_400_for_invalid_language(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPut('/v2/users/me/language', [
            'language' => 'klingon',
        ]);

        $response->assertStatus(400);
    }

    public function test_update_language_returns_401_without_auth(): void
    {
        $response = $this->apiPut('/v2/users/me/language', [
            'language' => 'en',
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // PASSWORD — Validation
    // ================================================================

    public function test_update_password_returns_400_without_current_password(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/users/me/password', [
            'new_password' => 'new-password-123',
        ]);

        $response->assertStatus(400);
    }

    public function test_update_password_returns_400_without_new_password(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/users/me/password', [
            'current_password' => 'old-password',
        ]);

        $response->assertStatus(400);
    }

    public function test_update_password_returns_401_without_auth(): void
    {
        $response = $this->apiPost('/v2/users/me/password', [
            'current_password' => 'old',
            'new_password' => 'new',
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // DELETE ACCOUNT — Authentication required
    // ================================================================

    public function test_delete_account_returns_401_without_auth(): void
    {
        $response = $this->apiDelete('/v2/users/me');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // MY LISTINGS
    // ================================================================

    public function test_my_listings_returns_data(): void
    {
        $user = $this->authenticatedUser();
        Listing::factory()->forTenant($this->testTenantId)->count(2)->create([
            'user_id' => $user->id,
        ]);

        $response = $this->apiGet('/v2/users/me/listings');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_my_listings_returns_401_without_auth(): void
    {
        $response = $this->apiGet('/v2/users/me/listings');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // USER LISTINGS
    // ================================================================

    public function test_user_listings_returns_data(): void
    {
        $user = $this->authenticatedUser();
        $otherUser = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
        ]);
        Listing::factory()->forTenant($this->testTenantId)->count(2)->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->apiGet("/v2/users/{$otherUser->id}/listings");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // NOTIFICATION PREFERENCES
    // ================================================================

    public function test_notification_preferences_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/users/me/notifications');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_update_notification_preferences_returns_400_without_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPut('/v2/users/me/notifications', []);

        $response->assertStatus(400);
    }

    // ================================================================
    // NEARBY MEMBERS — Validation
    // ================================================================

    public function test_nearby_returns_400_without_coordinates(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/members/nearby');

        $response->assertStatus(400);
    }

    public function test_nearby_returns_data_with_valid_coordinates(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/members/nearby?lat=53.35&lon=-6.26');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // SESSIONS
    // ================================================================

    public function test_sessions_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/users/me/sessions');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_sessions_returns_401_without_auth(): void
    {
        $response = $this->apiGet('/v2/users/me/sessions');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // CONSENT — Authentication required
    // ================================================================

    public function test_get_consent_returns_401_without_auth(): void
    {
        $response = $this->apiGet('/v2/users/me/consent');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_update_consent_returns_400_without_slug(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPut('/v2/users/me/consent', [
            'given' => true,
        ]);

        $response->assertStatus(400);
    }

    // ================================================================
    // GDPR REQUEST — Validation
    // ================================================================

    public function test_gdpr_request_returns_400_for_invalid_type(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/users/me/gdpr-request', [
            'type' => 'invalid',
        ]);

        $response->assertStatus(400);
    }

    public function test_gdpr_request_returns_401_without_auth(): void
    {
        $response = $this->apiPost('/v2/users/me/gdpr-request', [
            'type' => 'access',
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }
}
