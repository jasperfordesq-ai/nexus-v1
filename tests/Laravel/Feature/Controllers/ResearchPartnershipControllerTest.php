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

class ResearchPartnershipControllerTest extends TestCase
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

    private function requireResearchTables(): void
    {
        foreach (['caring_research_partners', 'caring_research_consents', 'caring_research_dataset_exports'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped('Research partnership tables are not present in the test database.');
            }
        }
    }

    public function test_member_consent_and_admin_anonymised_dataset_export_flow(): void
    {
        $this->requireResearchTables();
        $this->setCaringCommunityFeature(true);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create(['status' => 'active']);
        $members = [];
        for ($i = 0; $i < 5; $i++) {
            $members[] = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        }

        Sanctum::actingAs($members[0]);
        $defaultConsent = $this->apiGet('/v2/caring-community/research/consent');
        $defaultConsent->assertStatus(200);
        $defaultConsent->assertJsonPath('data.consent_status', 'opted_out');

        $updatedConsent = $this->apiPut('/v2/caring-community/research/consent', [
            'consent_status' => 'opted_in',
            'notes' => 'Happy to support anonymised research.',
        ]);
        $updatedConsent->assertStatus(200);
        $updatedConsent->assertJsonPath('data.consent_status', 'opted_in');

        Sanctum::actingAs($admin);
        $partner = $this->apiPost('/v2/admin/caring-community/research/partners', [
            'name' => 'Pilot Evaluation 2026',
            'institution' => 'Applied Ageing Institute',
            'contact_email' => 'research@example.test',
            'agreement_reference' => 'DSA-2026-AGORIS',
            'methodology_url' => 'https://example.test/methodology',
            'status' => 'active',
            'data_scope' => ['datasets' => ['caring_community_aggregate_v1']],
        ]);

        $partner->assertStatus(201);
        $partner->assertJsonPath('data.name', 'Pilot Evaluation 2026');
        $partnerId = (int) $partner->json('data.id');

        foreach ($members as $index => $member) {
            DB::table('vol_logs')->insert([
                'tenant_id' => $this->testTenantId,
                'user_id' => $member->id,
                'organization_id' => null,
                'date_logged' => '2026-04-0' . ($index + 1),
                'hours' => 1.00 + $index,
                'description' => 'Anonymised support activity.',
                'status' => 'approved',
                'created_at' => '2026-04-01 09:00:00',
                'updated_at' => '2026-04-01 09:00:00',
            ]);
        }

        $export = $this->apiPost("/v2/admin/caring-community/research/partners/{$partnerId}/dataset-exports", [
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
        ]);

        $export->assertStatus(201);
        $export->assertJsonPath('data.export.partner_id', $partnerId);
        $export->assertJsonPath('data.export.anonymization_version', 'aggregate-v1');
        $export->assertJsonPath('data.dataset.anonymization.direct_identifiers', false);
        $export->assertJsonPath('data.dataset.rows.0.suppressed', false);
        $this->assertNotEmpty($export->json('data.export.data_hash'));
        $this->assertDatabaseHas('caring_research_dataset_exports', [
            'tenant_id' => $this->testTenantId,
            'partner_id' => $partnerId,
            'dataset_key' => 'caring_community_aggregate_v1',
        ]);
    }

    public function test_research_routes_respect_caring_community_feature_gate(): void
    {
        $this->requireResearchTables();
        $this->setCaringCommunityFeature(false);

        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/caring-community/research/consent');

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'FEATURE_DISABLED');
    }
}
