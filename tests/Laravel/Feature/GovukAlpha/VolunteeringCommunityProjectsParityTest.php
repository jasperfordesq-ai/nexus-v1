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
 * Feature test for the accessible (GOV.UK) volunteering "Community projects" tab.
 */
class VolunteeringCommunityProjectsParityTest extends TestCase
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

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    public function test_community_projects_tab_lists_approved_projects(): void
    {
        $proposer = $this->authenticatedUser();
        DB::table('vol_community_projects')->insert([
            'tenant_id'       => $this->testTenantId,
            'proposed_by'     => $proposer->id,
            'title'           => 'Riverside Cleanup Project',
            'description'     => 'A community effort to clean the riverside park.',
            'status'          => 'approved',
            'supporter_count' => 4,
            'created_at'      => now(),
        ]);
        // A non-public (proposed) project must NOT appear in the public list.
        DB::table('vol_community_projects')->insert([
            'tenant_id'   => $this->testTenantId,
            'proposed_by' => $proposer->id,
            'title'       => 'Hidden Draft Project',
            'description' => 'Still under review.',
            'status'      => 'proposed',
            'created_at'  => now(),
        ]);

        $res = $this->get("/{$this->testTenantSlug}/accessible/volunteering?tab=community_projects");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha.volunteering.community_projects_title'));
        $res->assertSee('Riverside Cleanup Project');
        $res->assertDontSee('Hidden Draft Project');
    }

    public function test_community_projects_tab_shows_in_nav(): void
    {
        $this->authenticatedUser();
        $res = $this->get("/{$this->testTenantSlug}/accessible/volunteering");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha.volunteering.community_projects_tab'));
        $res->assertSee('tab=community_projects', false);
    }
}
