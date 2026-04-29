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

class CaregiverCoverRequestControllerTest extends TestCase
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

    private function requireCoverTables(): void
    {
        if (! Schema::hasTable('caring_caregiver_links') || ! Schema::hasTable('caring_cover_requests')) {
            $this->markTestSkipped('Caregiver cover request tables are not present in the test database.');
        }
    }

    public function test_caregiver_can_create_cover_request_and_match_trusted_candidate(): void
    {
        $this->requireCoverTables();
        $this->setCaringCommunityFeature(true);

        $caregiver = User::factory()->forTenant($this->testTenantId)->create();
        $caredFor = User::factory()->forTenant($this->testTenantId)->create();
        $candidate = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Trusted Substitute',
            'trust_tier' => 3,
            'verification_status' => 'passed',
            'skills' => 'companionship,shopping',
        ]);
        User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Too New',
            'trust_tier' => 0,
            'skills' => 'companionship',
        ]);

        DB::table('caring_caregiver_links')->insert([
            'tenant_id' => $this->testTenantId,
            'caregiver_id' => $caregiver->id,
            'cared_for_id' => $caredFor->id,
            'relationship_type' => 'family',
            'is_primary' => true,
            'start_date' => '2026-04-29',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($caregiver);

        $create = $this->apiPost('/v2/caring-community/caregiver/cover-requests', [
            'cared_for_id' => $caredFor->id,
            'title' => 'Holiday cover',
            'briefing' => 'Morning check-in and groceries.',
            'required_skills' => 'companionship, shopping',
            'starts_at' => '2026-05-10 09:00:00',
            'ends_at' => '2026-05-12 17:00:00',
            'minimum_trust_tier' => 2,
            'urgency' => 'planned',
        ]);

        $create->assertStatus(201);
        $create->assertJsonPath('data.title', 'Holiday cover');
        $coverRequestId = (int) $create->json('data.id');

        $candidates = $this->apiGet("/v2/caring-community/caregiver/cover-requests/{$coverRequestId}/candidates");
        $candidates->assertStatus(200);
        $candidates->assertJsonPath('data.0.id', $candidate->id);
        $this->assertNotContains('Too New', collect($candidates->json('data'))->pluck('name')->all());

        $assign = $this->apiPost("/v2/caring-community/caregiver/cover-requests/{$coverRequestId}/assign", [
            'supporter_id' => $candidate->id,
        ]);

        $assign->assertStatus(200);
        $assign->assertJsonPath('data.status', 'matched');
        $assign->assertJsonPath('data.matched_supporter_id', $candidate->id);
    }

    public function test_cover_request_requires_active_caregiver_link(): void
    {
        $this->requireCoverTables();
        $this->setCaringCommunityFeature(true);

        $caregiver = User::factory()->forTenant($this->testTenantId)->create();
        $caredFor = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($caregiver);

        $response = $this->apiPost('/v2/caring-community/caregiver/cover-requests', [
            'cared_for_id' => $caredFor->id,
            'title' => 'Unexpected appointment',
            'starts_at' => '2026-05-10 09:00:00',
            'ends_at' => '2026-05-10 12:00:00',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'FORBIDDEN');
    }
}
