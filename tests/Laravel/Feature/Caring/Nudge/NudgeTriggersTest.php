<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Caring\Nudge;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\CaringCommunity\CaringNudgeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

/**
 * Tests for the new nudge triggers added to CaringNudgeService:
 *  - helper_at_risk
 *  - unfulfilled_help_request
 *  - low_coverage_subregion
 */
class NudgeTriggersTest extends TestCase
{
    use DatabaseTransactions;

    private function bootTenant(): void
    {
        TenantContext::setById($this->testTenantId);
    }

    public function test_helper_at_risk_trigger_emits_candidate_for_lapsed_helper(): void
    {
        $this->bootTenant();
        if (!Schema::hasTable('vol_logs') || !Schema::hasTable('caring_smart_nudges')) {
            $this->markTestSkipped('Required tables missing.');
        }

        $helper = User::factory()->forTenant($this->testTenantId)->create();

        // Activity 50 days ago (in prior window), nothing in last 21 days → at risk
        DB::table('vol_logs')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $helper->id,
            'date_logged' => date('Y-m-d', strtotime('-50 days')),
            'hours' => 2.0,
            'description' => 'Old',
            'status' => 'approved',
            'created_at' => now()->subDays(50),
        ]);

        $service = app(CaringNudgeService::class);
        $candidates = $service->previewCandidates($this->testTenantId, 50);

        $found = collect($candidates)->firstWhere('source_type', 'helper_at_risk');
        $this->assertNotNull($found, 'helper_at_risk candidate not produced');
        $this->assertSame($helper->id, (int) $found['target_user']['id']);
    }

    public function test_unfulfilled_help_request_trigger_targets_coordinator(): void
    {
        $this->bootTenant();
        if (!Schema::hasTable('caring_help_requests') || !Schema::hasTable('caring_smart_nudges')) {
            $this->markTestSkipped('Required tables missing.');
        }

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $requester = User::factory()->forTenant($this->testTenantId)->create();

        DB::table('caring_help_requests')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $requester->id,
            'what' => 'Need a ride',
            'when_needed' => 'Friday',
            'contact_preference' => 'either',
            'status' => 'pending',
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $candidates = app(CaringNudgeService::class)->previewCandidates($this->testTenantId, 50);

        $found = collect($candidates)->firstWhere('source_type', 'unfulfilled_help_request');
        $this->assertNotNull($found, 'unfulfilled_help_request candidate not produced');
        // Target should be a coordinator/admin (not the requester themselves)
        $this->assertNotSame($requester->id, (int) $found['target_user']['id']);
        $this->assertSame($requester->id, (int) $found['related_user']['id']);
        $this->assertArrayHasKey('help_request_id', $found['signals']);
        // Sanity: admin is a valid coordinator target
        $this->assertGreaterThan(0, $admin->id);
    }

    public function test_recently_dispatched_at_risk_nudge_is_not_re_emitted(): void
    {
        $this->bootTenant();
        if (!Schema::hasTable('vol_logs') || !Schema::hasTable('caring_smart_nudges')) {
            $this->markTestSkipped('Required tables missing.');
        }

        $helper = User::factory()->forTenant($this->testTenantId)->create();

        DB::table('vol_logs')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $helper->id,
            'date_logged' => date('Y-m-d', strtotime('-50 days')),
            'hours' => 2.0,
            'description' => 'Old',
            'status' => 'approved',
            'created_at' => now()->subDays(50),
        ]);

        // Pre-existing nudge of the same source_type within cooldown
        DB::table('caring_smart_nudges')->insert([
            'tenant_id' => $this->testTenantId,
            'target_user_id' => $helper->id,
            'related_user_id' => null,
            'source_type' => 'helper_at_risk',
            'score' => 0.7,
            'signals' => json_encode([]),
            'status' => 'sent',
            'sent_at' => now()->subDays(2),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $candidates = app(CaringNudgeService::class)->previewCandidates($this->testTenantId, 50);

        $matching = collect($candidates)->where('source_type', 'helper_at_risk')->where('target_user.id', $helper->id);
        $this->assertCount(0, $matching, 'helper_at_risk candidate should be suppressed by cooldown');
    }
}
