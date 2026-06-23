<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\CourseCreditService;
use App\Core\TenantContext;
use App\Models\Course;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

/**
 * CourseCreditServiceTest
 *
 * Strategy: CourseCreditService calls WalletService::transfer internally.
 * WalletService does real DB work (balance reads, ledger writes) which requires
 * a fully-seeded user with balance.  We test:
 *   (a) Guard conditions that skip the transfer entirely (zero cost, self-enrol)
 *       — these return without touching the DB and are fully testable with a
 *       minimal Course stub.
 *   (b) Insufficient-balance path — WalletService throws RuntimeException which
 *       chargeEnrollment catches and returns charged=false+reason.
 *   (c) Successful transfer — seed two real users with adequate balances, then
 *       verify the ledger row, sender debit, and receiver credit.
 *
 * Skipped: outbound HTTP inside WalletService (there is none); Pusher events
 * (fire-and-forget, don't affect return values).
 */
class CourseCreditServiceTest extends TestCase
{
    use DatabaseTransactions;

    // Unique high-range IDs so we don't collide with real tenant-2 data.
    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Build a minimal Course model without persisting it.
     */
    private function makeCourse(float $creditCost, int $authorId, ?int $id = null): Course
    {
        $course = new Course();
        $course->credit_cost       = $creditCost;
        $course->author_user_id    = $authorId;
        $course->title             = 'Test Course';
        if ($id !== null) {
            $course->id = $id;
        }
        return $course;
    }

    /**
     * Insert a minimal user row with a given balance and return the ID.
     */
    private function insertUser(float $balance = 0.0, string $suffix = ''): int
    {
        $uid = uniqid($suffix, true);
        return DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Test User ' . $uid,
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => 'testuser.' . $uid . '@example.test',
            'status'     => 'active',
            'balance'    => $balance,
            'role'       => 'member',
            'is_approved'=> 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── Guard: zero credit cost ───────────────────────────────────────────────

    public function test_chargeEnrollment_returns_not_charged_when_credit_cost_is_zero(): void
    {
        $course  = $this->makeCourse(0.0, 1);
        $result  = CourseCreditService::chargeEnrollment($course, 999);

        $this->assertFalse($result['charged']);
        $this->assertSame(0.0, $result['amount']);
    }

    public function test_chargeEnrollment_returns_not_charged_when_credit_cost_is_negative(): void
    {
        $course  = $this->makeCourse(-5.0, 1);
        $result  = CourseCreditService::chargeEnrollment($course, 999);

        $this->assertFalse($result['charged']);
        $this->assertSame(0.0, $result['amount']);
    }

    // ── Guard: self-enrolment ─────────────────────────────────────────────────

    public function test_chargeEnrollment_returns_not_charged_when_learner_is_author(): void
    {
        $authorId = 42;
        $course   = $this->makeCourse(10.0, $authorId);
        $result   = CourseCreditService::chargeEnrollment($course, $authorId);

        $this->assertFalse($result['charged']);
        $this->assertSame(0.0, $result['amount']);
    }

    // ── Guard: amount is preserved in error result ────────────────────────────

    public function test_chargeEnrollment_returns_correct_amount_in_error_when_recipient_not_found(): void
    {
        // Use a non-existent author ID so WalletService throws RuntimeException.
        $course  = $this->makeCourse(7.5, 999999999);
        $result  = CourseCreditService::chargeEnrollment($course, 1);

        $this->assertFalse($result['charged']);
        $this->assertSame(7.5, $result['amount']);
        $this->assertArrayHasKey('reason', $result);
        $this->assertNotEmpty($result['reason']);
    }

    // ── Guard: rounding preserved in error ───────────────────────────────────

    public function test_chargeEnrollment_rounds_amount_to_two_decimal_places_in_error(): void
    {
        // 3.1415 → 3.14 after round(…,2)
        $course  = $this->makeCourse(3.1415, 999999999);
        $result  = CourseCreditService::chargeEnrollment($course, 1);

        $this->assertFalse($result['charged']);
        $this->assertSame(3.14, $result['amount']);
    }

    // ── Guard: insufficient balance ───────────────────────────────────────────

    public function test_chargeEnrollment_returns_not_charged_when_learner_has_insufficient_balance(): void
    {
        $author  = $this->insertUser(100.0, 'author');
        $learner = $this->insertUser(0.0,   'learner');   // zero balance

        $course  = $this->makeCourse(10.0, $author);
        $result  = CourseCreditService::chargeEnrollment($course, $learner);

        $this->assertFalse($result['charged']);
        $this->assertSame(10.0, $result['amount']);
        $this->assertArrayHasKey('reason', $result);
    }

    // ── Happy path: successful transfer ──────────────────────────────────────

    public function test_chargeEnrollment_charges_learner_and_credits_author_on_success(): void
    {
        $author  = $this->insertUser(0.0,    'author');
        $learner = $this->insertUser(50.0,   'learner');

        $course = $this->makeCourse(5.0, $author);
        $result = CourseCreditService::chargeEnrollment($course, $learner);

        $this->assertTrue($result['charged']);
        $this->assertSame(5.0, $result['amount']);

        // Learner should have been debited.
        $learnerBalance = DB::table('users')->where('id', $learner)->value('balance');
        $this->assertEquals(45.0, (float) $learnerBalance, 'Learner balance should be 50 - 5 = 45');

        // Author should have been credited.
        $authorBalance = DB::table('users')->where('id', $author)->value('balance');
        $this->assertEquals(5.0, (float) $authorBalance, 'Author balance should be 0 + 5 = 5');
    }

    public function test_chargeEnrollment_creates_transaction_ledger_entry(): void
    {
        $author  = $this->insertUser(0.0,    'author');
        $learner = $this->insertUser(20.0,   'learner');

        $course = $this->makeCourse(3.0, $author);
        CourseCreditService::chargeEnrollment($course, $learner);

        $txCount = DB::table('transactions')
            ->where('tenant_id', self::TENANT_ID)
            ->where('sender_id',   $learner)
            ->where('receiver_id', $author)
            ->where('amount', 3.0)
            ->count();

        $this->assertGreaterThanOrEqual(1, $txCount, 'A ledger transaction row should be created');
    }

    public function test_chargeEnrollment_charged_result_has_no_reason_key(): void
    {
        $author  = $this->insertUser(0.0,   'author');
        $learner = $this->insertUser(10.0,  'learner');

        $course = $this->makeCourse(1.0, $author);
        $result = CourseCreditService::chargeEnrollment($course, $learner);

        $this->assertTrue($result['charged']);
        $this->assertArrayNotHasKey('reason', $result);
    }

    // ── Decimal precision end-to-end ─────────────────────────────────────────

    public function test_chargeEnrollment_handles_decimal_cost_correctly(): void
    {
        $author  = $this->insertUser(0.0,   'author');
        $learner = $this->insertUser(10.0,  'learner');

        $course = $this->makeCourse(2.50, $author);
        $result = CourseCreditService::chargeEnrollment($course, $learner);

        $this->assertTrue($result['charged']);
        $this->assertSame(2.5, $result['amount']);

        $learnerBalance = (float) DB::table('users')->where('id', $learner)->value('balance');
        $this->assertEquals(7.5, $learnerBalance);
    }
}
