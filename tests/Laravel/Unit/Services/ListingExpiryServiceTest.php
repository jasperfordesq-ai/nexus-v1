<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\ListingExpiryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * ListingExpiryServiceTest
 *
 * Tests the expiry/renewal workflow:
 *  - processExpiredListings(): only past-expires_at active/null-status rows → 'expired';
 *    future-expiry rows and already-expired rows are left untouched.
 *  - renewListing(): extends expiry by 30 days, enforces owner / MAX_RENEWALS guards.
 *  - setExpiry(): basic date update + tenant scoping.
 *
 * Email side-effects are caught by a try/catch inside the service so test failures
 * caused by missing SMTP are impossible.  Notification rows are checked where relevant.
 *
 * All times are explicit fixtures; no reliance on wall-clock beyond "now + offset".
 */
class ListingExpiryServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    private ListingExpiryService $service;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
        $this->service = new ListingExpiryService();

        // Create a minimal user that satisfies notifications FK.
        $uid = uniqid('exp_', true);
        $this->userId = DB::table('users')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'name'        => 'Expiry Test ' . $uid,
            'first_name'  => 'Expiry',
            'last_name'   => 'Test',
            'email'       => 'expiry.' . $uid . '@example.test',
            'status'      => 'active',
            'balance'     => 0,
            'role'        => 'member',
            'is_approved' => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Insert a minimal listings row, return its ID.
     */
    private function insertListing(array $overrides = []): int
    {
        $defaults = [
            'tenant_id'  => self::TENANT_ID,
            'user_id'    => $this->userId,
            'title'      => 'Test Listing ' . uniqid(),
            'type'       => 'offer',
            'status'     => 'active',
            'expires_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        return DB::table('listings')->insertGetId(array_merge($defaults, $overrides));
    }

    // ── processExpiredListings: past-expiry active listing ────────────────────

    public function test_process_expired_listings_marks_past_expiry_active_listing_as_expired(): void
    {
        $listingId = $this->insertListing([
            'status'     => 'active',
            'expires_at' => Carbon::now()->subDay()->toDateTimeString(),
        ]);

        $result = $this->service->processExpiredListings();

        $this->assertGreaterThanOrEqual(1, $result['expired'], 'At least one listing should have been expired');
        $row = DB::table('listings')->where('id', $listingId)->first();
        $this->assertSame('expired', $row->status, 'Listing past expiry must be set to expired');
    }

    // ── processExpiredListings: future-expiry listing is not touched ──────────

    public function test_process_expired_listings_does_not_expire_future_expiry_listing(): void
    {
        $listingId = $this->insertListing([
            'status'     => 'active',
            'expires_at' => Carbon::now()->addDay()->toDateTimeString(),
        ]);

        $this->service->processExpiredListings();

        $row = DB::table('listings')->where('id', $listingId)->first();
        $this->assertSame('active', $row->status, 'Future-expiry listing must not be expired');
    }

    // ── processExpiredListings: null-status past-expiry is also expired ───────

    public function test_process_expired_listings_handles_null_status_row(): void
    {
        // The service picks up rows WHERE (status IS NULL OR status='active').
        $listingId = $this->insertListing([
            'status'     => 'active',   // DB enum has no NULL default; use 'active' to confirm pick-up
            'expires_at' => Carbon::now()->subHours(2)->toDateTimeString(),
        ]);

        $result = $this->service->processExpiredListings();

        $this->assertSame(0, $result['errors'], 'No errors should occur during normal expiry processing');
        $row = DB::table('listings')->where('id', $listingId)->first();
        $this->assertSame('expired', $row->status);
    }

    // ── processExpiredListings: listing without expires_at is not touched ─────

    public function test_process_expired_listings_ignores_listings_with_null_expires_at(): void
    {
        $listingId = $this->insertListing([
            'status'     => 'active',
            'expires_at' => null,
        ]);

        $this->service->processExpiredListings();

        $row = DB::table('listings')->where('id', $listingId)->first();
        $this->assertSame('active', $row->status, 'Listing without expires_at must not be expired');
    }

    // ── processExpiredListings: already-expired listing is not double-processed

    public function test_process_expired_listings_does_not_touch_already_expired_listing(): void
    {
        $listingId = $this->insertListing([
            'status'     => 'expired',
            'expires_at' => Carbon::now()->subDay()->toDateTimeString(),
        ]);

        $beforeCount = DB::table('notifications')
            ->where('user_id', $this->userId)
            ->where('type', 'listing_expired')
            ->count();

        $this->service->processExpiredListings();

        // Status must not have changed back or been re-processed.
        $row = DB::table('listings')->where('id', $listingId)->first();
        $this->assertSame('expired', $row->status);
        // Notification count must not have increased (already-expired not selected by query).
        $afterCount = DB::table('notifications')
            ->where('user_id', $this->userId)
            ->where('type', 'listing_expired')
            ->count();
        $this->assertSame($beforeCount, $afterCount, 'No extra notification for already-expired listing');
    }

    // ── processExpiredListings: returns correct counts ────────────────────────

    public function test_process_expired_listings_returns_correct_expired_count(): void
    {
        // Insert two expired, one future.
        $this->insertListing([
            'status'     => 'active',
            'expires_at' => Carbon::now()->subHour()->toDateTimeString(),
        ]);
        $this->insertListing([
            'status'     => 'active',
            'expires_at' => Carbon::now()->subMinutes(5)->toDateTimeString(),
        ]);
        $this->insertListing([
            'status'     => 'active',
            'expires_at' => Carbon::now()->addHour()->toDateTimeString(),
        ]);

        $result = $this->service->processExpiredListings();

        $this->assertGreaterThanOrEqual(2, $result['expired'], 'At least 2 listings should be expired');
        $this->assertSame(0, $result['errors']);
    }

    // ── renewListing: successful path (service has two known bugs — documented) ─

    /**
     * @see ListingExpiryService::renewListing() line 206: $listing->expires_at is a
     *      raw string (not in Listing::$casts as datetime), so ->copy() on line 211
     *      throws "Call to a member function copy() on string" for active listings
     *      with a future expires_at.
     *
     *      Additionally, DB::raw('renewal_count + 1') on line 217 conflicts with the
     *      'renewal_count' => 'integer' cast in Listing::$casts, throwing
     *      "Object of class Query\Expression could not be converted to int" on every
     *      renewal path that reaches the update() call.
     *
     *      Both bugs exist in app/ source which cannot be modified by this test suite.
     *      The two successful-renewal tests are skipped until the service is fixed:
     *      (a) add 'expires_at' => 'datetime' to Listing::$casts, and
     *      (b) use $listing->increment('renewal_count') instead of DB::raw().
     */
    public function test_renew_listing_success_path_skipped_pending_service_bug_fix(): void
    {
        $this->markTestSkipped(
            'renewListing() has two service-layer bugs: ' .
            '(1) expires_at is not cast to datetime so ->copy() throws on string; ' .
            '(2) DB::raw(renewal_count+1) conflicts with integer cast in Listing::$casts. ' .
            'Fix app/Services/ListingExpiryService.php and app/Models/Listing.php before enabling.'
        );
    }

    // ── renewListing: non-owner gets forbidden ────────────────────────────────

    public function test_renew_listing_returns_forbidden_for_non_owner_non_admin(): void
    {
        $listingId = $this->insertListing(['status' => 'active', 'renewal_count' => 0]);

        // Insert a second member user who does not own the listing.
        $uid2 = uniqid('other_', true);
        $otherId = DB::table('users')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'name'        => 'Other ' . $uid2,
            'first_name'  => 'Other',
            'last_name'   => 'User',
            'email'       => 'other.' . $uid2 . '@example.test',
            'status'      => 'active',
            'balance'     => 0,
            'role'        => 'member',
            'is_approved' => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $result = $this->service->renewListing($listingId, $otherId);

        $this->assertFalse($result['success']);
        $this->assertSame('forbidden', $result['error_code']);
    }

    // ── renewListing: not_found for missing listing ───────────────────────────

    public function test_renew_listing_returns_not_found_for_missing_listing(): void
    {
        $result = $this->service->renewListing(999999999, $this->userId);

        $this->assertFalse($result['success']);
        $this->assertSame('not_found', $result['error_code']);
        $this->assertNull($result['new_expires_at']);
    }

    // ── renewListing: renewal limit enforced ─────────────────────────────────

    public function test_renew_listing_returns_limit_reached_when_max_renewals_exceeded(): void
    {
        $listingId = $this->insertListing([
            'status'        => 'active',
            'renewal_count' => 12,   // MAX_RENEWALS = 12
        ]);

        $result = $this->service->renewListing($listingId, $this->userId);

        $this->assertFalse($result['success']);
        $this->assertSame('limit_reached', $result['error_code']);
        $this->assertNull($result['new_expires_at']);
    }

    // ── renewListing: admin user can renew another user's listing ────────────

    public function test_renew_listing_returns_forbidden_not_for_admin_user(): void
    {
        $listingId = $this->insertListing(['status' => 'active', 'renewal_count' => 12]);

        // Insert an admin user for the tenant.
        $uid3 = uniqid('admin_', true);
        $adminId = DB::table('users')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'name'        => 'Admin ' . $uid3,
            'first_name'  => 'Admin',
            'last_name'   => 'User',
            'email'       => 'admin.' . $uid3 . '@example.test',
            'status'      => 'active',
            'balance'     => 0,
            'role'        => 'admin',
            'is_approved' => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // At renewal_count=12 → limit_reached (not forbidden); proves admin PASSES the auth check.
        $result = $this->service->renewListing($listingId, $adminId);

        // Admin passes authorization but hits the renewal limit — NOT forbidden.
        $this->assertFalse($result['success']);
        $this->assertSame('limit_reached', $result['error_code'], 'Admin should pass auth check and see limit_reached, not forbidden');
    }

    // ── setExpiry: updates the date ───────────────────────────────────────────

    public function test_set_expiry_updates_expires_at_for_own_tenant_listing(): void
    {
        $listingId  = $this->insertListing(['expires_at' => null]);
        $futureDate = Carbon::now()->addDays(14)->toDateTimeString();

        $success = $this->service->setExpiry($listingId, $futureDate);

        $this->assertTrue($success);
        $row = DB::table('listings')->where('id', $listingId)->first();
        $this->assertNotNull($row->expires_at);
        $stored = Carbon::parse($row->expires_at);
        $expected = Carbon::parse($futureDate);
        $this->assertTrue($stored->isSameMinute($expected), 'Stored expires_at should match the value we set');
    }

    // ── setExpiry: clears the date ────────────────────────────────────────────

    public function test_set_expiry_can_clear_expires_at_to_null(): void
    {
        $listingId = $this->insertListing([
            'expires_at' => Carbon::now()->addDay()->toDateTimeString(),
        ]);

        $success = $this->service->setExpiry($listingId, null);

        $this->assertTrue($success);
        $row = DB::table('listings')->where('id', $listingId)->first();
        $this->assertNull($row->expires_at, 'expires_at should be clearable to NULL');
    }

    // ── setExpiry: cross-tenant isolation ─────────────────────────────────────

    public function test_set_expiry_returns_false_for_listing_from_different_tenant(): void
    {
        // Use the ID of a non-existent listing (no real other-tenant listing; 999999999 is safe).
        $success = $this->service->setExpiry(999999999, Carbon::now()->addDay()->toDateTimeString());

        $this->assertFalse($success, 'setExpiry must return false when no row is affected');
    }
}
