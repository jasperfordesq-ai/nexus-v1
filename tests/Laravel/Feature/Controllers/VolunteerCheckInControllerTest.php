<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use App\Models\User;
use App\Services\VolunteerCheckInService;

/**
 * Feature tests for VolunteerCheckInController — shift check-in/out.
 */
class VolunteerCheckInControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    public function test_get_check_in_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/shifts/1/checkin');

        $response->assertStatus(401);
    }

    public function test_shift_check_ins_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/shifts/1/checkins');

        $response->assertStatus(401);
    }

    public function test_get_check_in_authenticated_smoke(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/volunteering/shifts/1/checkin');

        $this->assertLessThan(500, $response->status());
    }

    public function test_shift_check_ins_authenticated_smoke(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/volunteering/shifts/1/checkins');

        $this->assertLessThan(500, $response->status());
    }

    public function test_active_org_admin_can_verify_shift_checkin(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create();
        $admin = User::factory()->forTenant($this->testTenantId)->create();
        $volunteer = User::factory()->forTenant($this->testTenantId)->create();

        $orgId = (int) DB::table('vol_organizations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $owner->id,
            'name' => 'Check-in Org',
            'slug' => 'check-in-org-' . uniqid(),
            'description' => 'Organisation for check-in verification tests.',
            'status' => 'active',
            'created_at' => now(),
        ]);

        DB::table('org_members')->insert([
            'tenant_id' => $this->testTenantId,
            'organization_id' => $orgId,
            'user_id' => $admin->id,
            'role' => 'admin',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $opportunityId = (int) DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'organization_id' => $orgId,
            'title' => 'Check-in Opportunity',
            'description' => 'A test opportunity with a shift.',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => now(),
        ]);

        $shiftId = (int) DB::table('vol_shifts')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'opportunity_id' => $opportunityId,
            'start_time' => now()->subMinutes(5),
            'end_time' => now()->addHour(),
            'capacity' => 5,
            'created_at' => now(),
        ]);

        DB::table('vol_applications')->insert([
            'tenant_id' => $this->testTenantId,
            'opportunity_id' => $opportunityId,
            'shift_id' => $shiftId,
            'user_id' => $volunteer->id,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = VolunteerCheckInService::generateToken($shiftId, $volunteer->id);
        $this->assertNotNull($token);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->apiPost('/v2/volunteering/checkin/verify/' . $token);

        $response->assertStatus(200);
        $this->assertSame('checked_in', DB::table('vol_shift_checkins')->where('qr_token', $token)->value('status'));
    }
}
