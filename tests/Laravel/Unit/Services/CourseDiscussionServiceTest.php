<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\CourseDiscussionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * CourseDiscussionServiceTest
 *
 * Tests threaded discussions: create, list (with replies), setStatus,
 * delete (cascade to replies), and cross-lesson parent guard.
 * Uses a synthetic tenant (99401) so rows don't collide with real data.
 */
class CourseDiscussionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99401;

    private int $userId;
    private int $courseId;
    private int $lessonId;

    protected function setUp(): void
    {
        parent::setUp();

        // CourseObserver dispatches ReindexEmbeddingJob on every course create/update,
        // and the Queue::before/after hooks in AppServiceProvider call
        // TenantContext::reset().  Queue::fake() prevents any dispatch from running
        // so the tenant context stays stable throughout the test.
        Queue::fake();

        DB::table('tenants')->insertOrIgnore([
            'id'                => self::TENANT_ID,
            'name'              => 'Test CourseDiscussion Tenant',
            'slug'              => 'test-99401',
            'is_active'         => true,
            'depth'             => 0,
            'allows_subtenants' => false,
            'created_at'        => '2024-01-01 00:00:00',
            'updated_at'        => '2024-01-01 00:00:00',
        ]);

        TenantContext::setById(self::TENANT_ID);

        // User — satisfies FK on course_discussions.user_id.
        $this->userId = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'CD User',
            'first_name' => 'CD',
            'last_name'  => 'User',
            'email'      => 'cd.user.' . uniqid('', true) . '@example.test',
            'status'     => 'active',
            'role'       => 'member',
            'is_approved'=> 1,
            'balance'    => 0,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);

        // Author — satisfies FK on courses.author_user_id.
        $authorId = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'CD Author',
            'email'      => 'cd.author.' . uniqid('', true) . '@example.test',
            'status'     => 'active',
            'role'       => 'member',
            'is_approved'=> 1,
            'balance'    => 0,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);

        // Course row.
        $this->courseId = DB::table('courses')->insertGetId([
            'tenant_id'         => self::TENANT_ID,
            'author_user_id'    => $authorId,
            'title'             => 'CD Test Course',
            'slug'              => 'cd-test-course-99401-' . uniqid('', true),
            'status'            => 'published',
            'moderation_status' => 'approved',
            'level'             => 'beginner',
            'visibility'        => 'public',
            'enrollment_type'   => 'self_paced',
            'created_at'        => '2024-01-01 00:00:00',
            'updated_at'        => '2024-01-01 00:00:00',
        ]);

        // Lesson row (course_discussions.lesson_id is nullable, but we use a real one).
        $this->lessonId = DB::table('course_lessons')->insertGetId([
            'tenant_id'    => self::TENANT_ID,
            'course_id'    => $this->courseId,
            'title'        => 'Lesson 1',
            'content_type' => 'text',
            'position'     => 0,
            'created_at'   => '2024-01-01 00:00:00',
            'updated_at'   => '2024-01-01 00:00:00',
        ]);
    }

    // ── create() ──────────────────────────────────────────────────────────────

    public function test_create_persists_discussion_with_correct_fields(): void
    {
        $disc = CourseDiscussionService::create(
            $this->courseId,
            $this->lessonId,
            $this->userId,
            'Hello world'
        );

        $this->assertNotNull($disc->id);

        $row = DB::table('course_discussions')->where('id', $disc->id)->first();
        $this->assertNotNull($row);
        $this->assertSame($this->courseId, (int) $row->course_id);
        $this->assertSame($this->lessonId, (int) $row->lesson_id);
        $this->assertSame($this->userId,   (int) $row->user_id);
        $this->assertSame('Hello world',   $row->body);
        $this->assertSame('visible',       $row->status);
        $this->assertNull($row->parent_id);
    }

    public function test_create_sets_tenant_id_from_tenant_context(): void
    {
        $disc = CourseDiscussionService::create($this->courseId, $this->lessonId, $this->userId, 'Scoped');

        $row = DB::table('course_discussions')->where('id', $disc->id)->first();
        $this->assertSame(self::TENANT_ID, (int) $row->tenant_id);
    }

    public function test_create_reply_stores_parent_id_when_parent_belongs_to_same_lesson(): void
    {
        $parent = CourseDiscussionService::create(
            $this->courseId, $this->lessonId, $this->userId, 'Parent post'
        );

        $reply = CourseDiscussionService::create(
            $this->courseId, $this->lessonId, $this->userId, 'Reply post',
            parentId: $parent->id
        );

        $this->assertSame($parent->id, (int) $reply->parent_id);
    }

    public function test_create_nullifies_parent_when_parent_belongs_to_different_lesson(): void
    {
        // Create a parent in lessonId.
        $parent = CourseDiscussionService::create(
            $this->courseId, $this->lessonId, $this->userId, 'Wrong-lesson parent'
        );

        // A second lesson for the same course.
        $otherLessonId = DB::table('course_lessons')->insertGetId([
            'tenant_id'    => self::TENANT_ID,
            'course_id'    => $this->courseId,
            'title'        => 'Lesson 2',
            'content_type' => 'text',
            'position'     => 1,
            'created_at'   => '2024-01-01 00:00:00',
            'updated_at'   => '2024-01-01 00:00:00',
        ]);

        // Attempt to use the parent from lessonId as the parent for otherLessonId — must be nullified.
        $reply = CourseDiscussionService::create(
            $this->courseId, $otherLessonId, $this->userId, 'Orphan reply',
            parentId: $parent->id
        );

        $this->assertNull($reply->parent_id, 'parent_id from a different lesson must be cleared');
    }

    // ── listForLesson() ───────────────────────────────────────────────────────

    public function test_listForLesson_returns_only_visible_top_level_posts(): void
    {
        CourseDiscussionService::create($this->courseId, $this->lessonId, $this->userId, 'Visible post');

        $hidden = CourseDiscussionService::create($this->courseId, $this->lessonId, $this->userId, 'Hidden post');
        CourseDiscussionService::setStatus($hidden->id, 'hidden');

        $list = CourseDiscussionService::listForLesson($this->courseId, $this->lessonId);

        $this->assertNotEmpty($list);
        foreach ($list as $item) {
            $this->assertSame('visible', $item['status']);
        }
    }

    public function test_listForLesson_attaches_visible_replies(): void
    {
        $parent = CourseDiscussionService::create(
            $this->courseId, $this->lessonId, $this->userId, 'Parent'
        );
        CourseDiscussionService::create(
            $this->courseId, $this->lessonId, $this->userId, 'Reply A',
            parentId: $parent->id
        );
        CourseDiscussionService::create(
            $this->courseId, $this->lessonId, $this->userId, 'Reply B',
            parentId: $parent->id
        );

        $list = CourseDiscussionService::listForLesson($this->courseId, $this->lessonId);

        // Find our parent entry.
        $found = null;
        foreach ($list as $item) {
            if ($item['id'] === $parent->id) {
                $found = $item;
                break;
            }
        }

        $this->assertNotNull($found);
        $this->assertCount(2, $found['replies']);
    }

    public function test_listForLesson_does_not_include_other_course_posts(): void
    {
        // Add a post to our course/lesson so the list is non-empty.
        CourseDiscussionService::create($this->courseId, $this->lessonId, $this->userId, 'Own post');

        // A second course with a post that shares the same lesson_id.
        $otherCourseId = DB::table('courses')->insertGetId([
            'tenant_id'         => self::TENANT_ID,
            'author_user_id'    => $this->userId,
            'title'             => 'Other CD Course',
            'slug'              => 'other-cd-course-99401-' . uniqid('', false),
            'status'            => 'published',
            'moderation_status' => 'approved',
            'level'             => 'beginner',
            'visibility'        => 'public',
            'enrollment_type'   => 'self_paced',
            'created_at'        => '2024-01-01 00:00:00',
            'updated_at'        => '2024-01-01 00:00:00',
        ]);

        DB::table('course_discussions')->insert([
            'tenant_id' => self::TENANT_ID,
            'course_id' => $otherCourseId,
            'lesson_id' => $this->lessonId,
            'user_id'   => $this->userId,
            'body'      => 'Cross-course interloper',
            'status'    => 'visible',
            'created_at'=> '2024-01-01 00:00:00',
            'updated_at'=> '2024-01-01 00:00:00',
        ]);

        $list = CourseDiscussionService::listForLesson($this->courseId, $this->lessonId);

        // The list must be non-empty (we added 'Own post') and must only contain
        // posts from $this->courseId — not from the other course.
        $this->assertNotEmpty($list, 'Should have at least one post from the target course');
        foreach ($list as $item) {
            $this->assertSame($this->courseId, (int) $item['course_id']);
        }
    }

    // ── setStatus() ───────────────────────────────────────────────────────────

    public function test_setStatus_changes_status_to_hidden(): void
    {
        $disc = CourseDiscussionService::create($this->courseId, $this->lessonId, $this->userId, 'Moderated');

        $result = CourseDiscussionService::setStatus($disc->id, 'hidden');

        $this->assertTrue($result);
        $row = DB::table('course_discussions')->where('id', $disc->id)->first();
        $this->assertSame('hidden', $row->status);
    }

    public function test_setStatus_returns_false_for_nonexistent_id(): void
    {
        $result = CourseDiscussionService::setStatus(999999999, 'hidden');

        $this->assertFalse($result);
    }

    public function test_setStatus_can_set_flagged_status(): void
    {
        $disc = CourseDiscussionService::create($this->courseId, $this->lessonId, $this->userId, 'Flagged');

        CourseDiscussionService::setStatus($disc->id, 'flagged');

        $row = DB::table('course_discussions')->where('id', $disc->id)->first();
        $this->assertSame('flagged', $row->status);
    }

    // ── delete() ──────────────────────────────────────────────────────────────

    public function test_delete_removes_the_comment_from_db(): void
    {
        $disc = CourseDiscussionService::create($this->courseId, $this->lessonId, $this->userId, 'To Delete');
        $id   = $disc->id;

        $result = CourseDiscussionService::delete($id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('course_discussions', ['id' => $id]);
    }

    public function test_delete_cascades_to_direct_replies(): void
    {
        $parent = CourseDiscussionService::create($this->courseId, $this->lessonId, $this->userId, 'Parent');
        $reply1 = CourseDiscussionService::create($this->courseId, $this->lessonId, $this->userId, 'R1', parentId: $parent->id);
        $reply2 = CourseDiscussionService::create($this->courseId, $this->lessonId, $this->userId, 'R2', parentId: $parent->id);

        CourseDiscussionService::delete($parent->id);

        $this->assertDatabaseMissing('course_discussions', ['id' => $reply1->id]);
        $this->assertDatabaseMissing('course_discussions', ['id' => $reply2->id]);
    }

    public function test_delete_returns_false_for_nonexistent_id(): void
    {
        $result = CourseDiscussionService::delete(999999999);

        $this->assertFalse($result);
    }

    // ── find() ────────────────────────────────────────────────────────────────

    public function test_find_returns_the_correct_discussion(): void
    {
        $disc = CourseDiscussionService::create($this->courseId, $this->lessonId, $this->userId, 'Find Me');

        $found = CourseDiscussionService::find($disc->id);

        $this->assertNotNull($found);
        $this->assertSame($disc->id, $found->id);
        $this->assertSame('Find Me', $found->body);
    }

    public function test_find_returns_null_for_nonexistent_id(): void
    {
        $result = CourseDiscussionService::find(999999999);

        $this->assertNull($result);
    }
}
