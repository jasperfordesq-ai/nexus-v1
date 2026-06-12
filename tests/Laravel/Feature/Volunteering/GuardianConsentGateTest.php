<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Volunteering;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Guardian-consent enforcement (2026-06-12): members under 18 (by
 * date_of_birth) must hold an ACTIVE guardian consent before applying to a
 * volunteering opportunity, signing up for a shift, or joining a waitlist.
 * Adults — and users with no recorded date of birth — are unaffected.
 */
class GuardianConsentGateTest extends TestCase
{
    use DatabaseTransactions;

    private function makeOpportunityWithShift(): array
    {
        $oppId = (int) DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'title' => 'Consent Gate Test Opportunity',
            'description' => 'x',
            'is_active' => 1,
            'created_at' => now(),
        ]);
        $shiftId = (int) DB::table('vol_shifts')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'opportunity_id' => $oppId,
            'start_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(2)->addHours(2)->format('Y-m-d H:i:s'),
            'capacity' => 5,
        ]);

        return [$oppId, $shiftId];
    }

    private function actingAsUserWithDob(?string $dob): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);
        if ($dob !== null) {
            DB::table('users')->where('id', $user->id)->update(['date_of_birth' => $dob]);
        }
        Sanctum::actingAs($user->fresh());

        return $user;
    }

    public function test_minor_without_consent_is_blocked_from_applying(): void
    {
        [$oppId] = $this->makeOpportunityWithShift();
        $this->actingAsUserWithDob(now()->subYears(15)->toDateString());

        $response = $this->apiPost("/v2/volunteering/opportunities/{$oppId}/apply", ['message' => 'hi']);

        $response->assertStatus(403);
        $this->assertSame('GUARDIAN_CONSENT_REQUIRED', $response->json('errors.0.code'));
    }

    public function test_minor_without_consent_is_blocked_from_shift_signup_and_waitlist(): void
    {
        [, $shiftId] = $this->makeOpportunityWithShift();
        $this->actingAsUserWithDob(now()->subYears(16)->toDateString());

        $signup = $this->apiPost("/v2/volunteering/shifts/{$shiftId}/signup");
        $signup->assertStatus(403);
        $this->assertSame('GUARDIAN_CONSENT_REQUIRED', $signup->json('errors.0.code'));

        $waitlist = $this->apiPost("/v2/volunteering/shifts/{$shiftId}/waitlist");
        $waitlist->assertStatus(403);
        $this->assertSame('GUARDIAN_CONSENT_REQUIRED', $waitlist->json('errors.0.code'));
    }

    public function test_minor_with_active_consent_can_apply(): void
    {
        [$oppId] = $this->makeOpportunityWithShift();
        $minor = $this->actingAsUserWithDob(now()->subYears(15)->toDateString());

        DB::table('vol_guardian_consents')->insert([
            'tenant_id' => $this->testTenantId,
            'minor_user_id' => $minor->id,
            'guardian_name' => 'Test Guardian',
            'guardian_email' => 'guardian@example.com',
            'relationship' => 'parent',
            'consent_token' => bin2hex(random_bytes(32)),
            'status' => 'active',
            'expires_at' => now()->addDays(200)->format('Y-m-d H:i:s'),
            'created_at' => now(),
        ]);

        $response = $this->apiPost("/v2/volunteering/opportunities/{$oppId}/apply", ['message' => 'hi']);

        $response->assertStatus(201);
    }

    public function test_adult_is_not_gated(): void
    {
        [$oppId] = $this->makeOpportunityWithShift();
        $this->actingAsUserWithDob(now()->subYears(30)->toDateString());

        $response = $this->apiPost("/v2/volunteering/opportunities/{$oppId}/apply", ['message' => 'hi']);

        $response->assertStatus(201);
    }

    public function test_user_without_dob_is_not_gated(): void
    {
        [$oppId] = $this->makeOpportunityWithShift();
        $this->actingAsUserWithDob(null);

        $response = $this->apiPost("/v2/volunteering/opportunities/{$oppId}/apply", ['message' => 'hi']);

        $response->assertStatus(201);
    }

    public function test_verify_endpoint_grants_pending_consent_without_auth(): void
    {
        TenantContext::setById($this->testTenantId);
        $token = bin2hex(random_bytes(32));
        $id = (int) DB::table('vol_guardian_consents')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'minor_user_id' => 1,
            'guardian_name' => 'Test Guardian',
            'guardian_email' => 'guardian@example.com',
            'relationship' => 'parent',
            'consent_token' => $token,
            'status' => 'pending',
            'expires_at' => now()->addDays(30)->format('Y-m-d H:i:s'),
            'created_at' => now(),
        ]);

        $response = $this->apiGet("/v2/volunteering/guardian-consents/verify/{$token}");

        $response->assertStatus(200);
        $this->assertSame('active', DB::table('vol_guardian_consents')->where('id', $id)->value('status'));
    }
}
