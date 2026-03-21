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

/**
 * Feature tests for GdprController — GDPR consent, data requests, account deletion.
 */
class GdprControllerTest extends TestCase
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
    //  POST /gdpr/consent
    // ------------------------------------------------------------------

    public function test_update_consent_requires_auth(): void
    {
        $response = $this->apiPost('/gdpr/consent', ['consent_type' => 'marketing', 'granted' => true]);

        $response->assertStatus(401);
    }

    public function test_update_consent_works(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/gdpr/consent', [
            'consent_type' => 'marketing',
            'granted' => true,
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
    }

    // ------------------------------------------------------------------
    //  POST /gdpr/request
    // ------------------------------------------------------------------

    public function test_create_request_requires_auth(): void
    {
        $response = $this->apiPost('/gdpr/request', ['type' => 'export']);

        $response->assertStatus(401);
    }

    public function test_create_data_export_request(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/gdpr/request', [
            'type' => 'export',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
    }

    // ------------------------------------------------------------------
    //  POST /gdpr/delete-account
    // ------------------------------------------------------------------

    public function test_delete_account_requires_auth(): void
    {
        $response = $this->apiPost('/gdpr/delete-account');

        $response->assertStatus(401);
    }
}
