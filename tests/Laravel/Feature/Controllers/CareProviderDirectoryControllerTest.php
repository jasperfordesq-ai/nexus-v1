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

class CareProviderDirectoryControllerTest extends TestCase
{
    use DatabaseTransactions;

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

    private function requireProviderTable(): void
    {
        if (! Schema::hasTable('caring_care_providers')) {
            $this->markTestSkipped('Care provider directory table is not present in the test database.');
        }
    }

    public function test_admin_can_create_verify_and_member_can_find_care_provider(): void
    {
        $this->requireProviderTable();
        $this->setCaringCommunityFeature(true);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($admin);

        $create = $this->apiPost('/v2/admin/caring-community/providers', [
            'name' => 'Regional Spitex Team',
            'type' => 'spitex',
            'description' => 'Home care and daily living support.',
            'categories' => ['homecare', 'nursing'],
            'address' => 'Care Street 12',
            'contact_phone' => '+41 44 555 0100',
            'contact_email' => 'hello@example.test',
            'website_url' => 'https://example.test',
        ]);

        $create->assertStatus(201);
        $create->assertJsonPath('data.name', 'Regional Spitex Team');
        $create->assertJsonPath('data.type', 'spitex');

        $providerId = (int) $create->json('data.id');

        $verify = $this->apiPost("/v2/admin/caring-community/providers/{$providerId}/verify");
        $verify->assertStatus(200);
        $verify->assertJsonPath('data.verified', true);

        Sanctum::actingAs($member);

        $list = $this->apiGet('/v2/caring-community/providers?type=spitex&search=Regional&verified_only=true');
        $list->assertStatus(200);
        $list->assertJsonPath('data.data.0.id', $providerId);
        $list->assertJsonPath('data.data.0.is_verified', true);

        $show = $this->apiGet("/v2/caring-community/providers/{$providerId}");
        $show->assertStatus(200);
        $show->assertJsonPath('data.name', 'Regional Spitex Team');
    }

    public function test_member_provider_directory_respects_caring_community_feature_gate(): void
    {
        $this->requireProviderTable();
        $this->setCaringCommunityFeature(false);

        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/caring-community/providers');

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'FEATURE_DISABLED');
    }

    public function test_admin_provider_create_rejects_cross_tenant_sub_region(): void
    {
        $this->requireProviderTable();
        if (! Schema::hasTable('caring_sub_regions') || ! Schema::hasColumn('caring_care_providers', 'sub_region_id')) {
            $this->markTestSkipped('Care provider sub-region columns are not present in the test database.');
        }

        $this->setCaringCommunityFeature(true);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $otherTenantId = DB::table('tenants')->where('id', '!=', $this->testTenantId)->value('id');
        if ($otherTenantId === null) {
            $this->markTestSkipped('A second tenant is required for cross-tenant sub-region validation.');
        }

        $subRegionId = DB::table('caring_sub_regions')->insertGetId([
            'tenant_id' => (int) $otherTenantId,
            'name' => 'Other Tenant Region',
            'slug' => 'other-tenant-region-' . uniqid(),
            'type' => 'quartier',
            'status' => 'active',
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/caring-community/providers', [
            'name' => 'Regional Spitex Team',
            'type' => 'spitex',
            'sub_region_id' => $subRegionId,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
        $response->assertJsonPath('errors.0.field', 'sub_region_id');
    }
}
