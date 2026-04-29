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

class CaregiverApiControllerTest extends TestCase
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

    private function requireCaregiverTables(): void
    {
        if (
            ! Schema::hasTable('caring_caregiver_links')
            || ! Schema::hasTable('caring_help_requests')
        ) {
            $this->markTestSkipped('Caregiver support tables are not present in the test database.');
        }
    }

    public function test_caregiver_can_link_receiver_and_create_on_behalf_help_request(): void
    {
        $this->requireCaregiverTables();
        $this->setCaringCommunityFeature(true);

        $caregiver = User::factory()->forTenant($this->testTenantId)->create();
        $careReceiver = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Mira Receiver',
        ]);
        Sanctum::actingAs($caregiver);

        $link = $this->apiPost('/v2/caring-community/caregiver/links', [
            'cared_for_id' => $careReceiver->id,
            'relationship_type' => 'family',
            'start_date' => now()->toDateString(),
            'notes' => 'Primary family support contact.',
            'is_primary' => true,
        ]);

        $link->assertStatus(201);
        $link->assertJsonPath('data.caregiver_id', $caregiver->id);
        $link->assertJsonPath('data.cared_for_id', $careReceiver->id);

        $links = $this->apiGet('/v2/caring-community/caregiver/links');
        $links->assertStatus(200);
        $links->assertJsonPath('data.0.cared_for_name', 'Mira Receiver');

        $request = $this->apiPost('/v2/caring-community/caregiver/request-on-behalf', [
            'cared_for_id' => $careReceiver->id,
            'title' => 'Medication pickup',
            'description' => 'Please collect the prescription before Friday afternoon.',
            'when_needed' => 'Friday afternoon',
            'contact_preference' => 'message',
        ]);

        $request->assertStatus(201);
        $request->assertJsonPath('data.user_id', $careReceiver->id);
        $request->assertJsonPath('data.requested_by_id', $caregiver->id);
        $request->assertJsonPath('data.is_on_behalf', 1);
        $request->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('caring_help_requests', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $careReceiver->id,
            'requested_by_id' => $caregiver->id,
            'is_on_behalf' => 1,
            'what' => "Medication pickup\n\nPlease collect the prescription before Friday afternoon.",
            'when_needed' => 'Friday afternoon',
            'contact_preference' => 'message',
            'status' => 'pending',
        ]);
    }

    public function test_caregiver_routes_respect_caring_community_feature_gate(): void
    {
        $this->requireCaregiverTables();
        $this->setCaringCommunityFeature(false);

        $caregiver = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($caregiver);

        $response = $this->apiGet('/v2/caring-community/caregiver/links');

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'FEATURE_DISABLED');
    }
}
