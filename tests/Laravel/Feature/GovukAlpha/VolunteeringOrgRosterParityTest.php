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
 * Feature tests for the accessible (GOV.UK) organisation "Volunteers roster" tab.
 */
class VolunteeringOrgRosterParityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['auth']->forgetGuards();
        foreach (['HTTP_X_TENANT_ID', 'HTTP_X_TENANT_SLUG', 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $k) {
            unset($_SERVER[$k]);
        }
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['volunteering'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function authenticatedUser(array $o = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge(['status' => 'active', 'is_approved' => true], $o));
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    private function createVolOrg(int $userId, string $name): int
    {
        return (int) DB::table('vol_organizations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id'   => $userId,
            'name'      => $name,
            'status'    => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedOpportunity(int $orgId): int
    {
        return (int) DB::table('vol_opportunities')->insertGetId([
            'tenant_id'       => $this->testTenantId,
            'organization_id' => $orgId,
            'title'           => 'Beach Cleanup Helper',
            'description'     => 'Help clean the beach on weekends.',
            'is_active'       => 1,
            'created_at'      => now(),
        ]);
    }

    private function seedApplication(int $opportunityId, int $userId, string $status): void
    {
        DB::table('vol_applications')->insert([
            'tenant_id'      => $this->testTenantId,
            'opportunity_id' => $opportunityId,
            'user_id'        => $userId,
            'status'         => $status,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    public function test_roster_lists_approved_volunteers_for_owner(): void
    {
        $owner = $this->authenticatedUser();
        $orgId = $this->createVolOrg($owner->id, 'Coastline Trust');
        $oppId = $this->seedOpportunity($orgId);

        $approved = $this->authenticatedUser(['name' => 'Approved Annie']);
        $pending = $this->authenticatedUser(['name' => 'Pending Pete']);
        $this->seedApplication($oppId, $approved->id, 'approved');
        $this->seedApplication($oppId, $pending->id, 'pending');

        Sanctum::actingAs($owner, ['*']);
        $res = $this->get("/{$this->testTenantSlug}/accessible/volunteering/organisations/{$orgId}/volunteers");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_volunteering.org_volunteers.title'));
        $res->assertSee('Approved Annie');
        $res->assertDontSee('Pending Pete');
    }

    public function test_roster_empty_state(): void
    {
        $owner = $this->authenticatedUser();
        $orgId = $this->createVolOrg($owner->id, 'Empty Org');

        $res = $this->get("/{$this->testTenantSlug}/accessible/volunteering/organisations/{$orgId}/volunteers");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_volunteering.org_volunteers.empty'));
    }

    public function test_roster_denied_for_non_owner(): void
    {
        $owner = $this->authenticatedUser();
        $orgId = $this->createVolOrg($owner->id, 'Private Roster Org');

        $stranger = $this->authenticatedUser();
        Sanctum::actingAs($stranger, ['*']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/volunteering/organisations/{$orgId}/volunteers");
        // The org gate blocks non-managers (403 or a redirect away from the roster).
        $this->assertContains($res->getStatusCode(), [403, 302]);
        if ($res->getStatusCode() === 200) {
            $res->assertDontSee('Private Roster Org');
        }
    }
}
