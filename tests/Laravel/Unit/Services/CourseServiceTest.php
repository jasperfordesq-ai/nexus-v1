<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\CourseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * CourseServiceTest
 *
 * Tests CourseService CRUD, browse filters, publish/unpublish, and
 * tenant-scoping. Uses tenant 2 (hour-timebank) — it already exists in the
 * test database — and uniqid-suffixed slugs to avoid collisions.
 * DatabaseTransactions rolls everything back after each test.
 */
class CourseServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    private int $authorId;
    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();

        // CourseObserver dispatches ReindexEmbeddingJob on every create/update.
        // When the queue driver is 'sync', Laravel fires the Queue::before/after
        // hooks registered in AppServiceProvider, which call TenantContext::reset()
        // and wipe the tenant context mid-test.  Queue::fake() intercepts all
        // dispatches so no job runs and the context stays intact.
        Queue::fake();

        TenantContext::setById(self::TENANT_ID);

        // Author — satisfies FK on courses.author_user_id.
        $this->authorId = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'CS Author',
            'first_name' => 'CS',
            'last_name'  => 'Author',
            'email'      => 'cs.author.' . uniqid('', true) . '@example.test',
            'status'     => 'active',
            'role'       => 'member',
            'is_approved'=> 1,
            'balance'    => 0,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);

        // Category — satisfies FK on courses.category_id.
        $this->categoryId = DB::table('course_categories')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'CS Test Category',
            'slug'       => 'cs-test-cat-' . uniqid('', true),
            'position'   => 0,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    /**
     * Minimal valid payload for CourseService::create().
     * The slug is always unique per call to prevent cross-test slug collisions.
     */
    private function minimalPayload(array $overrides = []): array
    {
        return array_merge([
            'title'       => 'Test Course ' . uniqid('', true),
            'slug'        => 'cs-test-' . uniqid('', false),
            'category_id' => $this->categoryId,
            'level'       => 'beginner',
            'visibility'  => 'members',
        ], $overrides);
    }

    /**
     * Create and immediately publish a course (auto-approve, no moderation).
     */
    private function createPublished(array $overrides = []): \App\Models\Course
    {
        $course = CourseService::create($this->authorId, $this->minimalPayload($overrides));
        CourseService::publish($course, autoApprove: true);
        return $course->fresh();
    }

    // ── create() ──────────────────────────────────────────────────────────────

    public function test_create_persists_course_with_correct_tenant_and_author(): void
    {
        $payload = $this->minimalPayload(['title' => 'My First Course']);
        $course  = CourseService::create($this->authorId, $payload);

        $this->assertNotNull($course->id);

        $row = DB::table('courses')->where('id', $course->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(self::TENANT_ID, (int) $row->tenant_id);
        $this->assertSame($this->authorId, (int) $row->author_user_id);
        $this->assertSame('My First Course', $row->title);
    }

    public function test_create_sets_status_draft_and_moderation_pending(): void
    {
        $course = CourseService::create($this->authorId, $this->minimalPayload());

        $this->assertSame('draft', $course->status);
        $this->assertSame('pending', $course->moderation_status);
    }

    public function test_create_generates_slug_from_title_when_not_supplied(): void
    {
        $payload = $this->minimalPayload();
        unset($payload['slug']);
        $payload['title'] = 'Intro to Testing ' . uniqid('', true);
        $course = CourseService::create($this->authorId, $payload);

        $this->assertStringStartsWith('intro-to-testing', $course->slug);
    }

    public function test_create_enforces_unique_slug_within_tenant(): void
    {
        $base    = 'dup-slug-' . uniqid('', false);
        $payload = $this->minimalPayload(['slug' => $base]);

        $first  = CourseService::create($this->authorId, $payload);
        $second = CourseService::create($this->authorId, $payload);

        $this->assertSame($base,       $first->slug);
        $this->assertSame($base . '-2', $second->slug);
        $this->assertNotSame($first->slug, $second->slug);
    }

    public function test_create_clamps_invalid_level_to_beginner(): void
    {
        $course = CourseService::create($this->authorId, $this->minimalPayload(['level' => 'expert']));

        $this->assertSame('beginner', $course->level);
    }

    public function test_create_clamps_negative_credit_cost_to_zero(): void
    {
        $course = CourseService::create($this->authorId, $this->minimalPayload(['credit_cost' => -5]));

        // cast is decimal:2 so value comes back as string "0.00"
        $this->assertSame('0.00', $course->credit_cost);
    }

    public function test_create_stores_credit_cost_rounded_to_two_decimals(): void
    {
        $course = CourseService::create($this->authorId, $this->minimalPayload(['credit_cost' => 3.1415]));

        $this->assertSame('3.14', $course->credit_cost);
    }

    // ── update() ──────────────────────────────────────────────────────────────

    public function test_update_changes_mutable_fields(): void
    {
        $course  = CourseService::create($this->authorId, $this->minimalPayload());
        $updated = CourseService::update($course, [
            'title'       => 'Updated Title',
            'level'       => 'advanced',
            'visibility'  => 'public',
            'credit_cost' => 3.5,
            'summary'     => 'A new summary',
        ]);

        $this->assertSame('Updated Title', $updated->title);
        $this->assertSame('advanced',      $updated->level);
        $this->assertSame('public',        $updated->visibility);
        $this->assertSame('3.50',          $updated->credit_cost);
        $this->assertSame('A new summary', $updated->summary);
    }

    public function test_update_preserves_fields_absent_from_payload(): void
    {
        $course = CourseService::create($this->authorId, $this->minimalPayload([
            'level' => 'intermediate',
        ]));

        // Update only the title; level should remain intermediate.
        CourseService::update($course, ['title' => 'New Title Only']);

        $this->assertSame('intermediate', $course->level);
    }

    public function test_update_clamps_invalid_level_enum_to_current_value(): void
    {
        $course = CourseService::create($this->authorId, $this->minimalPayload(['level' => 'advanced']));

        CourseService::update($course, ['level' => 'nonsense']);

        $this->assertSame('advanced', $course->level);
    }

    // ── publish() / unpublish() ───────────────────────────────────────────────

    public function test_publish_sets_status_published_and_moderation_approved_when_auto_approve(): void
    {
        $course = CourseService::create($this->authorId, $this->minimalPayload());
        CourseService::publish($course, autoApprove: true);

        $this->assertSame('published', $course->status);
        $this->assertSame('approved',  $course->moderation_status);
    }

    public function test_publish_sets_published_at_on_first_publish(): void
    {
        $course = CourseService::create($this->authorId, $this->minimalPayload());
        $this->assertNull($course->published_at);

        CourseService::publish($course, autoApprove: true);

        $this->assertNotNull($course->published_at);
    }

    public function test_publish_does_not_overwrite_published_at_on_republish(): void
    {
        $course = CourseService::create($this->authorId, $this->minimalPayload());
        CourseService::publish($course, autoApprove: true);
        $firstPublishedAt = $course->published_at->timestamp;

        // Unpublish and re-publish — published_at must NOT change.
        CourseService::unpublish($course);
        CourseService::publish($course, autoApprove: true);

        $this->assertNotNull($course->published_at);
        $this->assertSame($firstPublishedAt, $course->published_at->timestamp);
    }

    public function test_unpublish_reverts_status_to_draft(): void
    {
        $course = CourseService::create($this->authorId, $this->minimalPayload());
        CourseService::publish($course, autoApprove: true);
        CourseService::unpublish($course);

        $this->assertSame('draft', $course->status);
    }

    // ── browse() ──────────────────────────────────────────────────────────────

    public function test_browse_returns_only_published_approved_public_courses(): void
    {
        // Draft — must NOT appear.
        CourseService::create($this->authorId, $this->minimalPayload(['visibility' => 'public']));

        // Published + approved public — must appear.
        $published = $this->createPublished(['visibility' => 'public']);

        $result = CourseService::browse(['include_member_only' => false]);

        $ids = array_column($result['items'], 'id');
        $this->assertContains($published->id, $ids);

        // Every returned item must be published + approved.
        foreach ($result['items'] as $item) {
            $this->assertSame('published', $item['status']);
            $this->assertSame('approved',  $item['moderation_status']);
        }
    }

    public function test_browse_restricts_to_public_visibility_when_include_member_only_is_false(): void
    {
        $pub = $this->createPublished(['visibility' => 'public']);
        $mem = $this->createPublished(['visibility' => 'members']);

        $result = CourseService::browse(['include_member_only' => false]);

        $ids = array_column($result['items'], 'id');
        $this->assertContains($pub->id, $ids, 'Public course should be included');
        $this->assertNotContains($mem->id, $ids, 'Members-only course should be excluded');
    }

    public function test_browse_includes_members_visibility_when_include_member_only_is_true(): void
    {
        $mem = $this->createPublished(['visibility' => 'members']);

        $result = CourseService::browse(['include_member_only' => true]);

        $ids = array_column($result['items'], 'id');
        $this->assertContains($mem->id, $ids, 'Members-only course should appear when include_member_only=true');
    }

    public function test_browse_filters_by_level(): void
    {
        $begCourse = $this->createPublished(['visibility' => 'public', 'level' => 'beginner']);
        $advCourse = $this->createPublished(['visibility' => 'public', 'level' => 'advanced']);

        $result = CourseService::browse(['level' => 'beginner', 'include_member_only' => true]);

        $ids = array_column($result['items'], 'id');
        $this->assertContains($begCourse->id, $ids, 'Beginner course should be in results');
        $this->assertNotContains($advCourse->id, $ids, 'Advanced course should not be in beginner-filtered results');
    }

    public function test_browse_filters_by_category_id(): void
    {
        $otherCategoryId = DB::table('course_categories')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Other Test Category',
            'slug'       => 'other-test-cat-' . uniqid('', true),
            'position'   => 0,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);

        $rightCat  = $this->createPublished(['visibility' => 'public', 'category_id' => $this->categoryId]);
        $wrongCat  = $this->createPublished(['visibility' => 'public', 'category_id' => $otherCategoryId]);

        $result = CourseService::browse(['category_id' => $this->categoryId, 'include_member_only' => true]);

        $ids = array_column($result['items'], 'id');
        $this->assertContains($rightCat->id, $ids, 'Course in requested category should appear');
        $this->assertNotContains($wrongCat->id, $ids, 'Course in other category should be excluded');
    }

    public function test_browse_search_finds_course_by_title_substring(): void
    {
        $unique = 'csuniqtitle' . uniqid('', true);
        $found  = $this->createPublished(['visibility' => 'public', 'title' => $unique]);

        $result = CourseService::browse(['search' => substr($unique, 0, 18), 'include_member_only' => true]);

        $ids = array_column($result['items'], 'id');
        $this->assertContains($found->id, $ids, 'Search by title substring should return the course');
    }

    // ── authoredBy() ──────────────────────────────────────────────────────────

    public function test_authoredBy_returns_only_courses_for_given_user(): void
    {
        $otherAuthorId = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Other Author',
            'email'      => 'other.' . uniqid('', true) . '@example.test',
            'status'     => 'active',
            'role'       => 'member',
            'is_approved'=> 1,
            'balance'    => 0,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);

        $mine  = CourseService::create($this->authorId,   $this->minimalPayload(['title' => 'My Course']));
        $theirs = CourseService::create($otherAuthorId, $this->minimalPayload(['title' => 'Their Course']));

        $result = CourseService::authoredBy($this->authorId);

        $ids = array_column($result, 'id');
        $this->assertContains($mine->id, $ids, 'Own course should appear');
        $this->assertNotContains($theirs->id, $ids, 'Other author course should not appear');
    }

    // ── delete() ──────────────────────────────────────────────────────────────

    public function test_delete_removes_the_course_from_db(): void
    {
        $course = CourseService::create($this->authorId, $this->minimalPayload());
        $id     = $course->id;

        $result = CourseService::delete($course);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('courses', ['id' => $id]);
    }

    // ── findById() / findBySlug() ─────────────────────────────────────────────

    public function test_findById_returns_the_correct_course(): void
    {
        $course = CourseService::create($this->authorId, $this->minimalPayload());

        $found = CourseService::findById($course->id);

        $this->assertNotNull($found);
        $this->assertSame($course->id, $found->id);
    }

    public function test_findBySlug_returns_correct_course(): void
    {
        $slug   = 'cs-find-slug-' . uniqid('', false);
        $course = CourseService::create($this->authorId, $this->minimalPayload(['slug' => $slug]));

        $found = CourseService::findBySlug($slug);

        $this->assertNotNull($found);
        $this->assertSame($course->id, $found->id);
    }

    public function test_findBySlug_returns_null_for_nonexistent_slug(): void
    {
        $result = CourseService::findBySlug('does-not-exist-zzzzzzzzzz');

        $this->assertNull($result);
    }
}
