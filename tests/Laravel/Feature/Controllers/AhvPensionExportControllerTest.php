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
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

class AhvPensionExportControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function setCaringCommunityFeature(bool $enabled): void
    {
        $tenant = DB::table('tenants')->where('id', $this->testTenantId)->first();
        $features = [];
        if ($tenant && !empty($tenant->features)) {
            $decoded = is_string($tenant->features) ? json_decode($tenant->features, true) : $tenant->features;
            $features = is_array($decoded) ? $decoded : [];
        }

        $features['caring_community'] = $enabled;
        DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->update(['features' => json_encode($features)]);
        TenantContext::setById($this->testTenantId);
    }

    public function test_member_can_export_provisional_ahv_evidence_rows(): void
    {
        $this->setCaringCommunityFeature(true);

        $member = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        DB::table('vol_logs')->insert([
            [
                'tenant_id' => $this->testTenantId,
                'user_id' => $member->id,
                'date_logged' => '2026-01-10',
                'hours' => 2.5,
                'status' => 'approved',
                'description' => 'Care visit',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $this->testTenantId,
                'user_id' => $member->id,
                'date_logged' => '2026-02-10',
                'hours' => 4.0,
                'status' => 'approved',
                'description' => 'Companion support',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $this->testTenantId,
                'user_id' => $member->id,
                'date_logged' => '2026-03-10',
                'hours' => 9.0,
                'status' => 'pending',
                'description' => 'Unverified support',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Sanctum::actingAs($member);
        $response = $this->apiGet('/v2/caring-community/my-ahv-pension-export?from=2026-01-01&to=2026-12-31');

        $response->assertStatus(200);
        $response->assertJsonPath('data.official_interface.status', 'pending_official_ahv_specification');
        $response->assertJsonPath('data.official_interface.official_submission_supported', false);
        $response->assertJsonPath('data.summary.approved_hours', 6.5);
        $response->assertJsonPath('data.summary.row_count', 2);
        $response->assertJsonPath('data.summary.years.0.year', 2026);
        $response->assertJsonPath('data.summary.years.0.approved_hours', 6.5);
        $response->assertJsonCount(2, 'data.contribution_rows');
    }

    public function test_ahv_export_respects_caring_community_feature_gate(): void
    {
        $this->setCaringCommunityFeature(false);

        $member = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($member);
        $response = $this->apiGet('/v2/caring-community/my-ahv-pension-export');

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'FEATURE_DISABLED');
    }
}
