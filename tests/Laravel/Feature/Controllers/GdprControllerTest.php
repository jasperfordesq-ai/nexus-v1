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
use App\Services\TokenService;
use Illuminate\Support\Facades\Hash;

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

        // GdprService::updateUserConsent resolves the slug against an active
        // consent_types row; the clean CI DB has none, so seed 'marketing'.
        // consent_types is a global (non-tenant) table keyed by slug.
        DB::table('consent_types')->updateOrInsert(
            ['slug' => 'marketing'],
            [
                'name' => 'Marketing',
                'description' => 'Marketing communications consent',
                'category' => 'marketing',
                'is_required' => 0,
                'current_version' => '1.0',
                'current_text' => 'I agree to receive marketing communications.',
                'legal_basis' => 'consent',
                'is_active' => 1,
                'display_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

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

        // Controller's type map accepts the data_* prefixed types
        // (data_export → portability), not the bare 'export'.
        $response = $this->apiPost('/gdpr/request', [
            'type' => 'data_export',
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

    public function test_delete_account_revokes_every_refresh_session_immediately(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'password_hash' => Hash::make('CorrectPassword123!'),
            'status' => 'active',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($user, ['*']);
        $tokens = app(TokenService::class);
        $tokens->generateRefreshToken((int) $user->id, $this->testTenantId);
        $tokens->generateRefreshToken((int) $user->id, $this->testTenantId);

        $this->apiPost('/gdpr/delete-account', [
            'password' => 'CorrectPassword123!',
        ])
            ->assertOk()
            ->assertJsonPath('data.logout_required', true);

        $this->assertDatabaseMissing('refresh_token_sessions', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'revoked_at' => null,
        ]);
        $this->assertSame(
            2,
            DB::table('refresh_token_sessions')
                ->where('tenant_id', $this->testTenantId)
                ->where('user_id', $user->id)
                ->where('revocation_reason', 'account_deletion_request')
                ->count(),
        );
    }
}
