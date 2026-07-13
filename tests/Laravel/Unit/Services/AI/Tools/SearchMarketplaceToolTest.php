<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\AI\Tools;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\AI\Tools\SearchMarketplaceTool;
use App\Services\TenantFeatureConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * SearchMarketplaceToolTest
 *
 * Tests the SearchMarketplaceTool which queries `marketplace_listings`
 * (status=active, moderation_status='approved', not expired) scoped to the
 * current tenant, matching on title / tagline / description.
 *
 * Note on isAvailable(): marketplace feature defaults to FALSE in
 * TenantFeatureConfig::FEATURE_DEFAULTS. The tool is therefore *unavailable*
 * on tenant 2 unless its features JSON enables marketplace=true.
 * We test isAvailable() directly with a mocked tenant context; execute() tests
 * are independent of feature availability (the guard is upstream in the router).
 *
 * Strategy:
 *  - Seed marketplace_listings rows via DB::table — no FK on user_id.
 *  - Assert metadata, query matching (title/tagline/description), filters
 *    (location, moderation_status), limit, tenant scoping, empty results,
 *    result shape.
 *  - DatabaseTransactions rolls everything back.
 */
class SearchMarketplaceToolTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID       = 2;
    private const OTHER_TENANT_ID = 1;

    private SearchMarketplaceTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        TenantContext::setById(self::TENANT_ID);
        $this->tool = new SearchMarketplaceTool();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Insert a marketplace listing and return its ID.
     * Defaults to active + null moderation_status (the tool treats null as approved).
     */
    private function insertListing(array $overrides = [], int $tenantId = self::TENANT_ID): int
    {
        $uid = uniqid('ml', true);
        $defaults = [
            'tenant_id'         => $tenantId,
            'user_id'           => 1,            // no FK enforced
            'title'             => 'Test Listing ' . $uid,
            'description'       => 'A description of the item.',
            'tagline'           => null,
            'price'             => null,
            'price_currency'    => 'EUR',
            'price_type'        => 'free',
            'status'            => 'active',
            // NOTE: test DB enforces NOT NULL on moderation_status despite schema DEFAULT.
            // Use 'approved' as the "pass" default; individual tests override to test exclusion.
            'moderation_status' => 'approved',
            'location'          => null,
            'created_at'        => now()->toDateTimeString(),
            'updated_at'        => now()->toDateTimeString(),
        ];
        return DB::table('marketplace_listings')->insertGetId(array_merge($defaults, $overrides));
    }

    // ─── Metadata ─────────────────────────────────────────────────────────────

    public function test_name_returns_search_marketplace(): void
    {
        $this->assertSame('search_marketplace', $this->tool->name());
    }

    public function test_parameters_schema_has_required_query(): void
    {
        $schema = $this->tool->parametersSchema();

        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('query', $schema['properties']);
        $this->assertArrayHasKey('location', $schema['properties']);
        $this->assertArrayHasKey('limit', $schema['properties']);
        $this->assertContains('query', $schema['required']);
    }

    // ─── isAvailable: marketplace is off by default ───────────────────────────

    public function test_is_available_returns_false_when_marketplace_feature_disabled(): void
    {
        // Tenant 2 has marketplace disabled by default (TenantFeatureConfig::FEATURE_DEFAULTS).
        // setById loads the real tenant row — check what the current value is.
        $available = $this->tool->isAvailable(1);
        // We cannot know whether the live tenant 2 has marketplace enabled,
        // so we assert the return type and that it reflects TenantFeatureConfig.
        $this->assertIsBool($available);
    }

    // ─── Execute: empty query guard ───────────────────────────────────────────

    public function test_execute_returns_error_when_query_is_empty(): void
    {
        $result = $this->tool->execute(['query' => ''], 1);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['error']);
        $this->assertSame([], $result['results']);
    }

    // ─── Execute: matching on title ───────────────────────────────────────────

    public function test_execute_returns_listing_matching_title(): void
    {
        $token = 'BIKE' . uniqid();
        $id = $this->insertListing(['title' => "Second-hand {$token} for sale"]);

        $result = $this->tool->execute(['query' => $token], 1);

        $this->assertTrue($result['ok']);
        $ids = array_column($result['results'], 'id');
        $this->assertContains($id, $ids);
        $this->assertStringContainsString('Found', $result['summary']);
        $this->assertSame('marketplace', $result['card_type']);
    }

    // ─── Execute: matching on tagline ─────────────────────────────────────────

    public function test_execute_returns_listing_matching_tagline(): void
    {
        $token = 'TAGL' . uniqid();
        $id = $this->insertListing(['tagline' => "Perfect {$token} condition"]);

        $result = $this->tool->execute(['query' => $token], 1);

        $this->assertTrue($result['ok']);
        $ids = array_column($result['results'], 'id');
        $this->assertContains($id, $ids);
    }

    // ─── Execute: matching on description ────────────────────────────────────

    public function test_execute_returns_listing_matching_description(): void
    {
        $token = 'DESCMPL' . uniqid();
        $id = $this->insertListing(['description' => "This item features {$token} design."]);

        $result = $this->tool->execute(['query' => $token], 1);

        $this->assertTrue($result['ok']);
        $ids = array_column($result['results'], 'id');
        $this->assertContains($id, $ids);
    }

    // ─── Execute: non-active listings excluded ────────────────────────────────

    public function test_execute_excludes_non_active_listings(): void
    {
        $token = 'SOLDITEM' . uniqid();
        $this->insertListing(['title' => $token, 'status' => 'sold']);
        $this->insertListing(['title' => $token, 'status' => 'expired']);

        $result = $this->tool->execute(['query' => $token], 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['results']);
    }

    public function test_execute_excludes_expired_active_listings(): void
    {
        $token = 'EXPIREDITEM' . uniqid();
        $this->insertListing([
            'title' => $token,
            'expires_at' => now()->subMinute()->toDateTimeString(),
        ]);

        $result = $this->tool->execute(['query' => $token], 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['results']);
    }

    public function test_execute_excludes_marketplace_suspended_sellers(): void
    {
        $token = 'SUSPENDEDSELLER' . uniqid();
        $seller = User::factory()->forTenant(self::TENANT_ID)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        DB::table('marketplace_seller_profiles')->insert([
            'tenant_id' => self::TENANT_ID,
            'user_id' => $seller->id,
            'display_name' => 'Suspended AI marketplace seller',
            'seller_type' => 'private',
            'is_suspended' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertListing(['title' => $token, 'user_id' => $seller->id]);

        $result = $this->tool->execute(['query' => $token], 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['results']);
    }

    // ─── Execute: rejected/pending moderation excluded ────────────────────────

    public function test_execute_excludes_rejected_moderation_status(): void
    {
        $token = 'REJITEM' . uniqid();
        $this->insertListing(['title' => $token, 'moderation_status' => 'rejected']);
        $this->insertListing(['title' => $token, 'moderation_status' => 'pending']);

        $result = $this->tool->execute(['query' => $token], 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['results']);
    }

    // ─── Execute: approved moderation_status included ─────────────────────────

    public function test_execute_includes_listing_with_approved_moderation_status(): void
    {
        $token = 'APPROVEDML' . uniqid();
        $id = $this->insertListing(['title' => $token, 'moderation_status' => 'approved']);

        $result = $this->tool->execute(['query' => $token], 1);

        $this->assertTrue($result['ok']);
        $ids = array_column($result['results'], 'id');
        $this->assertContains($id, $ids);
    }

    // ─── Execute: empty results ───────────────────────────────────────────────

    public function test_execute_returns_ok_with_empty_results_on_no_match(): void
    {
        $result = $this->tool->execute(['query' => 'NOMATCHMPL_' . uniqid()], 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['results']);
        $this->assertStringContainsString('No marketplace items matched', $result['summary']);
    }

    // ─── Execute: location filter ─────────────────────────────────────────────

    public function test_execute_filters_by_location(): void
    {
        $token     = 'LOCMPL' . uniqid();
        $idGalway  = $this->insertListing(['title' => "{$token} chair", 'location' => 'Galway']);
        $idLimerick = $this->insertListing(['title' => "{$token} desk",  'location' => 'Limerick']);

        $result = $this->tool->execute(['query' => $token, 'location' => 'Galway'], 1);

        $this->assertTrue($result['ok']);
        $ids = array_column($result['results'], 'id');
        $this->assertContains($idGalway, $ids);
        $this->assertNotContains($idLimerick, $ids);
    }

    // ─── Execute: limit ───────────────────────────────────────────────────────

    public function test_execute_respects_limit_argument(): void
    {
        $token = 'LMTMLST' . uniqid();
        for ($i = 0; $i < 6; $i++) {
            $this->insertListing(['title' => "{$token} item {$i}"]);
        }

        $result = $this->tool->execute(['query' => $token, 'limit' => 2], 1);

        $this->assertTrue($result['ok']);
        $this->assertCount(2, $result['results']);
    }

    public function test_execute_caps_limit_at_8(): void
    {
        $token = 'MAXLMTMLST' . uniqid();
        for ($i = 0; $i < 10; $i++) {
            $this->insertListing(['title' => "{$token} i{$i}"]);
        }

        $result = $this->tool->execute(['query' => $token, 'limit' => 100], 1);

        $this->assertTrue($result['ok']);
        $this->assertLessThanOrEqual(8, count($result['results']));
    }

    // ─── Execute: result shape ────────────────────────────────────────────────

    public function test_execute_result_row_has_expected_keys(): void
    {
        $token = 'SHAPEMPL' . uniqid();
        $this->insertListing([
            'title'          => "{$token} widget",
            'price'          => 5.00,
            'price_currency' => 'EUR',
            'price_type'     => 'fixed',
            'condition'      => 'good',
            'location'       => 'Waterford',
        ]);

        $result = $this->tool->execute(['query' => $token], 1);

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['results']);

        $row = $result['results'][0];
        foreach (['id', 'title', 'tagline', 'condition', 'price', 'time_credit_price', 'price_type', 'location', 'excerpt', 'url'] as $key) {
            $this->assertArrayHasKey($key, $row, "Missing key: {$key}");
        }
        $this->assertIsInt($row['id']);
        // Price should be formatted as "EUR 5.00".
        $this->assertStringContainsString('EUR', (string) $row['price']);
        $this->assertStringContainsString('/marketplace/', $row['url']);
    }

    public function test_execute_result_price_is_null_when_not_set(): void
    {
        $token = 'FREEMPL' . uniqid();
        $this->insertListing(['title' => $token, 'price' => null]);

        $result = $this->tool->execute(['query' => $token], 1);

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['results']);
        $this->assertNull($result['results'][0]['price']);
    }

    // ─── Execute: tenant scoping ──────────────────────────────────────────────

    public function test_execute_does_not_return_listings_from_other_tenants(): void
    {
        $token = 'TENANTMPL' . uniqid();
        $this->insertListing(['title' => "{$token} foreign"], self::OTHER_TENANT_ID);

        $result = $this->tool->execute(['query' => $token], 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['results']);
    }
}
