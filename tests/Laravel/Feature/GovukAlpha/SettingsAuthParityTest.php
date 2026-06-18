<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Parity coverage for the accessible (GOV.UK) settings module additions:
 *   - Linked / sub-account management (React LinkedAccountsTab)
 *   - Appearance / theme settings (React AppearanceSettings)
 *
 * Mirrors the auth-gating, tenant-pinning and helper conventions of
 * tests/Laravel/Feature/GovukAlphaFrontendTest.php (which keeps these helpers
 * private), reproduced here so this file stands alone.
 */
class SettingsAuthParityTest extends TestCase
{
    use DatabaseTransactions;

    protected int $testTenantId = 2;
    protected string $testTenantSlug = 'hour-timebank';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['auth']->forgetGuards();

        foreach ([
            'HTTP_X_TENANT_ID',
            'HTTP_X_TENANT_SLUG',
            'HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTHORIZATION',
        ] as $serverKey) {
            unset($_SERVER[$serverKey]);
        }

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        \Illuminate\Support\Facades\Cache::flush();
    }

    // =====================================================================
    //  Linked / sub-account management
    // =====================================================================

    public function test_settings_linked_accounts_requires_authentication(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/alpha/settings/linked-accounts");

        $response->assertRedirect("/{$this->testTenantSlug}/alpha/login?status=auth-required");
    }

    public function test_settings_linked_accounts_request_requires_authentication(): void
    {
        $response = $this->post("/{$this->testTenantSlug}/alpha/settings/linked-accounts/request", [
            'email' => 'someone@example.com',
        ]);

        $response->assertRedirect("/{$this->testTenantSlug}/alpha/login?status=auth-required");
    }

    public function test_settings_linked_accounts_page_renders_empty_state(): void
    {
        $this->authenticatedUser(['name' => 'Linker One']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/settings/linked-accounts");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_settings.linked.title'));
        $response->assertSee(__('govuk_alpha_settings.linked.children_empty'));
        $response->assertSee(__('govuk_alpha_settings.linked.parents_empty'));
        $response->assertSee(__('govuk_alpha_settings.linked.request_heading'));
    }

    public function test_settings_linked_accounts_page_shows_existing_children_and_parents(): void
    {
        $me = $this->authenticatedUser(['name' => 'Manager Me']);
        $child = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'first_name' => 'Childy', 'last_name' => 'McChild', 'name' => 'Childy McChild']);
        $parent = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'first_name' => 'Parenty', 'last_name' => 'McParent', 'name' => 'Parenty McParent']);

        DB::table('account_relationships')->insert([
            [
                'parent_user_id' => $me->id, 'child_user_id' => $child->id, 'tenant_id' => $this->testTenantId,
                'relationship_type' => 'family', 'permissions' => json_encode(['can_view_activity' => true]),
                'status' => 'active', 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'parent_user_id' => $parent->id, 'child_user_id' => $me->id, 'tenant_id' => $this->testTenantId,
                'relationship_type' => 'carer', 'permissions' => json_encode(['can_view_activity' => true]),
                'status' => 'pending', 'approved_at' => null, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/settings/linked-accounts");

        $response->assertOk();
        $response->assertSee('Childy McChild');
        $response->assertSee('Parenty McParent');
        // Pending parent request offers an approve action.
        $response->assertSee(__('govuk_alpha_settings.linked.approve_button'));
        $response->assertSee(__('govuk_alpha_settings.linked.status_pending'));
    }

    public function test_settings_linked_accounts_request_with_invalid_email_redirects_with_status(): void
    {
        $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/alpha/settings/linked-accounts/request", [
            'email' => 'not-an-email',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=link-email-invalid', (string) $response->headers->get('Location'));
    }

    public function test_settings_linked_accounts_request_unknown_email_reports_not_found(): void
    {
        $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/alpha/settings/linked-accounts/request", [
            'email' => 'nobody-here-' . uniqid() . '@example.com',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=link-user-not-found', (string) $response->headers->get('Location'));
    }

    public function test_settings_linked_accounts_request_persists_relationship(): void
    {
        $me = $this->authenticatedUser(['name' => 'Requester Me']);
        $child = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active', 'is_approved' => true, 'email' => 'link-child-' . uniqid() . '@example.com',
        ]);

        $response = $this->post("/{$this->testTenantSlug}/alpha/settings/linked-accounts/request", [
            'email' => $child->email,
            'relationship_type' => 'family',
            'perm_can_view_activity' => '1',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=link-requested', (string) $response->headers->get('Location'));
        $this->assertDatabaseHas('account_relationships', [
            'parent_user_id' => $me->id,
            'child_user_id' => $child->id,
            'tenant_id' => $this->testTenantId,
        ]);
    }

    public function test_settings_linked_accounts_approve_activates_pending_relationship(): void
    {
        $me = $this->authenticatedUser(['name' => 'Approver Me']);
        $parent = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);

        $relationshipId = DB::table('account_relationships')->insertGetId([
            'parent_user_id' => $parent->id, 'child_user_id' => $me->id, 'tenant_id' => $this->testTenantId,
            'relationship_type' => 'guardian', 'permissions' => json_encode(['can_view_activity' => true]),
            'status' => 'pending', 'approved_at' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->post("/{$this->testTenantSlug}/alpha/settings/linked-accounts/approve", [
            'relationship_id' => $relationshipId,
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=link-approved', (string) $response->headers->get('Location'));
        $this->assertDatabaseHas('account_relationships', [
            'id' => $relationshipId,
            'status' => 'active',
        ]);
    }

    public function test_settings_linked_accounts_update_permissions_persists(): void
    {
        $me = $this->authenticatedUser(['name' => 'Perm Me']);
        $child = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);

        $relationshipId = DB::table('account_relationships')->insertGetId([
            'parent_user_id' => $me->id, 'child_user_id' => $child->id, 'tenant_id' => $this->testTenantId,
            'relationship_type' => 'family', 'permissions' => json_encode(['can_view_activity' => true]),
            'status' => 'active', 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->post("/{$this->testTenantSlug}/alpha/settings/linked-accounts/permissions", [
            'relationship_id' => $relationshipId,
            'perm_can_view_activity' => '1',
            'perm_can_manage_listings' => '1',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=link-permissions-saved', (string) $response->headers->get('Location'));

        $row = DB::table('account_relationships')->where('id', $relationshipId)->first();
        $perms = json_decode((string) ($row->permissions ?? '{}'), true) ?: [];
        $this->assertTrue((bool) ($perms['can_manage_listings'] ?? false));
    }

    public function test_settings_linked_accounts_revoke_removes_relationship(): void
    {
        $me = $this->authenticatedUser(['name' => 'Revoke Me']);
        $child = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);

        $relationshipId = DB::table('account_relationships')->insertGetId([
            'parent_user_id' => $me->id, 'child_user_id' => $child->id, 'tenant_id' => $this->testTenantId,
            'relationship_type' => 'family', 'permissions' => json_encode(['can_view_activity' => true]),
            'status' => 'active', 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->post("/{$this->testTenantSlug}/alpha/settings/linked-accounts/revoke", [
            'relationship_id' => $relationshipId,
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=link-revoked', (string) $response->headers->get('Location'));
        // The service marks the relationship revoked (soft state), so it must no
        // longer appear as active for this parent.
        $this->assertDatabaseMissing('account_relationships', [
            'id' => $relationshipId,
            'status' => 'active',
        ]);
    }

    // =====================================================================
    //  Appearance / theme
    // =====================================================================

    public function test_settings_appearance_requires_authentication(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/alpha/settings/appearance");

        $response->assertRedirect("/{$this->testTenantSlug}/alpha/login?status=auth-required");
    }

    public function test_settings_appearance_page_renders_with_current_theme(): void
    {
        $me = $this->authenticatedUser(['name' => 'Theme Me']);
        DB::table('users')->where('id', $me->id)->update(['preferred_theme' => 'light']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/settings/appearance");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_settings.appearance.title'));
        $response->assertSee(__('govuk_alpha_settings.appearance.themes.light'));
        $response->assertSee(__('govuk_alpha_settings.appearance.themes.dark'));
        $response->assertSee(__('govuk_alpha_settings.appearance.themes.system'));
        // The current theme radio is pre-selected.
        $response->assertSee('id="theme_light" name="theme" type="radio" value="light"', false);
    }

    public function test_settings_appearance_update_persists_theme(): void
    {
        $me = $this->authenticatedUser(['name' => 'Save Theme Me']);

        $response = $this->post("/{$this->testTenantSlug}/alpha/settings/appearance", [
            'theme' => 'dark',
        ]);

        $response->assertRedirect("/{$this->testTenantSlug}/alpha/settings/appearance?status=appearance-saved");
        $this->assertDatabaseHas('users', [
            'id' => $me->id,
            'preferred_theme' => 'dark',
        ]);
    }

    public function test_settings_appearance_update_rejects_invalid_theme(): void
    {
        $me = $this->authenticatedUser(['name' => 'Bad Theme Me']);
        DB::table('users')->where('id', $me->id)->update(['preferred_theme' => 'system']);

        $response = $this->post("/{$this->testTenantSlug}/alpha/settings/appearance", [
            'theme' => 'neon-rainbow',
        ]);

        $response->assertRedirect("/{$this->testTenantSlug}/alpha/settings/appearance?status=appearance-invalid");
        // Unchanged.
        $this->assertDatabaseHas('users', [
            'id' => $me->id,
            'preferred_theme' => 'system',
        ]);
    }

    // =====================================================================
    //  Helpers (mirrored from GovukAlphaFrontendTest, which keeps them private)
    // =====================================================================

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }
}
