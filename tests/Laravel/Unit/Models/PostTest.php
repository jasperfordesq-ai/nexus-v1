<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class PostTest extends TestCase
{
    private Post $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new Post();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('posts', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'author_id', 'title', 'slug', 'excerpt', 'content',
            'featured_image', 'status', 'category_id', 'content_json', 'html_render',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('array', $casts['content_json']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(Post::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_author_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->author());
    }

    public function test_category_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->category());
    }

    public function test_scope_published(): void
    {
        $builder = Post::query()->published();
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function test_scope_draft(): void
    {
        $builder = Post::query()->draft();
        $this->assertInstanceOf(Builder::class, $builder);
    }
}
