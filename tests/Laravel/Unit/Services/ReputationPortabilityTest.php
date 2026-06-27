<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Events\ReviewCreated;
use App\Models\Review;
use App\Models\User;
use App\Services\MemberRankingService;
use App\Services\Protocols\KomunitinAdapter;
use App\Core\TenantContext;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Laravel\TestCase;

/**
 * Reputation portability — verifies reviews follow users across federation
 * boundaries so community trust isn't lost when members operate on multiple
 * tenants/platforms.
 */
class ReputationPortabilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_review_service_get_for_user_returns_local_and_federated_reviews(): void
    {
        $receiver = User::factory()->forTenant($this->testTenantId)->create();
        $reviewer = User::factory()->forTenant($this->testTenantId)->create();
        $foreignReviewer = User::factory()->forTenant(999)->create();

        // Local review (same tenant)
        DB::table('reviews')->insert([
            'tenant_id'         => $this->testTenantId,
            'reviewer_id'       => $reviewer->id,
            'receiver_id'       => $receiver->id,
            'rating'            => 5,
            'comment'           => 'Local excellent',
            'status'            => 'approved',
            'review_type'       => 'local',
            'show_cross_tenant' => 1,
            'created_at'        => now(),
        ]);

        // Federated review (came from tenant 999, receiver_tenant_id = current)
        DB::table('reviews')->insert([
            'tenant_id'          => 999,
            'reviewer_id'        => $foreignReviewer->id,
            'reviewer_tenant_id' => 999,
            'receiver_id'        => $receiver->id,
            'receiver_tenant_id' => $this->testTenantId,
            'rating'             => 4,
            'comment'            => 'Federated good',
            'status'             => 'approved',
            'review_type'        => 'federated',
            'show_cross_tenant'  => 1,
            'created_at'         => now(),
        ]);

        // User factory observers reset TenantContext to tenant 1 during create();
        // ReviewService::scopeWithFederated() reads TenantContext::getId() to build
        // the local-OR-federated filter, so re-pin the test tenant before querying
        // or both reviews fall outside the (tenant 1) scope and total comes back 0.
        TenantContext::setById($this->testTenantId);

        /** @var ReviewService $service */
        $service = app(ReviewService::class);
        $result = $service->getForUser($receiver->id);

        $this->assertSame(2, $result['total'], 'Both local and federated reviews should be counted');
        $this->assertEqualsWithDelta(4.5, $result['average_rating'], 0.001);
        $this->assertCount(2, $result['items']);
    }

    public function test_member_ranking_reputation_includes_federated_reviews(): void
    {
        $receiver = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $reviewer = User::factory()->forTenant($this->testTenantId)->create();

        // Local rating = 2
        DB::table('reviews')->insert([
            'tenant_id'         => $this->testTenantId,
            'reviewer_id'       => $reviewer->id,
            'receiver_id'       => $receiver->id,
            'rating'            => 2,
            'status'            => 'approved',
            'review_type'       => 'local',
            'show_cross_tenant' => 1,
            'created_at'        => now(),
        ]);

        // Federated rating = 5 — from a foreign tenant
        DB::table('reviews')->insert([
            'tenant_id'          => 999,
            'reviewer_id'        => $reviewer->id, // reviewer identity is opaque to this test
            'reviewer_tenant_id' => 999,
            'receiver_id'        => $receiver->id,
            'receiver_tenant_id' => $this->testTenantId,
            'rating'             => 5,
            'status'             => 'approved',
            'review_type'        => 'federated',
            'show_cross_tenant'  => 1,
            'created_at'         => now(),
        ]);

        // Re-pin after factory observers reset TenantContext to tenant 1.
        TenantContext::setById($this->testTenantId);

        $service = new MemberRankingService(new User());
        // rankMembers() returns ['items' => [...rows], 'total' => N]; the ranked
        // member rows live under 'items' (previously the method returned a flat list).
        $ranked = $service->rankMembers($this->testTenantId);

        $row = collect($ranked['items'])->firstWhere('user_id', $receiver->id);
        $this->assertNotNull($row);
        // Reputation is no longer a naive avg/5; calculateReputationScore() blends a
        // Bayesian average (prior mean 3.8, strength 5) with a Wilson lower bound
        // (z=1.96), weighted 0.55/0.45. For reviews [2 (local), 5 (federated)]:
        //   count=2, avg=3.5, positive(>=4)=1
        //   bayes = (5*3.8 + 3.5*2)/(5+2) = 3.7142857 → /5 = 0.7428571
        //   wilson(1/2) = 0.0945287
        //   reputation = 0.7428571*0.55 + 0.0945287*0.45 = 0.451109
        // The federated review IS included: local-only [2] would score 0.385, so
        // this value still proves federated reviews count toward reputation.
        $this->assertEqualsWithDelta(0.4511, $row['reputation'], 0.001);
    }

    public function test_review_created_event_is_dispatched_on_create(): void
    {
        Event::fake([ReviewCreated::class]);

        $reviewer = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        // Re-pin after factory observers reset TenantContext to tenant 1.
        // ReviewService::create() now tenant-scopes the receiver_id existence
        // check to TenantContext::getId(); without this the tenant-2 receiver
        // is invisible under the leaked tenant-1 scope and validation fails.
        TenantContext::setById($this->testTenantId);

        /** @var ReviewService $service */
        $service = app(ReviewService::class);
        $service->create($reviewer->id, [
            'receiver_id' => $receiver->id,
            'rating'      => 5,
            'comment'     => 'Great collaborator',
        ]);

        Event::assertDispatched(ReviewCreated::class, function (ReviewCreated $event) use ($receiver) {
            return $event->review instanceof Review
                && (int) $event->review->receiver_id === $receiver->id
                && (int) $event->review->rating === 5;
        });
    }

    public function test_komunitin_adapter_send_review_produces_valid_jsonapi_envelope(): void
    {
        $adapter = new KomunitinAdapter();

        $envelope = $adapter->transformOutboundReview([
            'rating'                => 4,
            'comment'               => 'Solid work',
            'transaction_ref'       => 'txn-abc-123',
            'reviewer_id'           => 42,
            'reviewer_handle'       => 'alice',
            'reviewer_tenant'       => $this->testTenantId,
            'receiver_external_id'  => 'remote-user-777',
            'created_at'            => '2026-04-12T10:00:00Z',
        ]);

        $this->assertArrayHasKey('data', $envelope);
        $this->assertSame('reviews', $envelope['data']['type']);

        $attrs = $envelope['data']['attributes'];
        $this->assertSame(4, $attrs['rating']);
        $this->assertSame('Solid work', $attrs['comment']);
        $this->assertSame('federated', $attrs['review_type']);
        $this->assertSame('txn-abc-123', $attrs['transaction_ref']);

        // Attestation — the receiving node uses this to verify origin
        $this->assertSame('42', $attrs['attestation']['reviewer_external_id']);
        $this->assertSame('alice', $attrs['attestation']['reviewer_handle']);
        $this->assertSame('nexus', $attrs['attestation']['source_platform']);

        // Relationship — receiver pointed to the Komunitin account
        $this->assertSame('remote-user-777', $envelope['data']['relationships']['receiver']['data']['id']);
        $this->assertSame('accounts', $envelope['data']['relationships']['receiver']['data']['type']);
    }
}
