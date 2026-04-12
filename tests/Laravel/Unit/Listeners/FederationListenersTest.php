<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\CommunityEventCreated;
use App\Events\ConnectionAccepted;
use App\Events\GroupCreated;
use App\Events\GroupMemberJoined;
use App\Events\ListingCreated;
use App\Events\MemberProfileUpdated;
use App\Events\ReviewCreated;
use App\Events\VolunteerOpportunityCreated;
use App\Listeners\PushCommunityEventToFederatedPartners;
use App\Listeners\PushConnectionAcceptedToFederatedPartner;
use App\Listeners\PushGroupMembershipToFederatedPartners;
use App\Listeners\PushGroupToFederatedPartners;
use App\Listeners\PushListingToFederatedPartners;
use App\Listeners\PushMemberProfileUpdateToFederatedPartners;
use App\Listeners\PushMessageToFederatedPartner;
use App\Listeners\PushReviewToFederatedPartner;
use App\Listeners\PushTransactionToFederatedPartner;
use App\Listeners\PushVolunteerOpportunityToFederatedPartners;
use App\Models\Connection;
use App\Models\Event as CommunityEventModel;
use App\Models\Group;
use App\Models\Listing;
use App\Models\Review;
use App\Models\User;
use App\Models\VolOpportunity;
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
        $this->assertContains(
            ShouldQueue::class,
            class_implements(PushCommunityEventToFederatedPartners::class) ?: []
        );
        $this->assertContains(
            ShouldQueue::class,
            class_implements(PushGroupToFederatedPartners::class) ?: []
        );
        $this->assertContains(
            ShouldQueue::class,
            class_implements(PushGroupMembershipToFederatedPartners::class) ?: []
        );
        $this->assertContains(
            ShouldQueue::class,
            class_implements(PushConnectionAcceptedToFederatedPartner::class) ?: []
        );
        $this->assertContains(
            ShouldQueue::class,
            class_implements(PushVolunteerOpportunityToFederatedPartners::class) ?: []
        );
        $this->assertContains(
            ShouldQueue::class,
            class_implements(PushMemberProfileUpdateToFederatedPartners::class) ?: []
        );
    }

    // -------- CommunityEvent listener --------

    public function test_community_event_listener_skips_when_tenant_feature_disabled(): void
    {
        $this->setFederationFeature($this->testTenantId, false);
        $featureService = Mockery::mock(FederationFeatureService::class);
        $featureService->shouldNotReceive('isTenantFederationEnabled');

        Http::fake();

        $model = new CommunityEventModel();
        $model->id = 800;
        $model->federated_visibility = 'listed';

        $listener = new PushCommunityEventToFederatedPartners($featureService);
        $listener->handle(new CommunityEventCreated($model, $this->testTenantId));

        Http::assertNothingSent();
    }

    public function test_community_event_listener_skips_when_system_federation_disabled(): void
    {
        $this->setFederationFeature($this->testTenantId, true);
        $featureService = Mockery::mock(FederationFeatureService::class);
        $featureService->shouldReceive('isTenantFederationEnabled')->once()->andReturn(false);

        Http::fake();

        $model = new CommunityEventModel();
        $model->id = 801;
        $model->federated_visibility = 'listed';

        $listener = new PushCommunityEventToFederatedPartners($featureService);
        $listener->handle(new CommunityEventCreated($model, $this->testTenantId));

        Http::assertNothingSent();
    }

    public function test_community_event_listener_restores_tenant_context(): void
    {
        $this->setFederationFeature($this->testTenantId, true);
        TenantContext::setById(999999);

        $featureService = Mockery::mock(FederationFeatureService::class);
        $featureService->shouldReceive('isTenantFederationEnabled')
            ->with($this->testTenantId)
            ->atLeast()->once()
            ->andReturn(false);

        Http::fake();

        $model = new CommunityEventModel();
        $model->id = 802;
        $model->federated_visibility = 'listed';

        $listener = new PushCommunityEventToFederatedPartners($featureService);
        $listener->handle(new CommunityEventCreated($model, $this->testTenantId));

        $this->assertSame($this->testTenantId, TenantContext::getId());
        Http::assertNothingSent();
    }

    public function test_community_event_listener_respects_allow_events_flag(): void
    {
        // No partners with allow_events=1 in fresh fixture — listener should noop.
        $this->setFederationFeature($this->testTenantId, true);

        $featureService = Mockery::mock(FederationFeatureService::class);
        $featureService->shouldReceive('isTenantFederationEnabled')->andReturn(true);

        Http::fake();

        $model = new CommunityEventModel();
        $model->id = 803;
        $model->federated_visibility = 'listed';

        $listener = new PushCommunityEventToFederatedPartners($featureService);
        $listener->handle(new CommunityEventCreated($model, $this->testTenantId));

        Http::assertNothingSent();
    }

    // -------- Group listeners --------

    public function test_group_listener_skips_when_tenant_feature_disabled(): void
    {
        $this->setFederationFeature($this->testTenantId, false);
        $featureService = Mockery::mock(FederationFeatureService::class);
        $featureService->shouldNotReceive('isTenantFederationEnabled');

        Http::fake();

        $group = new Group();
        $group->id = 810;
        $group->name = 'Test';
        $group->federated_visibility = 'listed';

        $listener = new PushGroupToFederatedPartners($featureService);
        $listener->handle(new GroupCreated($group, $this->testTenantId));

        Http::assertNothingSent();
    }

    public function test_group_listener_skips_when_system_federation_disabled(): void
    {
        $this->setFederationFeature($this->testTenantId, true);
        $featureService = Mockery::mock(FederationFeatureService::class);
        $featureService->shouldReceive('isTenantFederationEnabled')->once()->andReturn(false);

        Http::fake();

        $group = new Group();
        $group->id = 811;
        $group->federated_visibility = 'listed';

        $listener = new PushGroupToFederatedPartners($featureService);
        $listener->handle(new GroupCreated($group, $this->testTenantId));

        Http::assertNothingSent();
    }

    public function test_group_membership_listener_restores_tenant_context(): void
    {
        $this->setFederationFeature($this->testTenantId, true);
        TenantContext::setById(999999);

        $featureService = Mockery::mock(FederationFeatureService::class);
        $featureService->shouldReceive('isTenantFederationEnabled')
            ->with($this->testTenantId)
            ->atLeast()->once()
            ->andReturn(false);

        Http::fake();

        $listener = new PushGroupMembershipToFederatedPartners($featureService);
        $listener->handle(new GroupMemberJoined(42, 99, $this->testTenantId));

        $this->assertSame($this->testTenantId, TenantContext::getId());
        Http::assertNothingSent();
    }

    // -------- Connection accepted listener --------

    public function test_connection_accepted_listener_skips_when_tenant_feature_disabled(): void
    {
        $this->setFederationFeature($this->testTenantId, false);
        $featureService = Mockery::mock(FederationFeatureService::class);
        $featureService->shouldNotReceive('isTenantFederationEnabled');

        Http::fake();

        $conn = new Connection();
        $conn->id = 820;
        $conn->requester_id = 1;
        $conn->receiver_id = 2;

        $listener = new PushConnectionAcceptedToFederatedPartner($featureService);
        $listener->handle(new ConnectionAccepted($conn, $this->testTenantId));

        Http::assertNothingSent();
    }

    public function test_connection_accepted_listener_skips_when_no_federated_identities(): void
    {
        $this->setFederationFeature($this->testTenantId, true);
        $featureService = Mockery::mock(FederationFeatureService::class);
        $featureService->shouldReceive('isTenantFederationEnabled')->andReturn(true);

        Http::fake();

        $conn = new Connection();
        $conn->id = 821;
        // Use wildly-high IDs so no federated_identities row can match
        $conn->requester_id = 9999998;
        $conn->receiver_id = 9999999;

        $listener = new PushConnectionAcceptedToFederatedPartner($featureService);
        $listener->handle(new ConnectionAccepted($conn, $this->testTenantId));

        Http::assertNothingSent();
    }

    // -------- Volunteer opportunity listener --------

    public function test_volunteer_listener_skips_when_tenant_feature_disabled(): void
    {
        $this->setFederationFeature($this->testTenantId, false);
        $featureService = Mockery::mock(FederationFeatureService::class);
        $featureService->shouldNotReceive('isTenantFederationEnabled');

        Http::fake();

        $opp = new VolOpportunity();
        $opp->id = 830;
        $opp->title = 'Help out';

        $listener = new PushVolunteerOpportunityToFederatedPartners($featureService);
        $listener->handle(new VolunteerOpportunityCreated($opp, $this->testTenantId));

        Http::assertNothingSent();
    }

    public function test_volunteer_listener_skips_when_system_federation_disabled(): void
    {
        $this->setFederationFeature($this->testTenantId, true);
        $featureService = Mockery::mock(FederationFeatureService::class);
        $featureService->shouldReceive('isTenantFederationEnabled')->once()->andReturn(false);

        Http::fake();

        $opp = new VolOpportunity();
        $opp->id = 831;

        $listener = new PushVolunteerOpportunityToFederatedPartners($featureService);
        $listener->handle(new VolunteerOpportunityCreated($opp, $this->testTenantId));

        Http::assertNothingSent();
    }

    // -------- Member profile update listener --------

    public function test_member_profile_listener_skips_when_tenant_feature_disabled(): void
    {
        $this->setFederationFeature($this->testTenantId, false);
        $featureService = Mockery::mock(FederationFeatureService::class);
        $featureService->shouldNotReceive('isTenantFederationEnabled');

        Http::fake();

        $user = new User();
        $user->id = 1;
        $user->first_name = 'Alice';

        $listener = new PushMemberProfileUpdateToFederatedPartners($featureService);
        $listener->handle(new MemberProfileUpdated($user, ['first_name'], $this->testTenantId));

        Http::assertNothingSent();
    }

    public function test_member_profile_listener_skips_when_no_syncable_fields(): void
    {
        $this->setFederationFeature($this->testTenantId, true);
        $featureService = Mockery::mock(FederationFeatureService::class);
        // System gate *may* be checked depending on implementation order; we
        // only care that nothing is ultimately sent.
        $featureService->shouldReceive('isTenantFederationEnabled')->andReturn(true);

        Http::fake();

        $user = new User();
        $user->id = 1;

        // 'password' / 'email' are not in SYNCABLE_FIELDS
        $listener = new PushMemberProfileUpdateToFederatedPartners($featureService);
        $listener->handle(new MemberProfileUpdated($user, ['password', 'email'], $this->testTenantId));

        Http::assertNothingSent();
    }

    public function test_member_profile_listener_skips_when_user_has_no_federated_identity(): void
    {
        $this->setFederationFeature($this->testTenantId, true);
        $featureService = Mockery::mock(FederationFeatureService::class);
        $featureService->shouldReceive('isTenantFederationEnabled')->andReturn(true);

        Http::fake();

        $user = new User();
        $user->id = 9999999; // no federated_identities row
        $user->first_name = 'Alice';

        $listener = new PushMemberProfileUpdateToFederatedPartners($featureService);
        $listener->handle(new MemberProfileUpdated($user, ['first_name'], $this->testTenantId));

        Http::assertNothingSent();
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
