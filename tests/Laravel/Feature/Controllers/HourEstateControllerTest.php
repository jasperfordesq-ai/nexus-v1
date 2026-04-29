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

class HourEstateControllerTest extends TestCase
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

    private function requireEstateTable(): void
    {
        if (!Schema::hasTable('caring_hour_estates')) {
            $this->markTestSkipped('Legacy hour estate table is not present in the test database.');
        }
    }

    public function test_member_can_nominate_beneficiary_and_admin_can_settle_legacy_hours(): void
    {
        $this->requireEstateTable();
        $this->setCaringCommunityFeature(true);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $member = User::factory()->forTenant($this->testTenantId)->create(['balance' => 12]);
        $beneficiary = User::factory()->forTenant($this->testTenantId)->create(['balance' => 3]);

        Sanctum::actingAs($member);
        $nomination = $this->apiPut('/v2/caring-community/hour-estate', [
            'policy_action' => 'transfer_to_beneficiary',
            'beneficiary_user_id' => $beneficiary->id,
            'policy_document_reference' => 'Fondation KISS legacy-hours policy v1',
            'member_notes' => 'Please transfer my hours to my daughter.',
        ]);

        $nomination->assertStatus(200);
        $nomination->assertJsonPath('data.policy_action', 'transfer_to_beneficiary');
        $nomination->assertJsonPath('data.beneficiary_user_id', $beneficiary->id);
        $estateId = (int) $nomination->json('data.id');

        Sanctum::actingAs($admin);
        $reported = $this->apiPost("/v2/admin/caring-community/hour-estates/{$estateId}/report-deceased", [
            'coordinator_notes' => 'Confirmed by cooperative coordinator.',
        ]);

        $reported->assertStatus(200);
        $reported->assertJsonPath('data.status', 'reported');
        $reported->assertJsonPath('data.reported_balance_hours', 12);

        $settled = $this->apiPost("/v2/admin/caring-community/hour-estates/{$estateId}/settle", [
            'coordinator_notes' => 'Transferred according to nomination.',
        ]);

        $settled->assertStatus(200);
        $settled->assertJsonPath('data.status', 'settled');
        $settled->assertJsonPath('data.settled_hours', 12);

        $this->assertEqualsWithDelta(0.0, (float) DB::table('users')->where('id', $member->id)->value('balance'), 0.001);
        $this->assertEqualsWithDelta(15.0, (float) DB::table('users')->where('id', $beneficiary->id)->value('balance'), 0.001);
    }

    public function test_hour_estate_routes_respect_caring_community_feature_gate(): void
    {
        $this->requireEstateTable();
        $this->setCaringCommunityFeature(false);

        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/caring-community/hour-estate');

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'FEATURE_DISABLED');
    }
}
