<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\AI\Tools;

use App\Core\TenantContext;
use App\Services\AI\Tools\SearchListingsTool;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * SearchListingsToolTest
 *
 * Tests the SearchListingsTool: metadata shape, full-text search,
 * type filtering, location filtering, limit enforcement, moderation
 * gating, inactive-status exclusion, tenant scoping, and empty results.
 */
class SearchListingsToolTest extends \Tests\Laravel\TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    private SearchListingsTool $tool;
    private int $ownerUserId;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        TenantContext::setById(self::TENANT_ID);
        $this->tool = new SearchListingsTool();
        // Seed a minimal owner user for listing FK
        $uid = uniqid('sltest_', true);
        $this->ownerUserId = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'ListingOwner ' . $uid,
            'first_name' => 'Listing',
            'last_name'  => 'Owner',
            'email'      => $uid . '@example.test',
            'status'     => 'active',
            'balance'    => 0.0,
            'role'       => 'member',
            'is_approved' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function insertListing(array $overrides = []): int
    {
        $uid = uniqid('lst_', true);
        $defaults = [
            'tenant_id'         => self::TENANT_ID,
            'user_id'           => $this->ownerUserId,
            'title'             => 'Test Listing ' . $uid,
            'description'       => 'A test listing for unit tests',
            'type'              => 'offer',
            'status'            => 'active',
            'moderation_status' => null,
            'location'          => 'Dublin',
            'hours_estimate'    => 2.0,
            'is_featured'       => 0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ];
        return DB::table('listings')->insertGetId(array_merge($defaults, $overrides));
    }

    // ── Metadata ──────────────────────────────────────────────────────────────

    public function test_name_returns_expected_string(): void
    {
        $this->assertSame('search_listings', $this->tool->name());
    }

    public function test_description_is_non_empty(): void
    {
        $this->assertNotEmpty($this->tool->description());
    }

    public function test_parameters_schema_requires_query(): void
    {
        $schema = $this->tool->parametersSchema();

        $this->assertSame('object', $schema['type']);
        $this->assertContains('query', $schema['required']);
    }

    public function test_parameters_schema_has_expected_properties(): void
    {
        $schema = $this->tool->parametersSchema();
        $props  = array_keys($schema['properties']);

        $this->assertContains('query',    $props);
        $this->assertContains('type',     $props);
        $this->assertContains('location', $props);
        $this->assertContains('limit',    $props);
    }

    public function test_to_openai_function_wraps_name_and_description(): void
    {
        $fn = $this->tool->toOpenAiFunction();

        $this->assertSame('function', $fn['type']);
        $this->assertSame('search_listings', $fn['function']['name']);
        $this->assertNotEmpty($fn['function']['description']);
    }

    // ── execute: empty query guard ────────────────────────────────────────────

    public function test_execute_returns_err_for_empty_query(): void
    {
        $result = $this->tool->execute(['query' => ''], 1);

        $this->assertFalse($result['ok']);
        $this->assertSame('error', $result['card_type']);
        $this->assertNotEmpty($result['error']);
    }

    public function test_execute_returns_err_for_whitespace_only_query(): void
    {
        $result = $this->tool->execute(['query' => '   '], 1);

        $this->assertFalse($result['ok']);
    }

    // ── execute: no results ───────────────────────────────────────────────────

    public function test_execute_returns_ok_with_empty_results_when_no_match(): void
    {
        $result = $this->tool->execute(['query' => 'xyzzy_nonexistent_zqmrpf'], 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['results']);
        $this->assertSame('listing', $result['card_type']);
    }

    // ── execute: happy path ───────────────────────────────────────────────────

    public function test_execute_finds_listing_matching_title(): void
    {
        $keyword = 'gardening' . uniqid();
        $this->insertListing(['title' => "Help with {$keyword}"]);

        $result = $this->tool->execute(['query' => $keyword], 1);

        $this->assertTrue($result['ok']);
        $this->assertGreaterThanOrEqual(1, count($result['results']));
    }

    public function test_execute_finds_listing_matching_description(): void
    {
        $keyword = 'plumbing' . uniqid();
        $this->insertListing([
            'title'       => 'Generic offer',
            'description' => "I can help with {$keyword} repairs",
        ]);

        $result = $this->tool->execute(['query' => $keyword], 1);

        $this->assertTrue($result['ok']);
        $this->assertGreaterThanOrEqual(1, count($result['results']));
    }

    public function test_execute_result_has_expected_keys(): void
    {
        $keyword = 'cooking' . uniqid();
        $this->insertListing(['title' => "Cooking class {$keyword}"]);

        $result = $this->tool->execute(['query' => $keyword], 1);

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['results']);
        $first = $result['results'][0];

        foreach (['id', 'title', 'type', 'location', 'hours_estimate', 'excerpt', 'url'] as $key) {
            $this->assertArrayHasKey($key, $first, "Result missing key: {$key}");
        }
    }

    public function test_execute_url_contains_listing_id(): void
    {
        $keyword = 'cycling' . uniqid();
        $id = $this->insertListing(['title' => "Cycling lessons {$keyword}"]);

        $result = $this->tool->execute(['query' => $keyword], 1);

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['results']);
        $this->assertStringContainsString((string) $id, $result['results'][0]['url']);
    }

    // ── execute: type filter ──────────────────────────────────────────────────

    public function test_execute_filters_by_type_offer(): void
    {
        $keyword = 'tutoring' . uniqid();
        $this->insertListing(['title' => "{$keyword} offer", 'type' => 'offer']);
        $this->insertListing(['title' => "{$keyword} request", 'type' => 'request']);

        $result = $this->tool->execute(['query' => $keyword, 'type' => 'offer'], 1);

        $this->assertTrue($result['ok']);
        foreach ($result['results'] as $r) {
            $this->assertSame('offer', $r['type']);
        }
    }

    public function test_execute_filters_by_type_request(): void
    {
        $keyword = 'driving' . uniqid();
        $this->insertListing(['title' => "{$keyword} offer",   'type' => 'offer']);
        $this->insertListing(['title' => "{$keyword} request", 'type' => 'request']);

        $result = $this->tool->execute(['query' => $keyword, 'type' => 'request'], 1);

        $this->assertTrue($result['ok']);
        foreach ($result['results'] as $r) {
            $this->assertSame('request', $r['type']);
        }
    }

    public function test_execute_type_any_returns_both_types(): void
    {
        $keyword = 'baking' . uniqid();
        $this->insertListing(['title' => "{$keyword} offer",   'type' => 'offer']);
        $this->insertListing(['title' => "{$keyword} request", 'type' => 'request']);

        $result = $this->tool->execute(['query' => $keyword, 'type' => 'any'], 1);

        $this->assertTrue($result['ok']);
        $types = array_column($result['results'], 'type');
        $this->assertContains('offer',   $types);
        $this->assertContains('request', $types);
    }

    // ── execute: location filter ──────────────────────────────────────────────

    public function test_execute_location_filter_restricts_results(): void
    {
        $keyword = 'massage' . uniqid();
        $this->insertListing(['title' => "{$keyword} Cork",   'location' => 'Cork']);
        $this->insertListing(['title' => "{$keyword} Galway", 'location' => 'Galway']);

        $result = $this->tool->execute(['query' => $keyword, 'location' => 'Cork'], 1);

        $this->assertTrue($result['ok']);
        foreach ($result['results'] as $r) {
            $this->assertStringContainsStringIgnoringCase('Cork', (string) $r['location']);
        }
    }

    // ── execute: limit ────────────────────────────────────────────────────────

    public function test_execute_limit_caps_results(): void
    {
        $keyword = 'yoga' . uniqid();
        for ($i = 0; $i < 6; $i++) {
            $this->insertListing(['title' => "{$keyword} class {$i}"]);
        }

        $result = $this->tool->execute(['query' => $keyword, 'limit' => 3], 1);

        $this->assertTrue($result['ok']);
        $this->assertLessThanOrEqual(3, count($result['results']));
    }

    public function test_execute_limit_clamped_to_max_8(): void
    {
        $keyword = 'painting' . uniqid();
        for ($i = 0; $i < 10; $i++) {
            $this->insertListing(['title' => "{$keyword} session {$i}"]);
        }

        // Request 20, should be clamped to 8
        $result = $this->tool->execute(['query' => $keyword, 'limit' => 20], 1);

        $this->assertTrue($result['ok']);
        $this->assertLessThanOrEqual(8, count($result['results']));
    }

    // ── execute: moderation + status gating ──────────────────────────────────

    public function test_execute_excludes_inactive_listings(): void
    {
        $keyword = 'sewing' . uniqid();
        $this->insertListing(['title' => "{$keyword} inactive", 'status' => 'inactive']);

        $result = $this->tool->execute(['query' => $keyword], 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['results']);
    }

    public function test_execute_excludes_rejected_moderation_status(): void
    {
        $keyword = 'ironing' . uniqid();
        $this->insertListing([
            'title'             => "{$keyword} rejected",
            'status'            => 'active',
            'moderation_status' => 'rejected',
        ]);

        $result = $this->tool->execute(['query' => $keyword], 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['results']);
    }

    public function test_execute_includes_listings_with_approved_moderation_status(): void
    {
        $keyword = 'cleaning' . uniqid();
        $this->insertListing([
            'title'             => "{$keyword} approved",
            'status'            => 'active',
            'moderation_status' => 'approved',
        ]);

        $result = $this->tool->execute(['query' => $keyword], 1);

        $this->assertTrue($result['ok']);
        $this->assertGreaterThanOrEqual(1, count($result['results']));
    }

    // ── Tenant scoping ────────────────────────────────────────────────────────

    public function test_execute_does_not_return_listings_from_another_tenant(): void
    {
        $keyword = 'knitting' . uniqid();
        // Insert listing for a different tenant
        DB::table('listings')->insert([
            'tenant_id'      => 999,
            'user_id'        => $this->ownerUserId,
            'title'          => "{$keyword} other tenant",
            'description'    => 'Should not appear',
            'type'           => 'offer',
            'status'         => 'active',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);
        $result = $this->tool->execute(['query' => $keyword], 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['results']);
    }

    // ── Card type ─────────────────────────────────────────────────────────────

    public function test_execute_returns_listing_card_type(): void
    {
        $keyword = 'welding' . uniqid();
        $this->insertListing(['title' => $keyword]);

        $result = $this->tool->execute(['query' => $keyword], 1);

        $this->assertSame('listing', $result['card_type']);
    }
}
