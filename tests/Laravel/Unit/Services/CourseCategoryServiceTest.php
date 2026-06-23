<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\CourseCategoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class CourseCategoryServiceTest extends TestCase
{
    use DatabaseTransactions;

    private int $tenantId = 99300;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tenants')->insertOrIgnore([
            'id'                => $this->tenantId,
            'name'              => 'Test CourseCat',
            'slug'              => 'test-99300',
            'is_active'         => true,
            'depth'             => 0,
            'allows_subtenants' => false,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        TenantContext::setById($this->tenantId);
    }

    // ── all() ────────────────────────────────────────────────────────

    public function test_all_returns_empty_array_when_no_categories_exist(): void
    {
        $result = CourseCategoryService::all();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_all_returns_categories_ordered_by_position_then_name(): void
    {
        DB::table('course_categories')->insert([
            ['tenant_id' => $this->tenantId, 'name' => 'Zebra',  'slug' => 'zebra-99300',  'position' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $this->tenantId, 'name' => 'Apple',  'slug' => 'apple-99300',  'position' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $this->tenantId, 'name' => 'Mango',  'slug' => 'mango-99300',  'position' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $result = CourseCategoryService::all();

        $this->assertCount(3, $result);
        // position 0 first, then position 1 alphabetically
        $this->assertSame('Mango', $result[0]['name']);
        $this->assertSame('Apple', $result[1]['name']);
        $this->assertSame('Zebra', $result[2]['name']);
    }

    public function test_all_does_not_return_other_tenant_categories(): void
    {
        $otherTenant = 99301;
        DB::table('tenants')->insertOrIgnore([
            'id' => $otherTenant, 'name' => 'Other', 'slug' => 'test-99301',
            'is_active' => true, 'depth' => 0, 'allows_subtenants' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('course_categories')->insert([
            'tenant_id' => $otherTenant, 'name' => 'Hidden', 'slug' => 'hidden-99301',
            'position' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = CourseCategoryService::all();

        $this->assertEmpty($result);
    }

    // ── create() ─────────────────────────────────────────────────────

    public function test_create_persists_category_with_correct_fields(): void
    {
        $cat = CourseCategoryService::create([
            'name'        => 'Programming',
            'slug'        => 'programming-99300',
            'description' => 'All things code',
            'icon'        => 'code',
            'position'    => 5,
        ]);

        $this->assertSame('Programming', $cat->name);
        $this->assertSame('programming-99300', $cat->slug);
        $this->assertSame('All things code', $cat->description);
        $this->assertSame('code', $cat->icon);
        $this->assertSame(5, $cat->position);

        $this->assertDatabaseHas('course_categories', [
            'tenant_id' => $this->tenantId,
            'name'      => 'Programming',
            'slug'      => 'programming-99300',
        ]);
    }

    public function test_create_generates_slug_from_name_when_slug_not_provided(): void
    {
        $cat = CourseCategoryService::create(['name' => 'Web Design']);

        $this->assertSame('web-design', $cat->slug);
    }

    public function test_create_deduplicates_slug_when_it_already_exists(): void
    {
        CourseCategoryService::create(['name' => 'Dup', 'slug' => 'dup-99300']);
        $second = CourseCategoryService::create(['name' => 'Dup', 'slug' => 'dup-99300']);

        $this->assertSame('dup-99300-2', $second->slug);
    }

    public function test_create_sets_position_to_zero_by_default(): void
    {
        $cat = CourseCategoryService::create(['name' => 'NoPos', 'slug' => 'no-pos-99300']);

        $this->assertSame(0, $cat->position);
    }

    // ── update() ─────────────────────────────────────────────────────

    public function test_update_returns_null_for_nonexistent_id(): void
    {
        $result = CourseCategoryService::update(999999, ['name' => 'Ghost']);

        $this->assertNull($result);
    }

    public function test_update_changes_mutable_fields(): void
    {
        $cat = CourseCategoryService::create([
            'name' => 'Old Name', 'slug' => 'old-name-99300', 'position' => 1,
        ]);

        $updated = CourseCategoryService::update($cat->id, [
            'name'        => 'New Name',
            'description' => 'Updated desc',
            'icon'        => 'star',
            'position'    => 10,
        ]);

        $this->assertNotNull($updated);
        $this->assertSame('New Name', $updated->name);
        $this->assertSame('Updated desc', $updated->description);
        $this->assertSame('star', $updated->icon);
        $this->assertSame(10, $updated->position);
    }

    public function test_update_does_not_clear_fields_when_key_is_absent(): void
    {
        $cat = CourseCategoryService::create([
            'name' => 'Keep Me', 'slug' => 'keep-me-99300', 'icon' => 'heart',
        ]);

        // Only update name; icon should remain
        $updated = CourseCategoryService::update($cat->id, ['name' => 'Keep Me 2']);

        $this->assertSame('heart', $updated->icon);
    }

    // ── delete() ─────────────────────────────────────────────────────

    public function test_delete_returns_false_for_nonexistent_id(): void
    {
        $result = CourseCategoryService::delete(999999);

        $this->assertFalse($result);
    }

    public function test_delete_removes_the_category(): void
    {
        $cat = CourseCategoryService::create(['name' => 'ToDelete', 'slug' => 'to-delete-99300']);

        $result = CourseCategoryService::delete($cat->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('course_categories', ['id' => $cat->id]);
    }

    public function test_delete_nullifies_category_id_on_linked_courses(): void
    {
        $cat = CourseCategoryService::create(['name' => 'LinkedCat', 'slug' => 'linked-cat-99300']);

        // Insert a real user to satisfy the courses.author_user_id FK
        $userId = DB::table('users')->insertGetId([
            'tenant_id'  => $this->tenantId,
            'name'       => 'Cat Test Author',
            'email'      => 'catauthor99300@example.com',
            'password'   => bcrypt('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $courseId = DB::table('courses')->insertGetId([
            'tenant_id'        => $this->tenantId,
            'author_user_id'   => $userId,
            'category_id'      => $cat->id,
            'title'            => 'Cat Course',
            'slug'             => 'cat-course-99300',
            'status'           => 'draft',
            'moderation_status'=> 'pending',
            'level'            => 'beginner',
            'visibility'       => 'members',
            'enrollment_type'  => 'self_paced',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        CourseCategoryService::delete($cat->id);

        $course = DB::table('courses')->where('id', $courseId)->first();
        $this->assertNull($course->category_id);
    }
}
