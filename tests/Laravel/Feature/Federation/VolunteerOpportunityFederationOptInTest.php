<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Federation;

use App\Events\VolunteerOpportunityCreated;
use App\Listeners\PushVolunteerOpportunityToFederatedPartners;
use App\Models\User;
use App\Models\VolOpportunity;
use App\Services\FederationFeatureService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\Concerns\FederationIntegrationHarness;
use Tests\Laravel\TestCase;

/**
 * Per-opportunity federation opt-in (vol_opportunities.federated_visibility).
 *
 * The push listener must export ONLY local opportunities whose owner explicitly
 * chose 'listed' — never imported partner rows (is_federated / external_id),
 * never 'none' rows, never inactive/closed rows. The controller must reject
 * invalid enum values outright (MariaDB strict=false silently corrupts invalid
 * enum writes to '').
 */
final class VolunteerOpportunityFederationOptInTest extends TestCase
{
    use DatabaseTransactions;
    use FederationIntegrationHarness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableFederationForTenant($this->testTenantId);
        $this->fakePartnerHttp();
    }

    private function makeListener(): PushVolunteerOpportunityToFederatedPartners
    {
        return new PushVolunteerOpportunityToFederatedPartners(app(FederationFeatureService::class));
    }

    private function makeOpportunity(array $attributes = []): VolOpportunity
    {
        $opp = new VolOpportunity();
        $opp->id = $attributes['id'] ?? 910;
        $opp->title = $attributes['title'] ?? 'Community garden help';
        $opp->description = $attributes['description'] ?? 'Help us plant the spring beds.';
        $opp->is_active = $attributes['is_active'] ?? true;
        $opp->status = $attributes['status'] ?? 'open';
        $opp->federated_visibility = $attributes['federated_visibility'] ?? 'none';
        $opp->is_federated = $attributes['is_federated'] ?? 0;
        $opp->external_id = $attributes['external_id'] ?? null;

        return $opp;
    }

    // ------------------------------------------------------------------
    //  Listener gate
    // ------------------------------------------------------------------

    public function test_listener_pushes_local_listed_active_open_opportunity(): void
    {
        $partner = $this->setupPartner('nexus');

        $opp = $this->makeOpportunity(['federated_visibility' => 'listed']);

        $this->makeListener()->handle(new VolunteerOpportunityCreated($opp, $this->testTenantId));

        Http::assertSent(function ($request) use ($partner, $opp) {
            return str_starts_with($request->url(), $partner->base_url)
                && str_contains($request->body(), (string) $opp->title);
        });
    }

    public function test_listener_does_not_push_opportunity_with_visibility_none(): void
    {
        $this->setupPartner('nexus');

        $opp = $this->makeOpportunity(['federated_visibility' => 'none']);

        $this->makeListener()->handle(new VolunteerOpportunityCreated($opp, $this->testTenantId));

        Http::assertNothingSent();
    }

    public function test_listener_does_not_push_imported_rows_even_if_listed(): void
    {
        $this->setupPartner('nexus');

        // Imported partner row (federation echo guard): is_federated=1 + external_id.
        $opp = $this->makeOpportunity([
            'federated_visibility' => 'listed',
            'is_federated'         => 1,
            'external_id'          => 'partner-ext-42',
        ]);

        $this->makeListener()->handle(new VolunteerOpportunityCreated($opp, $this->testTenantId));

        Http::assertNothingSent();
    }

    public function test_listener_does_not_push_inactive_or_closed_listed_opportunity(): void
    {
        $this->setupPartner('nexus');

        $inactive = $this->makeOpportunity(['federated_visibility' => 'listed', 'is_active' => false]);
        $this->makeListener()->handle(new VolunteerOpportunityCreated($inactive, $this->testTenantId));

        $closed = $this->makeOpportunity(['federated_visibility' => 'listed', 'status' => 'closed']);
        $this->makeListener()->handle(new VolunteerOpportunityCreated($closed, $this->testTenantId));

        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    //  Controller validation + persistence
    // ------------------------------------------------------------------

    private function authenticatedOrgOwner(): array
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status'      => 'active',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($user, ['*']);

        $orgId = (int) DB::table('vol_organizations')->insertGetId([
            'tenant_id'  => $this->testTenantId,
            'user_id'    => $user->id,
            'name'       => 'Fed OptIn Org ' . uniqid(),
            'slug'       => 'fed-optin-org-' . uniqid(),
            'description' => 'Org for federation opt-in tests.',
            'status'     => 'active',
            'created_at' => now(),
        ]);

        return [$user, $orgId];
    }

    public function test_create_opportunity_rejects_invalid_federated_visibility_enum(): void
    {
        [, $orgId] = $this->authenticatedOrgOwner();

        $response = $this->apiPost('/v2/volunteering/opportunities', [
            'organization_id'      => $orgId,
            'title'                => 'Invalid enum opportunity',
            'description'          => 'This request must be rejected before any DB write.',
            'federated_visibility' => 'joinable', // valid for groups, NOT for vol opportunities
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseMissing('vol_opportunities', [
            'tenant_id' => $this->testTenantId,
            'title'     => 'Invalid enum opportunity',
        ]);
    }

    public function test_create_opportunity_persists_listed_visibility(): void
    {
        [, $orgId] = $this->authenticatedOrgOwner();

        $response = $this->apiPost('/v2/volunteering/opportunities', [
            'organization_id'      => $orgId,
            'title'                => 'Shared with partners opportunity',
            'description'          => 'Owner opted into federation sharing for this one.',
            'federated_visibility' => 'listed',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('vol_opportunities', [
            'tenant_id'            => $this->testTenantId,
            'title'                => 'Shared with partners opportunity',
            'federated_visibility' => 'listed',
        ]);
    }

    public function test_update_opportunity_rejects_invalid_federated_visibility_enum(): void
    {
        [$user, $orgId] = $this->authenticatedOrgOwner();

        $oppId = (int) DB::table('vol_opportunities')->insertGetId([
            'tenant_id'            => $this->testTenantId,
            'organization_id'      => $orgId,
            'created_by'           => $user->id,
            'title'                => 'Update target opportunity',
            'description'          => 'Existing local opportunity.',
            'status'               => 'open',
            'is_active'            => 1,
            'federated_visibility' => 'none',
            'created_at'           => now(),
        ]);

        $response = $this->apiPut('/v2/volunteering/opportunities/' . $oppId, [
            'federated_visibility' => 'bookable',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('vol_opportunities', [
            'id'                   => $oppId,
            'federated_visibility' => 'none',
        ]);
    }

    public function test_update_opportunity_persists_listed_visibility(): void
    {
        [$user, $orgId] = $this->authenticatedOrgOwner();

        $oppId = (int) DB::table('vol_opportunities')->insertGetId([
            'tenant_id'            => $this->testTenantId,
            'organization_id'      => $orgId,
            'created_by'           => $user->id,
            'title'                => 'Toggle sharing opportunity',
            'description'          => 'Existing local opportunity to opt in.',
            'status'               => 'open',
            'is_active'            => 1,
            'federated_visibility' => 'none',
            'created_at'           => now(),
        ]);

        $response = $this->apiPut('/v2/volunteering/opportunities/' . $oppId, [
            'federated_visibility' => 'listed',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('vol_opportunities', [
            'id'                   => $oppId,
            'federated_visibility' => 'listed',
        ]);
    }
}
