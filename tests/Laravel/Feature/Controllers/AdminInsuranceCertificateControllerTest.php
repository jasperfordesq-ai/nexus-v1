<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use App\Services\EmailDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for AdminInsuranceCertificateController.
 *
 * Covers list, stats, show, store, update, verify, reject, destroy, getUserCertificates.
 */
class AdminInsuranceCertificateControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // STATS — GET /v2/admin/insurance/stats
    // ================================================================

    public function test_stats_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/insurance/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_stats_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/insurance/stats');

        $response->assertStatus(403);
    }

    public function test_stats_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/insurance/stats');

        $response->assertStatus(401);
    }

    // ================================================================
    // LIST — GET /v2/admin/insurance
    // ================================================================

    public function test_list_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/insurance');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_list_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/insurance');

        $response->assertStatus(403);
    }

    // ================================================================
    // SHOW — GET /v2/admin/insurance/{id}
    // ================================================================

    public function test_show_returns_404_for_nonexistent_certificate(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/insurance/99999');

        $response->assertStatus(404);
    }

    // ================================================================
    // STORE — POST /v2/admin/insurance
    // ================================================================

    public function test_store_requires_user_id(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/insurance', [
            'insurance_type' => 'public_liability',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/insurance', [
            'user_id' => 1,
            'insurance_type' => 'public_liability',
        ]);

        $response->assertStatus(403);
    }

    // ================================================================
    // REJECT — POST /v2/admin/insurance/{id}/reject
    // ================================================================

    public function test_reject_requires_reason(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/insurance/1/reject', []);

        $response->assertStatus(422);
    }

    public function test_verify_sends_insurance_certificate_email_with_tenant_evidence(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $member = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'insurance-verified@example.test',
        ]);
        $mailer = $this->fakeEmailDispatchService();
        $certificateId = $this->createCertificateForUser($member);
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/insurance/' . $certificateId . '/verify');

        $response->assertStatus(200);
        $response->assertJsonPath('data.email_sent', true);
        $this->assertCount(1, $mailer->sends);
        $this->assertSame('insurance-verified@example.test', $mailer->sends[0]['to']);
        $this->assertSame('insurance_certificate', $mailer->sends[0]['options']['category']);
        $this->assertSame($this->testTenantId, $mailer->sends[0]['options']['tenant_id']);
    }

    public function test_reject_sends_insurance_certificate_email_with_reason_and_tenant_evidence(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $member = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'insurance-rejected@example.test',
        ]);
        $mailer = $this->fakeEmailDispatchService();
        $certificateId = $this->createCertificateForUser($member);
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/insurance/' . $certificateId . '/reject', [
            'reason' => 'Need updated expiry date',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.email_sent', true);
        $this->assertCount(1, $mailer->sends);
        $this->assertSame('insurance-rejected@example.test', $mailer->sends[0]['to']);
        $this->assertSame('insurance_certificate', $mailer->sends[0]['options']['category']);
        $this->assertSame($this->testTenantId, $mailer->sends[0]['options']['tenant_id']);
        $this->assertStringContainsString('Need updated expiry date', $mailer->sends[0]['body']);
    }

    // ================================================================
    // DELETE — DELETE /v2/admin/insurance/{id}
    // ================================================================

    public function test_destroy_returns_404_for_nonexistent_certificate(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiDelete('/v2/admin/insurance/99999');

        $response->assertStatus(404);
    }

    // ================================================================
    // USER CERTIFICATES — GET /v2/admin/insurance/user/{userId}
    // ================================================================

    public function test_user_certificates_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/insurance/user/' . $user->id);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_user_certificates_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/insurance/user/1');

        $response->assertStatus(403);
    }

    private function createCertificateForUser(User $user, array $overrides = []): int
    {
        return (int) DB::table('insurance_certificates')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'insurance_type' => 'public_liability',
            'provider_name' => 'Audit Insurance Co',
            'policy_number' => 'AUDIT-123',
            'status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function fakeEmailDispatchService(): EmailDispatchService
    {
        $mailer = new class extends EmailDispatchService {
            /** @var list<array{to:string,subject:string,body:string,options:array<string,mixed>}> */
            public array $sends = [];

            public function send(string $to, string $subject, string $body, array $options = []): bool
            {
                $this->sends[] = [
                    'to' => $to,
                    'subject' => $subject,
                    'body' => $body,
                    'options' => $options,
                ];

                return true;
            }
        };

        app()->instance(EmailDispatchService::class, $mailer);

        return $mailer;
    }
}
