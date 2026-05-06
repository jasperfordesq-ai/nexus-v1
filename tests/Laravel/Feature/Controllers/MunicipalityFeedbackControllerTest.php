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

class MunicipalityFeedbackControllerTest extends TestCase
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

    public function test_member_feedback_submit_is_blocked_when_caring_community_is_disabled(): void
    {
        $this->setCaringCommunityFeature(false);
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->create());

        $response = $this->apiPost('/v2/caring-community/feedback', [
            'category' => 'idea',
            'subject' => 'More local transport support',
            'body' => 'Please coordinate more volunteer driver slots.',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'FEATURE_DISABLED');
    }

    public function test_member_feedback_history_is_blocked_when_caring_community_is_disabled(): void
    {
        $this->setCaringCommunityFeature(false);
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->create());

        $response = $this->apiGet('/v2/caring-community/feedback/mine');

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'FEATURE_DISABLED');
    }
}
