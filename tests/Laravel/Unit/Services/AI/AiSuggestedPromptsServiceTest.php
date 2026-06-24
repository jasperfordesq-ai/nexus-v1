<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\AI;

use App\Core\TenantContext;
use App\Services\AI\AiSuggestedPromptsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * AiSuggestedPromptsServiceTest
 *
 * Tests AiSuggestedPromptsService::pick() which returns static prompts
 * filtered by the tenant's enabled features/modules.  No DB writes needed
 * beyond setting up TenantContext via setById(2) or by directly manipulating
 * the tenant's in-memory row via reflection/DB updates.
 *
 * Strategy:
 *   - Use TenantContext::setById(2) for the default real tenant (has features
 *     and configuration columns) — exercises the real code path.
 *   - Inject a synthetic tenant row to verify specific feature flag combinations.
 *   - Verify count, content, and that 'general' prompts always appear.
 */
class AiSuggestedPromptsServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    private AiSuggestedPromptsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->service = new AiSuggestedPromptsService();
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Build a "zero" features array with every known feature disabled, then
     * selectively enable the given ones.  This prevents FEATURE_DEFAULTS from
     * bleeding in via mergeFeatures() and polluting pool assertions.
     */
    private static function zeroFeatures(array $enable = []): array
    {
        $all = array_fill_keys(array_keys(\App\Services\TenantFeatureConfig::FEATURE_DEFAULTS), false);
        foreach ($enable as $key => $val) {
            $all[$key] = (bool) $val;
        }
        return $all;
    }

    /**
     * Build a "zero" modules array with every known module disabled, then
     * selectively enable the given ones.
     */
    private static function zeroModules(array $enable = []): array
    {
        $all = array_fill_keys(array_keys(\App\Services\TenantFeatureConfig::MODULE_DEFAULTS), false);
        foreach ($enable as $key => $val) {
            $all[$key] = (bool) $val;
        }
        return $all;
    }

    /**
     * Persist a synthetic feature/module configuration for tenant 2 and reload
     * TenantContext.  DatabaseTransactions rolls this back after each test.
     */
    private function setFakeTenant(array $features = [], array $modules = []): void
    {
        $featuresJson  = json_encode($features);
        $modulesConfig = json_encode(['modules' => $modules]);

        DB::table('tenants')->where('id', self::TENANT_ID)->update([
            'features'      => $featuresJson,
            'configuration' => $modulesConfig,
        ]);

        // Re-load so TenantContext picks up the updated row.
        TenantContext::setById(self::TENANT_ID);
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    public function test_returns_exactly_five_prompts_by_default(): void
    {
        TenantContext::setById(self::TENANT_ID);

        $prompts = $this->service->pick(5);

        $this->assertCount(5, $prompts);
    }

    public function test_returns_requested_count(): void
    {
        TenantContext::setById(self::TENANT_ID);

        $prompts = $this->service->pick(3);

        $this->assertCount(3, $prompts);
    }

    public function test_returns_at_least_one_even_if_count_is_zero(): void
    {
        TenantContext::setById(self::TENANT_ID);

        // pick() uses max(1, $count) internally.
        $prompts = $this->service->pick(0);

        $this->assertGreaterThanOrEqual(1, count($prompts));
    }

    public function test_all_returned_prompts_are_non_empty_strings(): void
    {
        TenantContext::setById(self::TENANT_ID);

        $prompts = $this->service->pick(5);

        foreach ($prompts as $prompt) {
            $this->assertIsString($prompt);
            $this->assertNotEmpty($prompt);
        }
    }

    public function test_general_prompts_always_appear_in_pool(): void
    {
        // With ALL features AND modules disabled, only 'general' prompts remain in
        // the pool.  There are exactly 3 general prompts; asking for 3 must return
        // all three from the general pool.
        $this->setFakeTenant(
            features: self::zeroFeatures(),
            modules:  self::zeroModules()
        );

        $generalPool = [
            'How do I create a listing?',
            'How do I message another member?',
            'How do I update my skills?',
        ];

        $prompts = $this->service->pick(3);

        foreach ($prompts as $prompt) {
            $this->assertContains($prompt, $generalPool, "Expected only general prompts when all features disabled; got: {$prompt}");
        }
    }

    public function test_listings_prompts_included_when_module_enabled(): void
    {
        // Zero everything; enable only listings module.
        $this->setFakeTenant(
            features: self::zeroFeatures(),
            modules:  self::zeroModules(['listings' => true])
        );

        $listingsPool = [
            'Find me someone who can help with gardening',
            'What offers are available this week?',
            'I need help with translation — who can help?',
        ];
        $generalPool = [
            'How do I create a listing?',
            'How do I message another member?',
            'How do I update my skills?',
        ];
        // listings(3) + general(3) = 6 candidates; ask for all 6.
        $prompts = $this->service->pick(6);

        $this->assertCount(6, $prompts);

        $allKnown = array_merge($listingsPool, $generalPool);
        foreach ($prompts as $prompt) {
            $this->assertContains($prompt, $allKnown, "Unexpected prompt: {$prompt}");
        }
    }

    public function test_wallet_prompts_included_when_module_enabled(): void
    {
        // Zero everything; enable only wallet module.
        $this->setFakeTenant(
            features: self::zeroFeatures(),
            modules:  self::zeroModules(['wallet' => true])
        );

        $walletPool = [
            'How many hours do I have?',
            'How do time credits work?',
        ];
        $generalPool = [
            'How do I create a listing?',
            'How do I message another member?',
            'How do I update my skills?',
        ];
        // wallet(2) + general(3) = 5 total candidates; ask for 5.
        $prompts = $this->service->pick(5);

        $this->assertCount(5, $prompts);
        foreach ($prompts as $prompt) {
            $this->assertContains($prompt, array_merge($walletPool, $generalPool), "Unexpected prompt: {$prompt}");
        }
    }

    public function test_events_prompts_included_when_feature_enabled(): void
    {
        // Zero everything; enable only events feature.
        $this->setFakeTenant(
            features: self::zeroFeatures(['events' => true]),
            modules:  self::zeroModules()
        );

        $eventsPool = [
            'What events are coming up this weekend?',
            'Are there any workshops on this month?',
        ];
        $generalPool = [
            'How do I create a listing?',
            'How do I message another member?',
            'How do I update my skills?',
        ];

        // events(2) + general(3) = 5.
        $prompts = $this->service->pick(5);
        $this->assertCount(5, $prompts);

        foreach ($prompts as $prompt) {
            $this->assertContains($prompt, array_merge($eventsPool, $generalPool), "Unexpected prompt: {$prompt}");
        }
    }

    public function test_no_duplicate_prompts_in_result(): void
    {
        TenantContext::setById(self::TENANT_ID);

        $prompts = $this->service->pick(5);

        $unique = array_unique($prompts);
        $this->assertCount(count($prompts), $unique, 'pick() returned duplicate prompts');
    }

    public function test_deterministic_per_tenant_seed_returns_same_order(): void
    {
        TenantContext::setById(self::TENANT_ID);

        $first  = $this->service->pick(5);
        $second = $this->service->pick(5);

        $this->assertSame($first, $second, 'pick() must be deterministic for the same tenant');
    }

    public function test_volunteering_prompts_when_feature_enabled(): void
    {
        // Zero everything; enable only volunteering feature.
        $this->setFakeTenant(
            features: self::zeroFeatures(['volunteering' => true]),
            modules:  self::zeroModules()
        );

        // volunteering(1) + general(3) = 4; ask for 4.
        $prompts = $this->service->pick(4);
        $this->assertCount(4, $prompts);

        $knownPool = [
            'What volunteering shifts are available?',
            'How do I create a listing?',
            'How do I message another member?',
            'How do I update my skills?',
        ];
        foreach ($prompts as $prompt) {
            $this->assertContains($prompt, $knownPool, "Unexpected prompt: {$prompt}");
        }
    }
}
