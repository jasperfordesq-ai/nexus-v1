<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\GroupTagService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class GroupTagServiceTest extends TestCase
{
    // ── getAll ───────────────────────────────────────────────────────

    public function test_getAll_scopes_to_tenant_and_returns_array(): void
    {
        DB::shouldReceive('table')->with('group_tags')->andReturnSelf();
        DB::shouldReceive('where')->with('tenant_id', $this->testTenantId)->andReturnSelf();
        DB::shouldReceive('orderBy')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([
            (object) ['id' => 1, 'name' => 'Art', 'slug' => 'art', 'usage_count' => 5],
        ]));

        $result = GroupTagService::getAll();
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('Art', $result[0]['name']);
    }

    public function test_getAll_caps_limit_at_500(): void
    {
        DB::shouldReceive('table')->with('group_tags')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('orderBy')->andReturnSelf();
        DB::shouldReceive('limit')->with(500)->once()->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        GroupTagService::getAll(['limit' => 999]);
        $this->assertTrue(true);
    }

    // ── getPopular ───────────────────────────────────────────────────

    public function test_getPopular_filters_by_positive_usage_count(): void
    {
        DB::shouldReceive('table')->with('group_tags')->andReturnSelf();
        DB::shouldReceive('where')->with('tenant_id', $this->testTenantId)->andReturnSelf();
        DB::shouldReceive('where')->with('usage_count', '>', 0)->andReturnSelf();
        DB::shouldReceive('orderByDesc')->with('usage_count')->andReturnSelf();
        DB::shouldReceive('limit')->with(20)->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = GroupTagService::getPopular();
        $this->assertSame([], $result);
    }

    // ── create ───────────────────────────────────────────────────────

    public function test_create_returns_existing_tag_when_slug_exists(): void
    {
        $existing = (object) ['id' => 7, 'name' => 'Music', 'slug' => 'music'];

        DB::shouldReceive('table')->with('group_tags')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->once()->andReturn($existing);

        $result = GroupTagService::create('Music');
        $this->assertSame(7, $result['id']);
        $this->assertSame('music', $result['slug']);
    }

    public function test_create_inserts_new_tag_and_returns_payload(): void
    {
        DB::shouldReceive('table')->with('group_tags')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->once()->andReturn(null);
        DB::shouldReceive('insertGetId')->once()->andReturn(99);

        $result = GroupTagService::create('New Tag', '#ff0000');

        $this->assertSame(99, $result['id']);
        $this->assertSame('New Tag', $result['name']);
        $this->assertSame('new-tag', $result['slug']);
        $this->assertSame('#ff0000', $result['color']);
        $this->assertSame(0, $result['usage_count']);
    }

    // ── delete: tenant scoping ───────────────────────────────────────

    public function test_delete_scopes_by_tenant(): void
    {
        // Assignments cleanup (no tenant scope on join table)
        DB::shouldReceive('table')->with('group_tag_assignments')->once()->andReturnSelf();
        DB::shouldReceive('where')->with('tag_id', 5)->once()->andReturnSelf();
        DB::shouldReceive('delete')->once()->andReturn(1);

        // Main delete with tenant scope
        DB::shouldReceive('table')->with('group_tags')->once()->andReturnSelf();
        DB::shouldReceive('where')->with('id', 5)->once()->andReturnSelf();
        DB::shouldReceive('where')->with('tenant_id', $this->testTenantId)->once()->andReturnSelf();
        DB::shouldReceive('delete')->once()->andReturn(1);

        $this->assertTrue(GroupTagService::delete(5));
    }

    public function test_delete_returns_false_when_no_rows(): void
    {
        DB::shouldReceive('table')->with('group_tag_assignments')->once()->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('delete')->andReturn(0, 0);
        DB::shouldReceive('table')->with('group_tags')->once()->andReturnSelf();

        $this->assertFalse(GroupTagService::delete(999));
    }

    // ── suggest ──────────────────────────────────────────────────────

    public function test_suggest_uses_like_pattern(): void
    {
        DB::shouldReceive('table')->with('group_tags')->andReturnSelf();
        DB::shouldReceive('where')->with('tenant_id', $this->testTenantId)->andReturnSelf();
        DB::shouldReceive('where')->with('name', 'LIKE', '%art%')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('limit')->with(10)->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = GroupTagService::suggest('art');
        $this->assertSame([], $result);
    }
}
