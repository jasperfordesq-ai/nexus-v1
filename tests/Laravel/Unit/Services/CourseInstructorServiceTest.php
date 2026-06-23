<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\CourseInstructorService;
use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

/**
 * CourseInstructorServiceTest
 *
 * CourseInstructorService is a thin Eloquent-backed service with no HTTP calls
 * or complex computation, so all methods are tested against the real nexus_test
 * database using DatabaseTransactions (all inserts roll back after each test).
 *
 * Tenant ID 2 (hour-timebank) is the default set up by TestCase::setUpTenantContext().
 * The CourseInstructor model carries HasTenantScope which auto-fills tenant_id=2
 * on creating and filters queries to tenant 2.  We insert user rows with real IDs
 * so FK constraints (if any) are satisfied.
 */
class CourseInstructorServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function insertUser(string $suffix = ''): int
    {
        $uid = uniqid($suffix, true);
        return DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Instructor Test ' . $uid,
            'first_name' => 'Inst',
            'last_name'  => 'Test',
            'email'      => 'insttest.' . $uid . '@example.test',
            'status'     => 'active',
            'balance'    => 0.0,
            'role'       => 'member',
            'is_approved'=> 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── isInstructor ──────────────────────────────────────────────────────────

    public function test_isInstructor_returns_false_for_unknown_user(): void
    {
        // Use an ID that is virtually impossible to have a grant row.
        $this->assertFalse(CourseInstructorService::isInstructor(PHP_INT_MAX));
    }

    public function test_isInstructor_returns_true_after_grant(): void
    {
        $userId = $this->insertUser('isInst');
        CourseInstructorService::grant($userId);

        $this->assertTrue(CourseInstructorService::isInstructor($userId));
    }

    public function test_isInstructor_returns_false_after_revoke(): void
    {
        $userId = $this->insertUser('revokeInst');
        CourseInstructorService::grant($userId);
        CourseInstructorService::revoke($userId);

        $this->assertFalse(CourseInstructorService::isInstructor($userId));
    }

    // ── grant ─────────────────────────────────────────────────────────────────

    public function test_grant_creates_a_course_instructor_row(): void
    {
        $userId = $this->insertUser('grant');
        CourseInstructorService::grant($userId);

        $count = DB::table('course_instructors')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $userId)
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_grant_returns_a_CourseInstructor_instance(): void
    {
        $userId = $this->insertUser('returnType');
        $result = CourseInstructorService::grant($userId);

        $this->assertInstanceOf(\App\Models\CourseInstructor::class, $result);
        $this->assertSame($userId, (int) $result->user_id);
    }

    public function test_grant_is_idempotent(): void
    {
        $userId = $this->insertUser('idem');
        CourseInstructorService::grant($userId);
        CourseInstructorService::grant($userId);   // second call should not throw

        $count = DB::table('course_instructors')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $userId)
            ->count();

        $this->assertSame(1, $count, 'Duplicate grant should not create a second row');
    }

    public function test_grant_stores_granted_by_when_provided(): void
    {
        $userId    = $this->insertUser('grantedBy');
        $adminId   = $this->insertUser('admin');

        CourseInstructorService::grant($userId, $adminId);

        $row = DB::table('course_instructors')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $userId)
            ->first();

        $this->assertSame($adminId, (int) $row->granted_by);
    }

    public function test_grant_stores_granted_at_timestamp(): void
    {
        $userId = $this->insertUser('grantedAt');
        CourseInstructorService::grant($userId);

        $row = DB::table('course_instructors')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $userId)
            ->first();

        $this->assertNotNull($row->granted_at);
    }

    public function test_grant_without_granter_stores_null_granted_by(): void
    {
        $userId = $this->insertUser('noGranter');
        CourseInstructorService::grant($userId);

        $row = DB::table('course_instructors')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $userId)
            ->first();

        $this->assertNull($row->granted_by);
    }

    // ── revoke ────────────────────────────────────────────────────────────────

    public function test_revoke_removes_the_instructor_row(): void
    {
        $userId = $this->insertUser('revoke');
        CourseInstructorService::grant($userId);
        CourseInstructorService::revoke($userId);

        $count = DB::table('course_instructors')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $userId)
            ->count();

        $this->assertSame(0, $count);
    }

    public function test_revoke_on_non_existent_grant_does_not_throw(): void
    {
        // Should be a no-op rather than an exception.
        CourseInstructorService::revoke(PHP_INT_MAX);
        $this->assertTrue(true);  // reaching here without exception = pass
    }

    // ── list ─────────────────────────────────────────────────────────────────

    public function test_list_returns_array(): void
    {
        $result = CourseInstructorService::list();
        $this->assertIsArray($result);
    }

    public function test_list_includes_newly_granted_user(): void
    {
        $userId = $this->insertUser('listTest');
        CourseInstructorService::grant($userId);

        $ids = array_column(CourseInstructorService::list(), 'user_id');
        $this->assertContains($userId, $ids, 'Newly granted user should appear in list()');
    }

    public function test_list_does_not_include_revoked_user(): void
    {
        $userId = $this->insertUser('revokedList');
        CourseInstructorService::grant($userId);
        CourseInstructorService::revoke($userId);

        $ids = array_column(CourseInstructorService::list(), 'user_id');
        $this->assertNotContains($userId, $ids, 'Revoked user should NOT appear in list()');
    }
}
