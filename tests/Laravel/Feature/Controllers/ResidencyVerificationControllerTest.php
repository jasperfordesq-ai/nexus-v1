<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

class ResidencyVerificationControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_member_submits_residency_and_admin_attests_distinct_badge(): void
    {
        $this->requireResidencyTable();
        $this->setCaringCommunityFeature(true);

        $member = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'residency-member@example.test',
            'status' => 'active',
        ]);

        Sanctum::actingAs($member);

        $submit = $this->apiPost('/v2/me/residency-verification', [
            'declared_municipality' => 'Cham',
            'declared_postcode' => '6330',
            'declared_address' => 'Obermuehlestrasse 8',
            'evidence_note' => 'Coordinator viewed municipal registration letter.',
        ]);

        $submit->assertStatus(200);
        $submit->assertJsonPath('data.status', 'pending');
        $submit->assertJsonPath('data.badge.key', 'verified_residency');
        $submit->assertJsonPath('data.badge.verified', false);

        $verificationId = (int) $submit->json('data.verification.id');

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create([
            'email' => 'residency-admin@example.test',
        ]);
        Sanctum::actingAs($admin);

        $list = $this->apiGet('/v2/admin/residency-verifications?status=pending');
        $list->assertStatus(200);
        $list->assertJsonPath('data.items.0.declared_municipality', 'Cham');

        $attest = $this->apiPost("/v2/admin/residency-verifications/{$verificationId}/attest", [
            'decision' => 'approved',
        ]);

        $attest->assertStatus(200);
        $attest->assertJsonPath('data.status', 'approved');
        $attest->assertJsonPath('data.badge.key', 'verified_residency');
        $attest->assertJsonPath('data.badge.verified', true);
        $attest->assertJsonPath('data.verification.attested_by', $admin->id);

        Sanctum::actingAs($member);

        $status = $this->apiGet('/v2/me/residency-verification');
        $status->assertStatus(200);
        $status->assertJsonPath('data.status', 'approved');
        $status->assertJsonPath('data.badge.verified', true);
        $status->assertJsonPath('data.verification.declared_postcode', '6330');
    }

    public function test_residency_verification_respects_caring_community_feature_gate(): void
    {
        $this->requireResidencyTable();
        $this->setCaringCommunityFeature(false);

        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/me/residency-verification');

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'FEATURE_DISABLED');
    }

    public function test_member_residency_declaration_rejects_values_that_exceed_column_limits(): void
    {
        $this->requireResidencyTable();
        $this->setCaringCommunityFeature(true);

        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->create());

        $response = $this->apiPost('/v2/me/residency-verification', [
            'declared_municipality' => str_repeat('M', 121),
            'declared_postcode' => '12345',
            'declared_address' => 'A globally valid address',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
        $response->assertJsonPath('errors.0.field', 'declared_municipality');
    }

    private function requireResidencyTable(): void
    {
        if (! Schema::hasTable('member_residency_verifications')) {
            $this->markTestSkipped('Residency verification table is not present in the test database.');
        }
    }

    private function setCaringCommunityFeature(bool $enabled): void
    {
        $tenant = DB::table('tenants')->where('id', $this->testTenantId)->first();
        $features = [];
        if ($tenant && ! empty($tenant->features)) {
            $decoded = is_string($tenant->features) ? json_decode($tenant->features, true) : $tenant->features;
            $features = is_array($decoded) ? $decoded : [];
        }

        $features['caring_community'] = $enabled;
        DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->update(['features' => json_encode($features)]);
        TenantContext::setById($this->testTenantId);
    }
}
