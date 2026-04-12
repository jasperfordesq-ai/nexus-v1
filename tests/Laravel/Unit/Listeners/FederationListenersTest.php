<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\ListingCreated;
use App\Events\ReviewCreated;
use App\Listeners\PushListingToFederatedPartners;
use App\Listeners\PushMessageToFederatedPartner;
use App\Listeners\PushReviewToFederatedPartner;
use App\Listeners\PushTransactionToFederatedPartner;
use App\Models\Listing;
use App\Models\Review;
use App\Models\User;
use App\Services\FederationExternalApiClient;
use App\Services\FederationExternalPartnerService;
use App\Services\FederationFeatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Exercises the three federation push listeners. TenantContext is NOT
 * alias-mocked (it is loaded too early to alias-mock inside phpunit) — we
 * instead flip the real tenants.features JSON column to drive
 * TenantContext::hasFeature() outcomes.
 */
class FederationListenersTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function setFederationFeature(int $tenantId, bool $enabled): void
    {
        $row = DB::table('tenants')->where('id', $tenantId)->first();
        if (!$row) {
            $this->markTestSkipped("Tenant {$tenantId} does not exist.");
        }
        $features = [];
        if (!empty($row->features)) {
            $decoded = is_string($row->features) ? json_decode($row->features, true) : $row->features;
            if (is_array($decoded)) {
                $features = $decoded;
            }
        }
        $features['federation'] = $enabled;
        DB::table('tenants')->where('id', $tenantId)->update(['features' => json_encode($features)]);
        // Force TenantContext to re-read the features column
        TenantContext::setById($tenantId);
    }

    public function test_all_federation_listeners_implement_should_queue(): void
    {
        $this->assertContains(
            ShouldQueue::class,
            class_implements(PushListingToFederatedPartners::class) ?: []
        );
        $this->assertContains(
            ShouldQueue::class,
            class_implements(PushMessageToFederatedPartner::class) ?: []
        );
        $this->assertContains(
            ShouldQueue::class,
            class_implements(PushTransactionToFederatedPartner::class) ?: []
        );
        $this->assertContains(
            ShouldQueue::class,
            class_implements(PushReviewToFederatedPartner::class) ?: []
        );
    }

    public function test_review_listener_skips_when_tenant_feature_disabled(): void
    {
        $this->setFederationFeature($this->testTenantId, false);

        // Feature service should never be consulted if hasFeature returned false
        $featureService = Mockery::mock(FederationFeatureService::class);
        $featureService->shouldNotReceive('isTenantFederationEnabled');

        Http::fake();

        $review = new Review();
        $review->id = 700;
        $review->receiver_id = 1;
        $review->rating = 5;
        $review->tenant_id = $this->testTenantId;

        $event = new ReviewCreated($review, $this->testTenantId);

        $listener = new PushReviewToFederatedPartner($featureService);
        $listener->handle($event);

        Http::assertNothingSent();
    }

    public function test_review_listener_restores_tenant_context_from_event(): void
    {
        // Simulate a queue worker that booted with a different (or stale)
        // tenant context — the listener must rebind to the event's tenant.
        $this->setFederationFeature($this->testTenantId, true);
        TenantContext::setById(999999); // unrelated tenant id

        $featureService = Mockery::mock(FederationFeatureService::class);
        // If tenant context is restored, isTenantFederationEnabled will be
        // consulted with the event's tenant id.
        $featureService->shouldReceive('isTenantFederationEnabled')
            ->with($this->testTenantId)
            ->atLeast()->once()
            ->andReturn(false); // short-circuit before any push

        Http::fake();

        $review = new Review();
        $review->id = 701;
        $review->receiver_id = 1;
        $review->rating = 5;
        $review->tenant_id = $this->testTenantId;

        $event = new ReviewCreated($review, $this->testTenantId);

        $listener = new PushReviewToFederatedPartner($featureService);
        $listener->handle($event);

        // After handle(), tenant context should have been restored
        $this->assertSame($this->testTenantId, TenantContext::getId());
        Http::assertNothingSent();
    }

    public function test_listener_skips_when_tenant_feature_disabled(): void
    {
        $this->setFederationFeature($this->testTenantId, false);

        // Feature service should never be consulted if hasFeature returned false
        $featureService = Mockery::mock(FederationFeatureService::class);
        $featureService->shouldNotReceive('isTenantFederationEnabled');

        Http::fake();

        $listing = new Listing();
        $listing->id = 500;
        $listing->title = 'Test';
        $listing->federated_visibility = 'listed';

        $user = new User();
        $user->id = 1;

        $event = new ListingCreated($listing, $user, $this->testTenantId);

        $listener = new PushListingToFederatedPartners($featureService);
        $listener->handle($event);

        Http::assertNothingSent();
    }

    public function test_listener_skips_when_system_federation_disabled(): void
    {
        $this->setFederationFeature($this->testTenantId, true);

        $featureService = Mockery::mock(FederationFeatureService::class);
        $featureService->shouldReceive('isTenantFederationEnabled')
            ->with($this->testTenantId)
            ->once()
            ->andReturn(false);

        Http::fake();

        $listing = new Listing();
        $listing->id = 501;
        $listing->title = 'Test';
        $listing->federated_visibility = 'listed';

        $user = new User();
        $user->id = 1;

        $event = new ListingCreated($listing, $user, $this->testTenantId);

        $listener = new PushListingToFederatedPartners($featureService);
        $listener->handle($event);

        Http::assertNothingSent();
    }

    public function test_listener_skips_when_visibility_is_local(): void
    {
        $this->setFederationFeature($this->testTenantId, true);

        $featureService = Mockery::mock(FederationFeatureService::class);
        $featureService->shouldReceive('isTenantFederationEnabled')
            ->andReturn(true);

        Http::fake();

        $listing = new Listing();
        $listing->id = 502;
        $listing->title = 'Test';
        $listing->federated_visibility = 'local';  // NOT listed/bookable

        $user = new User();
        $user->id = 1;

        $event = new ListingCreated($listing, $user, $this->testTenantId);

        $listener = new PushListingToFederatedPartners($featureService);
        $listener->handle($event);

        Http::assertNothingSent();
    }
}
