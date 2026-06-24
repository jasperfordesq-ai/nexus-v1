<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Console;

use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for App\Console\Commands\ProcessSavedSearchAlerts
 *
 * Uses a unique tenant id (99731) to stay isolated from every other test suite.
 */
class ProcessSavedSearchAlertsTest extends TestCase
{
    use DatabaseTransactions;

    private int $tenantId = 99731;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // Seed isolated tenant
        DB::table('tenants')->updateOrInsert(
            ['id' => $this->tenantId],
            [
                'name'              => 'Test Tenant 99731',
                'slug'              => 'test-tenant-99731',
                'domain'            => null,
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        TenantContext::setById($this->tenantId);

        // Seed a user for this tenant
        $this->userId = (int) DB::table('users')->insertGetId([
            'name'       => 'Alert User 99731',
            'email'      => 'alert99731@example.com',
            'tenant_id'  => $this->tenantId,
            'role'       => 'member',
            'status'     => 'active',
            'created_at' => now(),
        ]);
    }

    // -----------------------------------------------------------------------
    // Helper: insert a saved search row
    // -----------------------------------------------------------------------

    private function insertSavedSearch(array $overrides = []): int
    {
        $defaults = [
            'tenant_id'       => $this->tenantId,
            'user_id'         => $this->userId,
            'name'            => 'My Test Search',
            'query_params'    => json_encode(['q' => 'gardening']),
            'notify_on_new'   => 1,
            'last_run_at'     => null,
            'last_notified_at'=> null,
            'created_at'      => now()->subHour(),
            'updated_at'      => now()->subHour(),
        ];

        return (int) DB::table('saved_searches')->insertGetId(array_merge($defaults, $overrides));
    }

    // -----------------------------------------------------------------------
    // Helper: insert a listing for this tenant
    // -----------------------------------------------------------------------

    private function insertListing(array $overrides = []): int
    {
        $defaults = [
            'tenant_id'   => $this->tenantId,
            'user_id'     => $this->userId,
            'title'       => 'Test Listing for Gardening',
            'description' => 'gardening help offer',
            'type'        => 'offer',
            'status'      => 'active',
            'created_at'  => now(),
            'updated_at'  => now(),
        ];

        return (int) DB::table('listings')->insertGetId(array_merge($defaults, $overrides));
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    /**
     * Command exits cleanly with no saved searches to process.
     */
    public function test_exits_success_with_no_saved_searches(): void
    {
        // No saved_searches rows for this tenant
        $this->artisan('listings:process-search-alerts', ['--tenant' => $this->tenantId])
            ->assertExitCode(0);
    }

    /**
     * When notify_on_new is false the search is skipped — no notification row.
     */
    public function test_skips_saved_search_when_notify_on_new_is_false(): void
    {
        $this->insertSavedSearch(['notify_on_new' => 0]);
        $this->insertListing();

        $before = DB::table('notifications')->where('tenant_id', $this->tenantId)->count();

        $this->artisan('listings:process-search-alerts', ['--tenant' => $this->tenantId])
            ->assertExitCode(0);

        $after = DB::table('notifications')->where('tenant_id', $this->tenantId)->count();
        $this->assertSame($before, $after, 'No notification should be created when notify_on_new is false');
    }

    /**
     * A matching new listing triggers a notification and stamps last_notified_at.
     */
    public function test_sends_alert_when_matching_new_listing_exists(): void
    {
        // Saved search created 2 hours ago; listing created NOW (after the cutoff)
        $searchId = $this->insertSavedSearch([
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);

        $this->insertListing(['created_at' => now()]);

        $beforeNotifs = DB::table('notifications')->where('user_id', $this->userId)->count();

        $this->artisan('listings:process-search-alerts', ['--tenant' => $this->tenantId])
            ->assertExitCode(0);

        // A notification row should have been created
        $afterNotifs = DB::table('notifications')->where('user_id', $this->userId)->count();
        $this->assertGreaterThan($beforeNotifs, $afterNotifs, 'A notification should be created for matching listing');

        // last_notified_at should be stamped
        $search = DB::table('saved_searches')->where('id', $searchId)->first();
        $this->assertNotNull($search->last_notified_at, 'last_notified_at must be stamped after alert');

        // last_result_count should be ≥ 1
        $this->assertGreaterThanOrEqual(1, (int) $search->last_result_count, 'last_result_count must reflect the match count');
    }

    /**
     * When the listing is older than the cutoff, no alert is sent.
     */
    public function test_no_alert_when_no_new_matching_listings(): void
    {
        // Saved search and listing are both old — listing predates the cutoff
        $this->insertSavedSearch([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        $this->insertListing([
            'created_at' => now()->subDays(3), // older than the saved search
            'updated_at' => now()->subDays(3),
        ]);

        $beforeNotifs = DB::table('notifications')->where('user_id', $this->userId)->count();

        $this->artisan('listings:process-search-alerts', ['--tenant' => $this->tenantId])
            ->assertExitCode(0);

        $afterNotifs = DB::table('notifications')->where('user_id', $this->userId)->count();
        $this->assertSame($beforeNotifs, $afterNotifs, 'No notification when listing is older than the search cutoff');
    }

    /**
     * When the search has a keyword filter and the listing does NOT match,
     * no alert is sent.
     */
    public function test_keyword_filter_excludes_non_matching_listing(): void
    {
        $this->insertSavedSearch([
            'query_params' => json_encode(['q' => 'plumbing']),
            'created_at'   => now()->subHour(),
            'updated_at'   => now()->subHour(),
        ]);

        // Listing has no mention of "plumbing"
        $this->insertListing([
            'title'       => 'Dog walking service',
            'description' => 'Walk your dog daily',
            'created_at'  => now(),
        ]);

        $beforeNotifs = DB::table('notifications')->where('user_id', $this->userId)->count();

        $this->artisan('listings:process-search-alerts', ['--tenant' => $this->tenantId])
            ->assertExitCode(0);

        $afterNotifs = DB::table('notifications')->where('user_id', $this->userId)->count();
        $this->assertSame($beforeNotifs, $afterNotifs, 'Keyword filter should exclude non-matching listings');
    }

    /**
     * Type filter (offer/request) is respected — request search does not match offer listing.
     */
    public function test_type_filter_excludes_wrong_listing_type(): void
    {
        $this->insertSavedSearch([
            'query_params' => json_encode(['type' => 'request']),
            'created_at'   => now()->subHour(),
            'updated_at'   => now()->subHour(),
        ]);

        // Insert an 'offer' listing — should NOT match a 'request' search
        $this->insertListing(['type' => 'offer', 'created_at' => now()]);

        $beforeNotifs = DB::table('notifications')->where('user_id', $this->userId)->count();

        $this->artisan('listings:process-search-alerts', ['--tenant' => $this->tenantId])
            ->assertExitCode(0);

        $afterNotifs = DB::table('notifications')->where('user_id', $this->userId)->count();
        $this->assertSame($beforeNotifs, $afterNotifs, 'Type filter: offer listing should not match request search');
    }

    /**
     * last_notified_at is used as the cutoff when set.
     * A listing that exists before last_notified_at should produce no alert.
     */
    public function test_last_notified_at_is_used_as_cutoff(): void
    {
        // last_notified_at was set 30 minutes ago
        $this->insertSavedSearch([
            'created_at'       => now()->subDays(1),
            'updated_at'       => now()->subDays(1),
            'last_notified_at' => now()->subMinutes(30)->format('Y-m-d H:i:s'),
        ]);

        // Listing was created 1 hour ago — before last_notified_at (30 min ago)
        $this->insertListing(['created_at' => now()->subHour()]);

        $beforeNotifs = DB::table('notifications')->where('user_id', $this->userId)->count();

        $this->artisan('listings:process-search-alerts', ['--tenant' => $this->tenantId])
            ->assertExitCode(0);

        $afterNotifs = DB::table('notifications')->where('user_id', $this->userId)->count();
        $this->assertSame($beforeNotifs, $afterNotifs, 'last_notified_at cutoff should exclude already-notified listings');
    }

    /**
     * Multiple new matching listings: alert is sent with the count.
     */
    public function test_alert_sent_for_multiple_matching_listings(): void
    {
        $searchId = $this->insertSavedSearch([
            'query_params' => json_encode([]),   // no keyword filter — match all
            'created_at'   => now()->subHour(),
            'updated_at'   => now()->subHour(),
        ]);

        $this->insertListing(['created_at' => now()]);
        $this->insertListing(['title' => 'Another new listing', 'created_at' => now()]);

        $beforeNotifs = DB::table('notifications')->where('user_id', $this->userId)->count();

        $this->artisan('listings:process-search-alerts', ['--tenant' => $this->tenantId])
            ->assertExitCode(0);

        $afterNotifs = DB::table('notifications')->where('user_id', $this->userId)->count();
        $this->assertGreaterThan($beforeNotifs, $afterNotifs, 'Should send alert for multiple matching listings');

        $search = DB::table('saved_searches')->where('id', $searchId)->first();
        $this->assertGreaterThanOrEqual(2, (int) $search->last_result_count, 'last_result_count should reflect all matching listings');
    }

    /**
     * Command processes only the specified tenant when --tenant option is given.
     * Other tenants' searches are untouched.
     */
    public function test_tenant_option_limits_processing_to_specified_tenant(): void
    {
        // Create a second tenant with its own saved search
        $otherTenantId = 99731 + 1000;
        DB::table('tenants')->updateOrInsert(
            ['id' => $otherTenantId],
            [
                'name'              => 'Other Tenant 99731',
                'slug'              => 'other-tenant-99731',
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        $otherUserId = (int) DB::table('users')->insertGetId([
            'name'       => 'Other User 99731',
            'email'      => 'other99731@example.com',
            'tenant_id'  => $otherTenantId,
            'role'       => 'member',
            'status'     => 'active',
            'created_at' => now(),
        ]);

        $otherSearchId = (int) DB::table('saved_searches')->insertGetId([
            'tenant_id'     => $otherTenantId,
            'user_id'       => $otherUserId,
            'name'          => 'Other Search',
            'query_params'  => json_encode([]),
            'notify_on_new' => 1,
            'created_at'    => now()->subHour(),
            'updated_at'    => now()->subHour(),
        ]);

        // Matching listing for the other tenant
        DB::table('listings')->insert([
            'tenant_id'  => $otherTenantId,
            'user_id'    => $otherUserId,
            'title'      => 'Other Tenant Listing',
            'type'       => 'offer',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $beforeNotifs = DB::table('notifications')->where('user_id', $otherUserId)->count();

        // Run with --tenant pointing at OUR tenant (99731), not the other one
        $this->artisan('listings:process-search-alerts', ['--tenant' => $this->tenantId])
            ->assertExitCode(0);

        $afterNotifs = DB::table('notifications')->where('user_id', $otherUserId)->count();
        $this->assertSame($beforeNotifs, $afterNotifs, 'Other tenant searches should not be processed');

        // other tenant's saved search should not have last_notified_at stamped
        $otherSearch = DB::table('saved_searches')->where('id', $otherSearchId)->first();
        $this->assertNull($otherSearch->last_notified_at, 'Other tenant last_notified_at must remain null');
    }

    /**
     * When the saved search has only created_at as a cutoff (last_run_at and
     * last_notified_at are null) the command uses created_at as the cutoff and
     * finds listings created after that point.
     */
    public function test_created_at_used_as_cutoff_when_other_timestamps_null(): void
    {
        // Search created 2 hours ago; last_run_at and last_notified_at are null
        $searchId = (int) DB::table('saved_searches')->insertGetId([
            'tenant_id'        => $this->tenantId,
            'user_id'          => $this->userId,
            'name'             => 'Created-at cutoff search',
            'query_params'     => json_encode([]),
            'notify_on_new'    => 1,
            'last_run_at'      => null,
            'last_notified_at' => null,
            'created_at'       => now()->subHours(2)->format('Y-m-d H:i:s'),
            'updated_at'       => now()->subHours(2)->format('Y-m-d H:i:s'),
        ]);

        // Listing created AFTER the search's created_at → should match
        $this->insertListing(['created_at' => now()->subMinutes(30)->format('Y-m-d H:i:s')]);

        $beforeNotifs = DB::table('notifications')->where('user_id', $this->userId)->count();

        $this->artisan('listings:process-search-alerts', ['--tenant' => $this->tenantId])
            ->assertExitCode(0);

        $afterNotifs = DB::table('notifications')->where('user_id', $this->userId)->count();
        $this->assertGreaterThan($beforeNotifs, $afterNotifs, 'created_at should be used as the cutoff when other timestamps are null');

        $search = DB::table('saved_searches')->where('id', $searchId)->first();
        $this->assertNotNull($search->last_notified_at, 'last_notified_at should be stamped after alert');
    }
}
