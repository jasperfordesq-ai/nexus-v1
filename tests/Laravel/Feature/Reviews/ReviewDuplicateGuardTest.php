<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Reviews;

use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Reviews had no unique key on (reviewer_id, transaction_id) — two concurrent
 * submissions (double-click / mobile retry) both passed the exists() check
 * and created two review rows: double rating count, double notifications,
 * double leave_review XP. The migration adds the unique backstop and
 * ReviewService maps the violation to the same "already reviewed" error.
 */
class ReviewDuplicateGuardTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    private function makeUser(): int
    {
        return (int) DB::table('users')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'first_name' => 'Review',
            'last_name' => 'Tester',
            'email' => 'rev.' . uniqid('', true) . '@example.com',
            'username' => 'rev_' . substr(md5(uniqid('', true)), 0, 8),
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeTransaction(int $senderId, int $receiverId): int
    {
        return (int) DB::table('transactions')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'amount' => 1,
            'description' => 'Review race test exchange',
            'status' => 'completed',
            'created_at' => now(),
        ]);
    }

    public function test_unique_index_blocks_duplicate_reviewer_transaction_rows(): void
    {
        $reviewer = $this->makeUser();
        $receiver = $this->makeUser();
        $txId = $this->makeTransaction($reviewer, $receiver);

        $row = [
            'tenant_id' => self::TENANT_ID,
            'reviewer_id' => $reviewer,
            'receiver_id' => $receiver,
            'transaction_id' => $txId,
            'rating' => 5,
            'comment' => 'Great exchange',
            'status' => 'approved',
            'created_at' => now(),
        ];

        DB::table('reviews')->insert($row);

        // Old schema accepted this second row silently — the concurrent-submit
        // race materialised as two reviews, double XP, double rating count.
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('reviews')->insert($row);
    }

    public function test_null_transaction_reviews_are_not_blocked_by_the_unique_index(): void
    {
        $reviewer = $this->makeUser();
        $receiverA = $this->makeUser();
        $receiverB = $this->makeUser();

        foreach ([$receiverA, $receiverB] as $receiver) {
            DB::table('reviews')->insert([
                'tenant_id' => self::TENANT_ID,
                'reviewer_id' => $reviewer,
                'receiver_id' => $receiver,
                'transaction_id' => null,
                'rating' => 4,
                'comment' => 'General member review',
                'status' => 'approved',
                'created_at' => now(),
            ]);
        }

        $this->assertSame(
            2,
            (int) DB::table('reviews')->where('reviewer_id', $reviewer)->whereNull('transaction_id')->count(),
            'Multiple NULL-transaction reviews by one reviewer must remain allowed.'
        );
    }

    public function test_service_maps_unique_violation_to_already_reviewed_error(): void
    {
        $reviewer = $this->makeUser();
        $receiver = $this->makeUser();
        $txId = $this->makeTransaction($reviewer, $receiver);

        // Plant the conflicting row under a DIFFERENT tenant so the service's
        // tenant-scoped exists() check misses it — exactly what a concurrent
        // same-tenant insert does in the race window. The global unique index
        // is the backstop; the service must surface the friendly error, not a 500.
        DB::table('reviews')->insert([
            'tenant_id' => 1,
            'reviewer_id' => $reviewer,
            'receiver_id' => $receiver,
            'transaction_id' => $txId,
            'rating' => 5,
            'status' => 'approved',
            'created_at' => now(),
        ]);

        $service = app(\App\Services\ReviewService::class);

        try {
            $service->create($reviewer, [
                'receiver_id' => $receiver,
                'rating' => 5,
                'comment' => 'Race duplicate',
                'transaction_id' => $txId,
            ]);
            $this->fail('Expected the duplicate review to be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already reviewed', $e->getMessage());
        }

        $this->assertSame(
            1,
            (int) DB::table('reviews')->where('reviewer_id', $reviewer)->where('transaction_id', $txId)->count(),
            'Exactly one review row must exist after the duplicate was rejected.'
        );
    }
}
