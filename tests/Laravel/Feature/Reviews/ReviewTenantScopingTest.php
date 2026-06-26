<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Reviews;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Laravel\TestCase;

/**
 * Regression: review creation must tenant-scope the receiver_id and
 * transaction_id existence checks. The validator used bare
 * `exists:users,id` / `exists:transactions,id`, so a member could create a
 * review row pointing at a user in another tenant, or attach a review to a
 * transaction belonging to another tenant (confirmed live: tenant-2 reviewer
 * referenced a tenant-3 user and a tenant-7 transaction). Within-tenant
 * reviews are unaffected.
 */
class ReviewTenantScopingTest extends TestCase
{
    use DatabaseTransactions;

    /** A second active tenant seeded by the base TestCase. */
    private const OTHER_TENANT_ID = 999;

    private function userInTenant(int $tenantId): User
    {
        $u = User::factory()->forTenant($tenantId)->create();
        // Normalise: console factory/observer interplay can drift TenantContext
        // and leave the row under the wrong tenant.
        DB::table('users')->where('id', $u->id)->update(['tenant_id' => $tenantId]);

        return $u;
    }

    public function test_rejects_review_of_a_user_in_another_tenant(): void
    {
        $reviewer = $this->userInTenant($this->testTenantId);
        $foreign  = $this->userInTenant(self::OTHER_TENANT_ID);

        $svc = app(ReviewService::class);

        $this->expectException(ValidationException::class);
        TenantContext::runForTenant($this->testTenantId, fn () =>
            $svc->create((int) $reviewer->id, ['receiver_id' => $foreign->id, 'rating' => 5]));
    }

    public function test_rejects_review_tied_to_a_transaction_in_another_tenant(): void
    {
        $reviewer = $this->userInTenant($this->testTenantId);
        $receiver = $this->userInTenant($this->testTenantId);

        $foreignTxId = DB::table('transactions')->insertGetId([
            'tenant_id'        => self::OTHER_TENANT_ID,
            'sender_id'        => $reviewer->id,
            'receiver_id'      => $receiver->id,
            'amount'           => 1,
            'description'      => 'foreign-tenant txn',
            'transaction_type' => 'exchange',
            'status'           => 'completed',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $svc = app(ReviewService::class);

        $this->expectException(ValidationException::class);
        TenantContext::runForTenant($this->testTenantId, fn () =>
            $svc->create((int) $reviewer->id, [
                'receiver_id'    => $receiver->id,
                'rating'         => 5,
                'transaction_id' => $foreignTxId,
            ]));
    }

    public function test_allows_a_same_tenant_review(): void
    {
        $reviewer = $this->userInTenant($this->testTenantId);
        $receiver = $this->userInTenant($this->testTenantId);

        $svc = app(ReviewService::class);
        $review = TenantContext::runForTenant($this->testTenantId, fn () =>
            $svc->create((int) $reviewer->id, ['receiver_id' => $receiver->id, 'rating' => 5]));

        $this->assertNotEmpty($review['id'] ?? null);
        $this->assertSame((int) $receiver->id, (int) $review['receiver_id']);
    }
}
