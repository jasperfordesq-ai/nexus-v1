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
        $response = $this->get("/{$this->testTenantSlug}/accessible/settings/linked-accounts");

        $response->assertRedirect("/{$this->testTenantSlug}/accessible/login?status=auth-required");
    }

    public function test_settings_linked_accounts_request_requires_authentication(): void
    {
        $response = $this->post("/{$this->testTenantSlug}/accessible/settings/linked-accounts/request", [
            'email' => 'someone@example.com',
        ]);

        $response->assertRedirect("/{$this->testTenantSlug}/accessible/login?status=auth-required");
    }

    public function test_settings_linked_accounts_page_renders_empty_state(): void
    {
        $this->authenticatedUser(['name' => 'Linker One']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/settings/linked-accounts");

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

        $response = $this->get("/{$this->testTenantSlug}/accessible/settings/linked-accounts");

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

        $response = $this->post("/{$this->testTenantSlug}/accessible/settings/linked-accounts/request", [
            'email' => 'not-an-email',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=link-email-invalid', (string) $response->headers->get('Location'));
    }

    public function test_settings_linked_accounts_request_unknown_email_reports_not_found(): void
    {
        $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/accessible/settings/linked-accounts/request", [
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

        $response = $this->post("/{$this->testTenantSlug}/accessible/settings/linked-accounts/request", [
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

        $response = $this->post("/{$this->testTenantSlug}/accessible/settings/linked-accounts/approve", [
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

        $response = $this->post("/{$this->testTenantSlug}/accessible/settings/linked-accounts/permissions", [
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

        $response = $this->post("/{$this->testTenantSlug}/accessible/settings/linked-accounts/revoke", [
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
        $response = $this->get("/{$this->testTenantSlug}/accessible/settings/appearance");

        $response->assertRedirect("/{$this->testTenantSlug}/accessible/login?status=auth-required");
    }

    public function test_settings_appearance_page_renders_with_current_theme(): void
    {
        $me = $this->authenticatedUser(['name' => 'Theme Me']);
        DB::table('users')->where('id', $me->id)->update(['preferred_theme' => 'light']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/settings/appearance");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_settings.appearance.title'));
        $response->assertSee(__('govuk_alpha_settings.appearance.themes.light'));
        $response->assertSee(__('govuk_alpha_settings.appearance.themes.dark'));
        $response->assertSee(__('govuk_alpha_settings.appearance.themes.system'));
        // The theme radios render (id scheme is GOV.UK-conventional, so assert the
        // order-independent name/value rather than a brittle exact attribute string).
        $response->assertSee('name="theme" type="radio" value="light"', false);
        $response->assertSee('name="theme" type="radio" value="dark"', false);
    }

    public function test_settings_appearance_update_persists_theme(): void
    {
        $me = $this->authenticatedUser(['name' => 'Save Theme Me']);

        $response = $this->post("/{$this->testTenantSlug}/accessible/settings/appearance", [
            'theme' => 'dark',
        ]);

        $response->assertRedirect("/{$this->testTenantSlug}/accessible/settings/appearance?status=appearance-saved");
        $this->assertDatabaseHas('users', [
            'id' => $me->id,
            'preferred_theme' => 'dark',
        ]);
    }

    public function test_settings_appearance_update_rejects_invalid_theme(): void
    {
        $me = $this->authenticatedUser(['name' => 'Bad Theme Me']);
        DB::table('users')->where('id', $me->id)->update(['preferred_theme' => 'system']);

        $response = $this->post("/{$this->testTenantSlug}/accessible/settings/appearance", [
            'theme' => 'neon-rainbow',
        ]);

        $response->assertRedirect("/{$this->testTenantSlug}/accessible/settings/appearance?status=appearance-invalid");
        // Unchanged.
        $this->assertDatabaseHas('users', [
            'id' => $me->id,
            'preferred_theme' => 'system',
        ]);
    }

    // =====================================================================
    //  GDPR data-subject requests
    // =====================================================================

    public function test_settings_data_rights_requires_authentication(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/accessible/settings/data-rights");

        $response->assertRedirect("/{$this->testTenantSlug}/accessible/login?status=auth-required");
    }

    public function test_settings_data_rights_request_requires_authentication(): void
    {
        $response = $this->post("/{$this->testTenantSlug}/accessible/settings/data-rights", [
            'request_type' => 'rectification',
        ]);

        $response->assertRedirect("/{$this->testTenantSlug}/accessible/login?status=auth-required");
    }

    public function test_settings_data_rights_page_renders_request_types(): void
    {
        $this->authenticatedUser(['name' => 'Rights Me']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/settings/data-rights");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_settings.gdpr.title'));
        $response->assertSee(__('govuk_alpha_settings.gdpr.types.portability'));
        $response->assertSee(__('govuk_alpha_settings.gdpr.types.rectification'));
        $response->assertSee(__('govuk_alpha_settings.gdpr.types.restriction'));
        $response->assertSee(__('govuk_alpha_settings.gdpr.types.objection'));
        $response->assertSee(__('govuk_alpha_settings.gdpr.your_requests_empty'));
    }

    public function test_settings_data_rights_request_rejects_invalid_type(): void
    {
        $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/accessible/settings/data-rights", [
            'request_type' => 'erasure', // not one of the four self-service types here
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=gdpr-invalid', (string) $response->headers->get('Location'));
    }

    public function test_settings_data_rights_request_persists_request(): void
    {
        $me = $this->authenticatedUser(['name' => 'Submit Rights Me']);

        $response = $this->post("/{$this->testTenantSlug}/accessible/settings/data-rights", [
            'request_type' => 'rectification',
            'notes' => 'My surname is misspelled.',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=gdpr-requested', (string) $response->headers->get('Location'));
        $this->assertDatabaseHas('gdpr_requests', [
            'user_id' => $me->id,
            'tenant_id' => $this->testTenantId,
            'request_type' => 'rectification',
            'status' => 'pending',
        ]);
    }

    public function test_settings_data_rights_request_blocks_duplicate(): void
    {
        $me = $this->authenticatedUser(['name' => 'Dup Rights Me']);

        DB::table('gdpr_requests')->insert([
            'user_id' => $me->id,
            'tenant_id' => $this->testTenantId,
            'request_type' => 'objection',
            'status' => 'pending',
            'priority' => 'normal',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post("/{$this->testTenantSlug}/accessible/settings/data-rights", [
            'request_type' => 'objection',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=gdpr-duplicate', (string) $response->headers->get('Location'));
    }

    // =====================================================================
    //  Insurance certificates (compliance-gated)
    // =====================================================================

    public function test_settings_insurance_requires_authentication(): void
    {
        $this->enableInsurance();
        $response = $this->get("/{$this->testTenantSlug}/accessible/settings/insurance");

        $response->assertRedirect("/{$this->testTenantSlug}/accessible/login?status=auth-required");
    }

    public function test_settings_insurance_404_when_disabled(): void
    {
        $this->disableInsurance();
        $this->authenticatedUser(['name' => 'No Insurance Me']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/settings/insurance");

        $response->assertNotFound();
    }

    public function test_settings_insurance_page_renders_when_enabled(): void
    {
        $this->enableInsurance();
        $this->authenticatedUser(['name' => 'Insurance Me']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/settings/insurance");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_settings.insurance.title'));
        $response->assertSee(__('govuk_alpha_settings.insurance.certificates_empty'));
        $response->assertSee(__('govuk_alpha_settings.insurance.upload_button'));
    }

    public function test_settings_insurance_page_shows_existing_certificate(): void
    {
        $this->enableInsurance();
        $me = $this->authenticatedUser(['name' => 'Cert Me']);

        DB::table('insurance_certificates')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $me->id,
            'insurance_type' => 'public_liability',
            'provider_name' => 'Acme Cover Ltd',
            'status' => 'verified',
            'created_at' => now(),
        ]);

        $response = $this->get("/{$this->testTenantSlug}/accessible/settings/insurance");

        $response->assertOk();
        $response->assertSee('Acme Cover Ltd');
        $response->assertSee(__('govuk_alpha_settings.insurance.types.public_liability'));
        $response->assertSee(__('govuk_alpha_settings.insurance.statuses.verified'));
    }

    public function test_settings_insurance_upload_requires_authentication(): void
    {
        $this->enableInsurance();
        $response = $this->post("/{$this->testTenantSlug}/accessible/settings/insurance", [
            'insurance_type' => 'public_liability',
        ]);

        $response->assertRedirect("/{$this->testTenantSlug}/accessible/login?status=auth-required");
    }

    public function test_settings_insurance_upload_404_when_disabled(): void
    {
        $this->disableInsurance();
        $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/accessible/settings/insurance", [
            'insurance_type' => 'public_liability',
        ]);

        $response->assertNotFound();
    }

    public function test_settings_insurance_upload_requires_file(): void
    {
        $this->enableInsurance();
        $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/accessible/settings/insurance", [
            'insurance_type' => 'public_liability',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=insurance-file-required', (string) $response->headers->get('Location'));
    }

    public function test_settings_insurance_upload_persists_certificate(): void
    {
        $this->enableInsurance();
        $me = $this->authenticatedUser(['name' => 'Upload Cert Me']);

        // A valid PNG file so finfo reports image/png.
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $file = \Illuminate\Http\Testing\File::create('cert.png');
        file_put_contents($file->getPathname(), $pngBytes);

        // The uploaded file must travel in the data array — Laravel's test client
        // extracts UploadedFile instances from there (post() has no separate files arg).
        $response = $this->post("/{$this->testTenantSlug}/accessible/settings/insurance", [
            'insurance_type' => 'professional_indemnity',
            'provider_name' => 'Indemnity Co',
            'certificate_file' => $file,
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=insurance-uploaded', (string) $response->headers->get('Location'));
        $this->assertDatabaseHas('insurance_certificates', [
            'user_id' => $me->id,
            'tenant_id' => $this->testTenantId,
            'insurance_type' => 'professional_indemnity',
            'provider_name' => 'Indemnity Co',
            'status' => 'submitted',
        ]);
    }

    // =====================================================================
    //  Helpers (mirrored from GovukAlphaFrontendTest, which keeps them private)
    // =====================================================================

    private function enableInsurance(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'broker_config'],
            ['setting_value' => json_encode(['insurance_enabled' => true]), 'setting_type' => 'json', 'updated_at' => now(), 'created_at' => now()],
        );
    }

    private function disableInsurance(): void
    {
        DB::table('tenant_settings')
            ->where('tenant_id', $this->testTenantId)
            ->where('setting_key', 'broker_config')
            ->delete();
    }

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
