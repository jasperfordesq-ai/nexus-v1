<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\CourseGroupService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * CourseGroupServiceTest
 *
 * Tests attach/detach idempotency, error guarding (non-existent group),
 * groupIdsForCourse, and the coursesForGroup query.
 *
 * coursesForGroup inherits the parent Group's member-content boundary before
 * applying course visibility, publication, and role rules.
 */
class CourseGroupServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function insertUser(): int
    {
        $uid = uniqid('grptest_', true);
        return DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Grp User ' . $uid,
            'first_name' => 'Grp',
            'last_name'  => 'User',
            'email'      => $uid . '@example.test',
            'status'     => 'active',
            'balance'    => 0,
            'role'       => 'member',
            'is_approved'=> 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertGroup(int $ownerId): int
    {
        $uid = uniqid('grp_', true);
        return DB::table('groups')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'owner_id'   => $ownerId,
            'name'       => 'Group ' . $uid,
            'slug'       => 'grp-' . $uid,
            'visibility' => 'public',
            'status'     => 'active',
            'is_active'  => 1,
            'created_at' => now(),
        ]);
    }

    private function insertCourse(int $authorId, string $status = 'published', string $visibility = 'public'): int
    {
        $uid = uniqid('crs_', true);
        return DB::table('courses')->insertGetId([
            'tenant_id'         => self::TENANT_ID,
            'author_user_id'    => $authorId,
            'title'             => 'Course ' . $uid,
            'slug'              => 'crs-' . $uid,
            'status'            => $status,
            'moderation_status' => 'approved',
            'level'             => 'beginner',
            'visibility'        => $visibility,
            'enrollment_type'   => 'self_paced',
            'enrollment_count'  => 0,
            'completion_count'  => 0,
            'published_at'      => now(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    private function insertGroupMember(int $groupId, int $userId): void
    {
        DB::table('group_members')->insert([
            'tenant_id' => self::TENANT_ID,
            'group_id' => $groupId,
            'user_id' => $userId,
            'status' => 'active',
            'role' => 'member',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── attach ────────────────────────────────────────────────────────────────

    public function test_attach_creates_link_row(): void
    {
        $ownerId  = $this->insertUser();
        $authorId = $this->insertUser();
        $groupId  = $this->insertGroup($ownerId);
        $courseId = $this->insertCourse($authorId);

        $link = CourseGroupService::attach($courseId, $groupId);

        $this->assertSame($courseId, (int) $link->course_id);
        $this->assertSame($groupId, (int) $link->group_id);
        $this->assertSame(self::TENANT_ID, (int) DB::table('course_group_links')->where('id', $link->id)->value('tenant_id'));
    }

    public function test_attach_is_idempotent_on_second_call(): void
    {
        $ownerId  = $this->insertUser();
        $authorId = $this->insertUser();
        $groupId  = $this->insertGroup($ownerId);
        $courseId = $this->insertCourse($authorId);

        $first  = CourseGroupService::attach($courseId, $groupId);
        $second = CourseGroupService::attach($courseId, $groupId);

        $this->assertSame($first->id, $second->id);

        $count = DB::table('course_group_links')
            ->where('course_id', $courseId)
            ->where('group_id', $groupId)
            ->count();
        $this->assertSame(1, $count);
    }

    public function test_attach_throws_when_group_does_not_exist(): void
    {
        $authorId = $this->insertUser();
        $courseId = $this->insertCourse($authorId);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('group_not_found');

        CourseGroupService::attach($courseId, 99999999);
    }

    // ── detach ────────────────────────────────────────────────────────────────

    public function test_detach_removes_existing_link(): void
    {
        $ownerId  = $this->insertUser();
        $authorId = $this->insertUser();
        $groupId  = $this->insertGroup($ownerId);
        $courseId = $this->insertCourse($authorId);

        CourseGroupService::attach($courseId, $groupId);
        CourseGroupService::detach($courseId, $groupId);

        $count = DB::table('course_group_links')
            ->where('course_id', $courseId)
            ->where('group_id', $groupId)
            ->count();
        $this->assertSame(0, $count);
    }

    public function test_detach_is_safe_when_link_does_not_exist(): void
    {
        $ownerId  = $this->insertUser();
        $authorId = $this->insertUser();
        $groupId  = $this->insertGroup($ownerId);
        $courseId = $this->insertCourse($authorId);

        // No exception expected; method returns void
        CourseGroupService::detach($courseId, $groupId);

        $this->assertTrue(true); // reached here = no exception
    }

    // ── groupIdsForCourse ─────────────────────────────────────────────────────

    public function test_groupIdsForCourse_returns_empty_when_no_links(): void
    {
        $authorId = $this->insertUser();
        $courseId = $this->insertCourse($authorId);

        $result = CourseGroupService::groupIdsForCourse($courseId);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_groupIdsForCourse_returns_linked_group_ids(): void
    {
        $ownerId  = $this->insertUser();
        $authorId = $this->insertUser();
        $groupId1 = $this->insertGroup($ownerId);
        $groupId2 = $this->insertGroup($ownerId);
        $courseId = $this->insertCourse($authorId);

        CourseGroupService::attach($courseId, $groupId1);
        CourseGroupService::attach($courseId, $groupId2);

        $result = CourseGroupService::groupIdsForCourse($courseId);

        $this->assertCount(2, $result);
        $this->assertContains($groupId1, $result);
        $this->assertContains($groupId2, $result);
    }

    public function test_groupIdsForCourse_returns_ints_not_strings(): void
    {
        $ownerId  = $this->insertUser();
        $authorId = $this->insertUser();
        $groupId  = $this->insertGroup($ownerId);
        $courseId = $this->insertCourse($authorId);

        CourseGroupService::attach($courseId, $groupId);

        $result = CourseGroupService::groupIdsForCourse($courseId);

        $this->assertSame($groupId, $result[0]);
        $this->assertIsInt($result[0]);
    }

    // ── coursesForGroup ───────────────────────────────────────────────────────

    public function test_coursesForGroup_returns_empty_when_no_links(): void
    {
        $ownerId = $this->insertUser();
        $groupId = $this->insertGroup($ownerId);

        $result = CourseGroupService::coursesForGroup($groupId, null);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_coursesForGroup_returns_published_public_courses(): void
    {
        $ownerId  = $this->insertUser();
        $authorId = $this->insertUser();
        $groupId  = $this->insertGroup($ownerId);
        $courseId = $this->insertCourse($authorId, 'published', 'public');

        CourseGroupService::attach($courseId, $groupId);

        $result = CourseGroupService::coursesForGroup($groupId, $ownerId);

        $this->assertCount(1, $result);
        $this->assertSame($courseId, (int) $result[0]['id']);
    }

    public function test_coursesForGroup_excludes_draft_courses(): void
    {
        $ownerId  = $this->insertUser();
        $authorId = $this->insertUser();
        $groupId  = $this->insertGroup($ownerId);
        $courseId = $this->insertCourse($authorId, 'draft', 'public');

        CourseGroupService::attach($courseId, $groupId);

        $result = CourseGroupService::coursesForGroup($groupId, $ownerId);

        $this->assertEmpty($result);
    }

    public function test_coursesForGroup_excludes_archived_courses(): void
    {
        $ownerId  = $this->insertUser();
        $authorId = $this->insertUser();
        $groupId  = $this->insertGroup($ownerId);
        $courseId = $this->insertCourse($authorId, 'archived', 'public');

        CourseGroupService::attach($courseId, $groupId);

        $result = CourseGroupService::coursesForGroup($groupId, $ownerId);

        $this->assertEmpty($result);
    }

    public function test_coursesForGroup_excludes_members_courses_for_anonymous_viewer(): void
    {
        $ownerId  = $this->insertUser();
        $authorId = $this->insertUser();
        $groupId  = $this->insertGroup($ownerId);
        $courseId = $this->insertCourse($authorId, 'published', 'members');

        CourseGroupService::attach($courseId, $groupId);

        // Anonymous viewers cannot cross the parent Group member-content boundary.
        $result = CourseGroupService::coursesForGroup($groupId, null);

        $this->assertEmpty($result);
    }

    public function test_coursesForGroup_returns_members_courses_for_authenticated_viewer(): void
    {
        $ownerId  = $this->insertUser();
        $authorId = $this->insertUser();
        $viewerId = $this->insertUser();
        $groupId  = $this->insertGroup($ownerId);
        $courseId = $this->insertCourse($authorId, 'published', 'members');

        CourseGroupService::attach($courseId, $groupId);
        $this->insertGroupMember($groupId, $viewerId);

        $result = CourseGroupService::coursesForGroup($groupId, $viewerId);

        $this->assertCount(1, $result);
        $this->assertSame($courseId, (int) $result[0]['id']);
    }

    public function test_coursesForGroup_conceals_courses_from_authenticated_non_member(): void
    {
        $ownerId = $this->insertUser();
        $authorId = $this->insertUser();
        $viewerId = $this->insertUser();
        $groupId = $this->insertGroup($ownerId);
        $courseId = $this->insertCourse($authorId, 'published', 'public');

        CourseGroupService::attach($courseId, $groupId);

        $this->assertSame([], CourseGroupService::coursesForGroup($groupId, $viewerId));
    }
}
