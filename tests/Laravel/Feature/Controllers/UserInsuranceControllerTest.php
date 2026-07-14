<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Feature tests for UserInsuranceController — user insurance certificates.
 */
class UserInsuranceControllerTest extends TestCase
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

    // ------------------------------------------------------------------
    //  GET /v2/users/me/insurance
    // ------------------------------------------------------------------

    public function test_list_requires_auth(): void
    {
        $response = $this->apiGet('/v2/users/me/insurance');

        $response->assertStatus(401);
    }

    public function test_list_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/users/me/insurance');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/users/me/insurance
    // ------------------------------------------------------------------

    public function test_store_requires_auth(): void
    {
        $response = $this->apiPost('/v2/users/me/insurance', []);

        $response->assertStatus(401);
    }

    public function test_store_rejects_any_document_field(): void
    {
        $this->authenticatedUser();

        $this->apiPost('/v2/users/me/insurance', [
            'insurance_type' => 'public_liability',
            'expiry_date' => '2027-01-01',
            'certificate_file' => 'data:application/pdf;base64,JVBERi0=',
        ])->assertStatus(422)->assertJsonPath('errors.0.code', 'DOCUMENT_UPLOAD_FORBIDDEN');
    }

    public function test_store_persists_only_non_sensitive_metadata(): void
    {
        $user = $this->authenticatedUser();

        $this->apiPost('/v2/users/me/insurance', [
            'insurance_type' => 'public_liability',
            'provider_name' => 'Example Mutual',
            'expiry_date' => '2027-01-01',
            'policy_number' => 'must-not-be-stored',
            'notes' => 'must-not-be-stored',
        ])->assertCreated();

        $record = DB::table('insurance_certificates')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($record);
        $this->assertSame('Example Mutual', $record->provider_name);
        $this->assertSame('2027-01-01', (string) $record->expiry_date);
        $this->assertNull($record->certificate_file_path);
        $this->assertNull($record->policy_number);
        $this->assertNull($record->notes);
    }
}
