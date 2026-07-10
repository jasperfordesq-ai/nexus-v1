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
 * Accessible (GOV.UK) frontend — volunteering safeguarding parity routes.
 *
 * Covers auth gating, page render, training record persistence, and incident
 * report persistence (or validation redirect when the service rejects input).
 * Mirrors the setUp scrubbing pattern from VolunteeringParityTest.
 */
class VolunteeringSafeguardingParityTest extends TestCase
{
    use DatabaseTransactions;

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

        $this->enableVolunteeringFeature();
    }

    // =====================================================================
    // Auth gating
    // =====================================================================

    public function test_volunteering_safeguarding_training_requires_authentication(): void
    {
        $loginPath = "/{$this->testTenantSlug}/accessible/login";

        $response = $this->get("/{$this->testTenantSlug}/accessible/volunteering/training");

        $response->assertRedirect();
        $this->assertStringContainsString($loginPath, $response->headers->get('Location') ?? '');
    }

    public function test_volunteering_safeguarding_incidents_requires_authentication(): void
    {
        $loginPath = "/{$this->testTenantSlug}/accessible/login";

        $response = $this->get("/{$this->testTenantSlug}/accessible/volunteering/incidents");

        $response->assertRedirect();
        $this->assertStringContainsString($loginPath, $response->headers->get('Location') ?? '');
    }

    public function test_volunteering_safeguarding_post_training_requires_authentication(): void
    {
        $loginPath = "/{$this->testTenantSlug}/accessible/login";

        $response = $this->post("/{$this->testTenantSlug}/accessible/volunteering/training", [
            'training_type' => 'first_aid',
            'training_name' => 'Some Course',
            'completed_at'  => '2026-01-15',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString($loginPath, $response->headers->get('Location') ?? '');
    }

    public function test_volunteering_safeguarding_post_incident_requires_authentication(): void
    {
        $loginPath = "/{$this->testTenantSlug}/accessible/login";

        $response = $this->post("/{$this->testTenantSlug}/accessible/volunteering/incidents", [
            'title'       => 'Concern',
            'description' => 'A detailed description of the concern that is at least twenty characters long.',
            'severity'    => 'low',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString($loginPath, $response->headers->get('Location') ?? '');
    }

    // =====================================================================
    // Page render for authenticated user
    // =====================================================================

    public function test_volunteering_safeguarding_training_renders_for_authenticated_user(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/accessible/volunteering/training");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_volunteering.safeguarding.title'));
        $response->assertSee(__('govuk_alpha_volunteering.safeguarding.add_training_title'));
        $response->assertSee(__('govuk_alpha_volunteering.safeguarding.tab_training'));
    }

    public function test_volunteering_safeguarding_incidents_renders_for_authenticated_user(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/accessible/volunteering/incidents");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_volunteering.safeguarding.title'));
        $response->assertSee(__('govuk_alpha_volunteering.safeguarding.report_incident_title'));
        $response->assertSee(__('govuk_alpha_volunteering.safeguarding.tab_incidents'));
    }

    // =====================================================================
    // Training record persistence
    // =====================================================================

    public function test_volunteering_safeguarding_log_training_persists(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/accessible/volunteering/training", [
            'training_type' => 'first_aid',
            'training_name' => 'Emergency First Aid at Work',
            'provider'      => 'Red Cross',
            'completed_at'  => '2026-01-10',
            'expires_at'    => '2029-01-10',
        ]);

        $response->assertRedirect();
        $location = $response->headers->get('Location') ?? '';
        $this->assertStringContainsString('status=training-added', $location);

        $this->assertDatabaseHas('vol_safeguarding_training', [
            'user_id'       => $user->id,
            'tenant_id'     => $this->testTenantId,
            'training_type' => 'first_aid',
            'training_name' => 'Emergency First Aid at Work',
            'provider'      => 'Red Cross',
            'status'        => 'pending',
        ]);
    }

    public function test_volunteering_safeguarding_log_training_rejects_missing_name(): void
    {
        $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/accessible/volunteering/training", [
            'training_type' => 'first_aid',
            'training_name' => '',
            'completed_at'  => '2026-01-10',
        ]);

        $response->assertRedirect();
        $location = $response->headers->get('Location') ?? '';
        $this->assertStringContainsString('status=training-name-required', $location);
    }

    public function test_volunteering_safeguarding_log_training_rejects_missing_type(): void
    {
        $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/accessible/volunteering/training", [
            'training_type' => '',
            'training_name' => 'Some Course',
            'completed_at'  => '2026-01-10',
        ]);

        $response->assertRedirect();
        $location = $response->headers->get('Location') ?? '';
        $this->assertStringContainsString('status=training-type-required', $location);
    }

    public function test_volunteering_safeguarding_log_training_rejects_missing_date(): void
    {
        $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/accessible/volunteering/training", [
            'training_type' => 'first_aid',
            'training_name' => 'Some Course',
            'completed_at'  => '',
        ]);

        $response->assertRedirect();
        $location = $response->headers->get('Location') ?? '';
        $this->assertStringContainsString('status=training-date-required', $location);
    }

    public function test_volunteering_safeguarding_error_summary_links_to_field(): void
    {
        // VOL-AF-002: the GOV.UK error summary must offer a jump link to the
        // offending field, not just a bare paragraph.
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/accessible/volunteering/training?status=training-type-required");

        $response->assertStatus(200);
        $response->assertSee('govuk-error-summary__list', false);
        $response->assertSee('href="#training_type"', false);
    }

    // =====================================================================
    // Incident report persistence
    // =====================================================================

    public function test_volunteering_safeguarding_report_incident_persists(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/accessible/volunteering/incidents", [
            'title'       => 'Near-miss during shift',
            'description' => 'A volunteer tripped on uneven paving outside the community centre. No injury occurred but the hazard was noted.',
            'severity'    => 'medium',
            'category'    => 'health_and_safety',
        ]);

        $response->assertRedirect();
        $location = $response->headers->get('Location') ?? '';
        $this->assertStringContainsString('status=incident-reported', $location);

        $this->assertDatabaseHas('vol_safeguarding_incidents', [
            'reported_by' => $user->id,
            'tenant_id'   => $this->testTenantId,
            'title'       => 'Near-miss during shift',
            'severity'    => 'medium',
            'status'      => 'open',
        ]);
    }

    public function test_volunteering_safeguarding_report_incident_rejects_missing_title(): void
    {
        $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/accessible/volunteering/incidents", [
            'title'       => '',
            'description' => 'A detailed description of the concern that is at least twenty characters long.',
            'severity'    => 'low',
        ]);

        $response->assertRedirect();
        $location = $response->headers->get('Location') ?? '';
        $this->assertStringContainsString('status=incident-title-required', $location);
    }

    public function test_volunteering_safeguarding_report_incident_rejects_short_description(): void
    {
        $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/accessible/volunteering/incidents", [
            'title'       => 'A concern',
            'description' => 'Too short',
            'severity'    => 'low',
        ]);

        $response->assertRedirect();
        $location = $response->headers->get('Location') ?? '';
        $this->assertStringContainsString('status=incident-description-too-short', $location);
    }

    // =====================================================================
    // Training records appear in the list after being logged
    // =====================================================================

    public function test_volunteering_safeguarding_training_list_shows_existing_record(): void
    {
        $user = $this->authenticatedUser();

        // Insert a training record directly so we don't depend on the full
        // service notification pipeline during testing.
        DB::table('vol_safeguarding_training')->insert([
            'user_id'       => $user->id,
            'tenant_id'     => $this->testTenantId,
            'training_type' => 'children_first',
            'training_name' => 'Children First Awareness',
            'provider'      => null,
            'completed_at'  => '2025-09-01',
            'expires_at'    => null,
            'status'        => 'verified',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $response = $this->get("/{$this->testTenantSlug}/accessible/volunteering/training");

        $response->assertOk();
        $response->assertSee('Children First Awareness');
        $response->assertSee(__('govuk_alpha_volunteering.safeguarding.status_verified'));
    }

    private function enableVolunteeringFeature(): void
    {
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['volunteering'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->app->instance('tenant.id', $this->testTenantId);
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
