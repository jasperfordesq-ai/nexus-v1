<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\CaringCommunity;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\CaringCommunity\ExternalIntegrationBacklogService;
use App\Services\CaringCommunity\OperatingPolicyService;
use App\Services\CaringCommunity\PilotDisclosurePackService;
use App\Services\CaringCommunity\PilotLaunchReadinessService;
use App\Services\CaringCommunity\PilotScoreboardService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * E2 — Pilot launch readiness must be a real gate, not a status banner.
 *
 * Verifies:
 *   - report() exposes a can_launch flag and a blockers list
 *   - launchPilot endpoint returns 422 CANNOT_LAUNCH when the gate is open
 *   - launchPilot endpoint returns 422 ALREADY_LAUNCHED on subsequent calls
 *   - successful launch persists pilot_launched_at / pilot_launched_by
 */
class PilotLaunchEnforcementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setCaringCommunityFeature($this->testTenantId, true);
        $this->ensureScoreboardColumnsExist();
    }

    /**
     * Mirror PilotLaunchReadinessTest::ensureScoreboardColumnsExist — the
     * scoreboard service reads several columns that aren't in the legacy
     * test schema dump but exist in production. Adding them here keeps the
     * test deterministic without polluting the rest of the suite.
     */
    private function ensureScoreboardColumnsExist(): void
    {
        if (Schema::hasTable('messages')) {
            if (!Schema::hasColumn('messages', 'from_user_id')) {
                DB::statement('ALTER TABLE messages ADD COLUMN from_user_id INT NULL');
            }
            if (!Schema::hasColumn('messages', 'to_user_id')) {
                DB::statement('ALTER TABLE messages ADD COLUMN to_user_id INT NULL');
            }
        }

        if (Schema::hasTable('merchant_coupons') && !Schema::hasColumn('merchant_coupons', 'user_id')) {
            DB::statement('ALTER TABLE merchant_coupons ADD COLUMN user_id INT NULL');
        }
    }

    private function setCaringCommunityFeature(int $tenantId, bool $enabled): void
    {
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        $features = [];
        if ($tenant && !empty($tenant->features)) {
            $decoded = is_string($tenant->features) ? json_decode($tenant->features, true) : $tenant->features;
            $features = is_array($decoded) ? $decoded : [];
        }
        $features['caring_community'] = $enabled;
        DB::table('tenants')
            ->where('id', $tenantId)
            ->update(['features' => json_encode($features)]);
        TenantContext::setById($tenantId);
    }

    private function makeAdmin(int $tenantId): User
    {
        return User::factory()->forTenant($tenantId)->admin()->create();
    }

    /**
     * Remove rows that would push the AG84 data-quality section into the
     * `blocked` (danger) state on the shared test DB: duplicate emails,
     * seed-marker accounts, and overdue help requests. The user we just
     * created for the test (which seeds a freshly random email) is left
     * intact — only OTHER tenant rows get scrubbed.
     */
    private function cleanDataQualityForTenant(int $tenantId): void
    {
        if (Schema::hasTable('users')) {
            // Drop any seed-marker users (DataQualityService SEVERITY_DANGER).
            DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) {
                    $q->where('email', 'LIKE', '%@example.com')
                      ->orWhere('email', 'LIKE', '%@example.org')
                      ->orWhere('email', 'LIKE', '%@test.test')
                      ->orWhere('name', 'LIKE', 'Test %')
                      ->orWhere('name', 'LIKE', 'Demo %');
                })
                ->delete();

            // Collapse duplicate emails (case-insensitive) — keep the lowest id
            // for each email and delete the rest.
            $duplicates = DB::table('users')
                ->where('tenant_id', $tenantId)
                ->whereNotNull('email')
                ->where('email', '<>', '')
                ->select(DB::raw('LOWER(email) AS lemail'), DB::raw('MIN(id) AS keep_id'), DB::raw('COUNT(*) AS c'))
                ->groupBy(DB::raw('LOWER(email)'))
                ->having('c', '>', 1)
                ->get();
            foreach ($duplicates as $row) {
                DB::table('users')
                    ->where('tenant_id', $tenantId)
                    ->whereRaw('LOWER(email) = ?', [$row->lemail])
                    ->where('id', '<>', (int) $row->keep_id)
                    ->delete();
            }

            // Same for phone numbers.
            if (Schema::hasColumn('users', 'phone')) {
                $dupePhones = DB::table('users')
                    ->where('tenant_id', $tenantId)
                    ->whereNotNull('phone')
                    ->where('phone', '<>', '')
                    ->select(DB::raw('phone'), DB::raw('MIN(id) AS keep_id'), DB::raw('COUNT(*) AS c'))
                    ->groupBy('phone')
                    ->having('c', '>', 1)
                    ->get();
                foreach ($dupePhones as $row) {
                    DB::table('users')
                        ->where('tenant_id', $tenantId)
                        ->where('phone', $row->phone)
                        ->where('id', '<>', (int) $row->keep_id)
                        ->delete();
                }
            }
        }

        // Overdue help requests (>30 days, status=pending, count>10) push the
        // section into danger. Mark them resolved for the duration of this test.
        if (Schema::hasTable('caring_help_requests')) {
            DB::table('caring_help_requests')
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->where('created_at', '<', now()->subDays(30))
                ->update(['status' => 'resolved', 'updated_at' => now()]);
        }
    }

    /**
     * Force every section of the readiness report into a `ready` status by
     * delegating to the real services that write to tenant_settings — that
     * way the test stays decoupled from the storage schema (single envelopes
     * vs key/value pairs vary across services).
     */
    private function makeAllSectionsReady(int $tenantId, int $adminId): void
    {
        // 1. Disclosure pack — stored as a single JSON envelope under
        //    `caring.disclosure_pack`. Filling controller + DPO + incident
        //    contact flips the section to READY.
        app(PilotDisclosurePackService::class)->update($tenantId, [
            'controller' => [
                'name' => 'KISS Test Verein',
                'contact_email' => 'controller@example.test',
                'data_protection_officer' => 'DPO Person',
            ],
            'incident_response' => [
                'contact_email' => 'incident@example.test',
            ],
        ]);

        // 2. Operating policy — discrete `caring.operating_policy.*` keys.
        //    Appendix URL + safeguarding owner are the gate.
        $policy = app(OperatingPolicyService::class);
        if (method_exists($policy, 'update')) {
            $policy->update($tenantId, [
                'policy_appendix_url' => 'https://example.test/appendix.pdf',
                'safeguarding_escalation_user_id' => $adminId,
            ]);
        } else {
            // Fallback: write directly. KEY_PREFIX = caring.operating_policy.
            DB::table('tenant_settings')->updateOrInsert(
                ['tenant_id' => $tenantId, 'setting_key' => 'caring.operating_policy.policy_appendix_url'],
                [
                    'setting_value' => 'https://example.test/appendix.pdf',
                    'setting_type' => 'string',
                    'category' => 'caring_community',
                    'description' => 'Test seed',
                    'updated_at' => now(),
                ],
            );
            DB::table('tenant_settings')->updateOrInsert(
                ['tenant_id' => $tenantId, 'setting_key' => 'caring.operating_policy.safeguarding_escalation_user_id'],
                [
                    'setting_value' => (string) $adminId,
                    'setting_type' => 'integer',
                    'category' => 'caring_community',
                    'description' => 'Test seed',
                    'updated_at' => now(),
                ],
            );
        }

        // 3. Commercial boundary acknowledgement — uses the readiness service
        //    helper that writes the canonical setting.
        app(PilotLaunchReadinessService::class)->acknowledgeBoundary($tenantId);

        // 4. Pilot scoreboard pre-pilot baseline — service knows the schema.
        if (Schema::hasTable('caring_kpi_baselines')) {
            DB::table('caring_kpi_baselines')->where('tenant_id', $tenantId)->delete();
            app(PilotScoreboardService::class)
                ->capturePrePilotBaseline($tenantId, $adminId, 'Test seed');
        }

        // 5. External integration backlog — JSON envelope under
        //    `caring.external_integrations`, written via the service so we
        //    stay schema-agnostic.
        $backlog = app(ExternalIntegrationBacklogService::class);
        if (method_exists($backlog, 'seedDefaults')) {
            $backlog->seedDefaults($tenantId);
        } elseif (method_exists($backlog, 'create')) {
            $backlog->create($tenantId, [
                'item_key' => 'test-integration',
                'title'    => 'Test integration',
                'status'   => 'live',
            ]);
        }

        // 6. Data quality — clean up duplicate emails / phones / seed-marker
        //    users / overdue help requests that prior tests may have left
        //    behind in the shared test DB. Fresh tenants pass naturally; we
        //    just ensure the historic state is clean.
        $this->cleanDataQualityForTenant($tenantId);

        // 7. Isolated-node — not required for the default (hosted) deployment
        //    so the section is already informational/ready.
    }

    public function test_report_exposes_can_launch_flag(): void
    {
        $admin = $this->makeAdmin($this->testTenantId);
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/caring-community/launch-readiness');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'can_launch',
                'blockers',
                'launched',
            ],
        ]);

        // Fresh tenant should never be launchable.
        $this->assertFalse((bool) $response->json('data.can_launch'));
        $this->assertIsArray($response->json('data.blockers'));
        $this->assertNotEmpty($response->json('data.blockers'));
    }

    public function test_launch_returns_422_when_gate_is_open(): void
    {
        $admin = $this->makeAdmin($this->testTenantId);
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/caring-community/launch-readiness/launch');

        $response->assertStatus(422);
        // CANNOT_LAUNCH responses use the custom shape (error.code + error.blockers)
        // because we need to surface the structured blockers array.
        $response->assertJsonPath('error.code', 'CANNOT_LAUNCH');
        $this->assertIsArray($response->json('error.blockers'));
        $this->assertNotEmpty($response->json('error.blockers'));
    }

    public function test_successful_launch_persists_state_and_subsequent_calls_return_already_launched(): void
    {
        // Defensive: clear any state that other tests may have left behind in
        // shared test DBs — particularly the AG85 deployment_mode envelope
        // (which would otherwise mark isolated_node as required), and any
        // pilot_launched_at flag from a prior run of this same test.
        DB::table('tenant_settings')
            ->where('tenant_id', $this->testTenantId)
            ->whereIn('setting_key', [
                'caring.isolated_node.deployment_mode',
                'caring_community.pilot_launched_at',
                'caring_community.pilot_launched_by',
            ])
            ->delete();

        $admin = $this->makeAdmin($this->testTenantId);
        $this->makeAllSectionsReady($this->testTenantId, $admin->id);

        // Verify can_launch is true now.
        $service = app(PilotLaunchReadinessService::class);
        $report = $service->report($this->testTenantId);
        $this->assertTrue(
            (bool) ($report['can_launch'] ?? false),
            'Test setup failure: makeAllSectionsReady() did not flip can_launch true. Sections: '
                . json_encode(array_map(fn ($s) => [$s['key'] => $s['status']], $report['sections']))
        );

        Sanctum::actingAs($admin);

        $first = $this->apiPost('/v2/admin/caring-community/launch-readiness/launch');
        $first->assertStatus(200);
        $first->assertJsonStructure([
            'data' => ['launched_at', 'launched_by_id', 'report'],
        ]);
        $this->assertSame($admin->id, (int) $first->json('data.launched_by_id'));

        $persistedAt = DB::table('tenant_settings')
            ->where('tenant_id', $this->testTenantId)
            ->where('setting_key', 'caring_community.pilot_launched_at')
            ->value('setting_value');
        $persistedBy = DB::table('tenant_settings')
            ->where('tenant_id', $this->testTenantId)
            ->where('setting_key', 'caring_community.pilot_launched_by')
            ->value('setting_value');
        $this->assertNotNull($persistedAt, 'pilot_launched_at must be persisted');
        $this->assertSame((string) $admin->id, (string) $persistedBy);

        // The report after launch should report `launched` as set, and
        // can_launch must flip false (already-launched is a one-way state).
        $afterReport = $service->report($this->testTenantId);
        $this->assertNotNull($afterReport['launched']);
        $this->assertFalse((bool) $afterReport['can_launch']);

        // Second call must return ALREADY_LAUNCHED. Since this is emitted via
        // respondWithError(), the body shape is `errors: [{code,message}]`.
        $second = $this->apiPost('/v2/admin/caring-community/launch-readiness/launch');
        $second->assertStatus(422);
        $second->assertJsonPath('errors.0.code', 'ALREADY_LAUNCHED');
    }

    public function test_non_admin_member_cannot_launch(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/caring-community/launch-readiness/launch');

        $response->assertStatus(403);
    }
}
