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

/**
 * Feature tests for VolunteerCertificateController — certificates & credentials.
 */
class VolunteerCertificateControllerTest extends TestCase
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

    public function test_my_certificates_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/certificates');

        $response->assertStatus(401);
    }

    public function test_my_credentials_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/credentials');

        $response->assertStatus(401);
    }

    public function test_my_certificates_authenticated_smoke(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/volunteering/certificates');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_my_credentials_authenticated_smoke(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/volunteering/credentials');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['credentials'], 'meta']);
    }

    public function test_verify_certificate_is_public_smoke(): void
    {
        // verifyCertificate has ->withoutMiddleware('auth:sanctum') — public route
        $response = $this->apiGet('/v2/volunteering/certificates/verify/FAKE-CODE');

        $response->assertNotFound()
            ->assertJsonStructure(['errors' => [['code', 'message']]]);
    }

    public function test_verify_certificate_is_tenant_scoped(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        DB::table('vol_certificates')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'verification_code' => 'TENANT2CERT',
            'total_hours' => 12.5,
            'date_range_start' => '2026-01-01',
            'date_range_end' => '2026-02-01',
            'organizations' => json_encode([['name' => 'Green Streets', 'hours' => 12.5]]),
            'generated_at' => now(),
        ]);

        $this->apiGet('/v2/volunteering/certificates/verify/TENANT2CERT')
            ->assertOk()
            ->assertJsonPath('data.verification_code', 'TENANT2CERT');

        $this->withTenant(999)
            ->apiGet('/v2/volunteering/certificates/verify/TENANT2CERT')
            ->assertNotFound();
    }
}
