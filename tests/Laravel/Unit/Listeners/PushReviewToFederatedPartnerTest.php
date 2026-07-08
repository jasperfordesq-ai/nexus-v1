<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\ReviewCreated;
use App\Listeners\PushReviewToFederatedPartner;
use App\Models\Review;
use App\Services\FederationAuditService;
use App\Services\FederationExternalApiClient;
use App\Services\FederationFeatureService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * PushReviewToFederatedPartnerTest
 *
 * Tenant 99671 is used exclusively by this file to avoid lock contention with
 * other test files. All DB writes roll back via DatabaseTransactions.
 *
 * The FederationExternalApiClient uses Http::fake() for all outbound HTTP calls.
 * We assert on the URL suffix, HMAC signature header, and payload shape.
 */
class PushReviewToFederatedPartnerTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99671;

    /** HTTPS public IP — passes OutboundUrlGuard SSRF check. */
    private const PARTNER_BASE_URL = 'https://93.184.216.34';

    /** Plaintext signing secret — encrypted with Crypt before DB insert (HMAC auth). */
    private const TEST_SIGNING_SECRET = 'review-test-signing-secret-abcdefgh';

    private FederationFeatureService $featureService;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // Insert tenant row for this test's unique tenant id.
        DB::table('tenants')->insert([
            'id'         => self::TENANT_ID,
            'name'       => 'Test Review Federation Tenant',
            'slug'       => 'test-review-fed-' . self::TENANT_ID,
            'features'   => json_encode(['federation' => true]),
            'created_at' => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);

        // Enable system-level federation (defaults to disabled in DB).
        DB::table('federation_system_control')->updateOrInsert(
            ['id' => 1],
            [
                'federation_enabled'                 => 1,
                'whitelist_mode_enabled'             => 0,
                'emergency_lockdown_active'          => 0,
                'cross_tenant_profiles_enabled'      => 1,
                'cross_tenant_messaging_enabled'     => 1,
                'cross_tenant_transactions_enabled'  => 1,
                'cross_tenant_listings_enabled'      => 1,
                'cross_tenant_events_enabled'        => 1,
                'cross_tenant_groups_enabled'        => 1,
                'max_federation_level'               => 4,
                'created_at'                         => now(),
            ]
        );

        // Enable tenant-level federation feature.
        DB::table('federation_tenant_features')->updateOrInsert(
            ['tenant_id' => self::TENANT_ID, 'feature_key' => FederationFeatureService::TENANT_FEDERATION_ENABLED],
            ['is_enabled' => 1, 'updated_at' => now()]
        );

        // Fresh service instance so in-process caches start clean.
        $this->featureService = new FederationFeatureService(new FederationAuditService());

        // Clear static adapter cache between tests.
        FederationExternalApiClient::clearAdapterCache();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper: insert a federation external partner row.
    // ─────────────────────────────────────────────────────────────────────────

    private function insertPartner(
        ?string $signingSecret = null,
        string $status = 'active',
        ?string $baseUrl = null
    ): int {
        // Use a unique base_url per call to avoid UK collision.
        static $counter = 0;
        $counter++;
        $url = $baseUrl ?? self::PARTNER_BASE_URL;
        // Append path-fragment to make the URL unique while keeping the host.
        $uniqueUrl = rtrim($url, '/') . '/p' . $counter . self::TENANT_ID;

        // Use HMAC auth with an encrypted signing secret so that
        // Crypt::decryptString() succeeds during buildAuthHeaders().
        $secret = $signingSecret ?? self::TEST_SIGNING_SECRET;
        $encryptedSecret = Crypt::encryptString($secret);

        return (int) DB::table('federation_external_partners')->insertGetId([
            'tenant_id'      => self::TENANT_ID,
            'name'           => 'Review Test Partner ' . $counter,
            'base_url'       => $uniqueUrl,
            'api_path'       => '/api/v1/federation',
            'auth_method'    => 'hmac',
            'signing_secret' => $encryptedSecret,
            'protocol_type'  => 'nexus',
            'status'         => $status,
            'created_at'     => now(),
        ]);
    }

    private function insertUser(): int
    {
        return (int) DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Rev User ' . uniqid(),
            'email'      => 'revuser' . uniqid() . '@test.local',
            'password'   => 'hashed',
            'created_at' => now(),
        ]);
    }

    private function insertFederatedIdentity(int $localUserId, int $partnerId, string $externalId = 'ext-user-1'): int
    {
        return (int) DB::table('federated_identities')->insertGetId([
            'tenant_id'        => self::TENANT_ID,
            'local_user_id'    => $localUserId,
            'partner_id'       => $partnerId,
            'external_user_id' => $externalId,
            'created_at'       => now(),
        ]);
    }

    private function makeReview(int $reviewerId, int $receiverId): Review
    {
        $id = (int) DB::table('reviews')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'reviewer_id' => $reviewerId,
            'receiver_id' => $receiverId,
            'rating'      => 5,
            'comment'     => 'Great service',
            'status'      => 'approved',
            'created_at'  => now(),
        ]);

        return Review::find($id);
    }

    private function makeListener(): PushReviewToFederatedPartner
    {
        return new PushReviewToFederatedPartner($this->featureService);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_implements_should_queue(): void
    {
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $this->makeListener());
    }

    public function test_nothing_sent_when_receiver_has_no_federated_identity(): void
    {
        Http::fake();
        $reviewerId = $this->insertUser();
        $receiverId = $this->insertUser();
        // No federated_identities row for receiver.
        $review = $this->makeReview($reviewerId, $receiverId);
        $event  = new ReviewCreated($review, self::TENANT_ID);

        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    public function test_posts_review_to_partner_when_receiver_is_federated(): void
    {
        $partnerId  = $this->insertPartner();
        $reviewerId = $this->insertUser();
        $receiverId = $this->insertUser();
        $this->insertFederatedIdentity($receiverId, $partnerId, 'ext-rcv-1');

        $review = $this->makeReview($reviewerId, $receiverId);
        $event  = new ReviewCreated($review, self::TENANT_ID);

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $this->makeListener()->handle($event);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/reviews')
                && $request->method() === 'POST';
        });
    }

    public function test_payload_contains_required_fields(): void
    {
        $partnerId  = $this->insertPartner();
        $reviewerId = $this->insertUser();
        $receiverId = $this->insertUser();
        $this->insertFederatedIdentity($receiverId, $partnerId, 'ext-rcv-payload');

        $review = $this->makeReview($reviewerId, $receiverId);
        $event  = new ReviewCreated($review, self::TENANT_ID);

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $this->makeListener()->handle($event);

        Http::assertSent(function ($request) use ($review, $receiverId) {
            $body = $request->data();
            return isset($body['rating'])
                && (int) $body['rating'] === 5
                && isset($body['receiver_external_id'])
                && $body['receiver_external_id'] === 'ext-rcv-payload'
                && isset($body['reviewer_id'])
                && (int) $body['reviewer_id'] === $review->reviewer_id
                && isset($body['reviewer_tenant'])
                && (int) $body['reviewer_tenant'] === self::TENANT_ID;
        });
    }

    public function test_sends_hmac_signature_header(): void
    {
        $secret    = 'unique-review-signing-secret-xyz';
        $partnerId = $this->insertPartner($secret);
        $reviewerId = $this->insertUser();
        $receiverId = $this->insertUser();
        $this->insertFederatedIdentity($receiverId, $partnerId, 'ext-rcv-auth');

        $review = $this->makeReview($reviewerId, $receiverId);
        $event  = new ReviewCreated($review, self::TENANT_ID);

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $this->makeListener()->handle($event);

        Http::assertSent(function ($request) {
            // HMAC auth sends X-Federation-Signature, not Authorization Bearer.
            return $request->hasHeader('X-Federation-Signature')
                && $request->hasHeader('X-Federation-Timestamp')
                && $request->hasHeader('X-Federation-Nonce');
        });
    }

    public function test_pushes_to_all_partners_when_receiver_has_multiple_federated_identities(): void
    {
        $partner1Id = $this->insertPartner('signing-secret-review-multi-a');
        $partner2Id = $this->insertPartner('signing-secret-review-multi-b');
        $reviewerId = $this->insertUser();
        $receiverId = $this->insertUser();
        $this->insertFederatedIdentity($receiverId, $partner1Id, 'ext-rcv-multi1');
        $this->insertFederatedIdentity($receiverId, $partner2Id, 'ext-rcv-multi2');

        $review = $this->makeReview($reviewerId, $receiverId);
        $event  = new ReviewCreated($review, self::TENANT_ID);

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $this->makeListener()->handle($event);

        Http::assertSentCount(2);
    }

    public function test_skips_when_tenant_id_is_zero(): void
    {
        Http::fake();
        $reviewerId = $this->insertUser();
        $receiverId = $this->insertUser();
        $review = $this->makeReview($reviewerId, $receiverId);
        $event  = new ReviewCreated($review, 0);  // invalid tenant

        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    public function test_skips_when_federation_feature_disabled_for_tenant(): void
    {
        // Disable the tenant-level federation feature.
        DB::table('federation_tenant_features')->updateOrInsert(
            ['tenant_id' => self::TENANT_ID, 'feature_key' => FederationFeatureService::TENANT_FEDERATION_ENABLED],
            ['is_enabled' => 0, 'updated_at' => now()]
        );

        // Override the tenant's features JSON too so TenantContext::hasFeature fails.
        DB::table('tenants')->where('id', self::TENANT_ID)->update([
            'features' => json_encode(['federation' => false]),
        ]);
        TenantContext::setById(self::TENANT_ID); // reload

        Http::fake();
        $partnerId  = $this->insertPartner();
        $reviewerId = $this->insertUser();
        $receiverId = $this->insertUser();
        $this->insertFederatedIdentity($receiverId, $partnerId, 'ext-rcv-disabled');
        $review = $this->makeReview($reviewerId, $receiverId);
        $event  = new ReviewCreated($review, self::TENANT_ID);

        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    public function test_throws_and_does_not_silently_swallow_partner_5xx(): void
    {
        $partnerId  = $this->insertPartner();
        $reviewerId = $this->insertUser();
        $receiverId = $this->insertUser();
        $this->insertFederatedIdentity($receiverId, $partnerId, 'ext-rcv-5xx');

        $review = $this->makeReview($reviewerId, $receiverId);
        $event  = new ReviewCreated($review, self::TENANT_ID);

        Http::fake(['*' => Http::response(['error' => 'server error'], 503)]);

        $this->expectException(\RuntimeException::class);

        $this->makeListener()->handle($event);
    }

    public function test_skips_partner_with_4xx_without_throwing(): void
    {
        // A 4xx (non-retryable) from the partner should NOT throw — the review
        // is likely malformed for that partner; retrying won't help.
        $partnerId  = $this->insertPartner();
        $reviewerId = $this->insertUser();
        $receiverId = $this->insertUser();
        $this->insertFederatedIdentity($receiverId, $partnerId, 'ext-rcv-4xx');

        $review = $this->makeReview($reviewerId, $receiverId);
        $event  = new ReviewCreated($review, self::TENANT_ID);

        Http::fake(['*' => Http::response(['error' => 'bad request'], 400)]);

        // Should not throw (4xx treated as terminal/non-retryable partner rejection).
        $this->makeListener()->handle($event);

        Http::assertSentCount(1);
    }

    public function test_skips_when_receiver_id_is_zero(): void
    {
        Http::fake();

        $reviewerId = $this->insertUser();
        $receiverId = $this->insertUser();
        $review = $this->makeReview($reviewerId, $receiverId);

        // Force receiver_id to 0 to exercise the early-return guard.
        $review->receiver_id = 0;

        $event = new ReviewCreated($review, self::TENANT_ID);
        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    public function test_does_not_push_review_to_federated_identity_belonging_to_another_tenant(): void
    {
        // Regression (audit L1): FederatedIdentity is NOT tenant-auto-scoped, so
        // the listener MUST filter tenant_id. A same-local_user_id identity row
        // scoped to a DIFFERENT tenant must never receive this tenant's review.
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $partnerId  = $this->insertPartner();
        $reviewerId = $this->insertUser();
        $receiverId = $this->insertUser();

        // Identity row exists for this local_user_id but under a FOREIGN tenant.
        DB::table('federated_identities')->insert([
            'tenant_id'        => self::TENANT_ID + 1,
            'local_user_id'    => $receiverId,
            'partner_id'       => $partnerId,
            'external_user_id' => 'ext-foreign-tenant',
            'created_at'       => now(),
        ]);

        $review = $this->makeReview($reviewerId, $receiverId);
        $event  = new ReviewCreated($review, self::TENANT_ID);

        $this->makeListener()->handle($event);

        Http::assertNothingSent();
    }

    public function test_queue_property_is_federation(): void
    {
        $this->assertSame('federation', $this->makeListener()->queue);
    }

    public function test_tries_is_three(): void
    {
        $this->assertSame(3, $this->makeListener()->tries);
    }
}
