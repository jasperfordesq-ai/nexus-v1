<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\HelpArticle;
use Illuminate\Database\Eloquent\Builder;
use Tests\Laravel\TestCase;

class HelpArticleTest extends TestCase
{
    private HelpArticle $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new HelpArticle();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('help_articles', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = ['title', 'slug', 'content', 'module_tag', 'is_public', 'view_count'];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('boolean', $casts['is_public']);
        $this->assertEquals('integer', $casts['view_count']);
    }

    public function test_does_not_use_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(HelpArticle::class);
        $this->assertNotContains(\App\Models\Concerns\HasTenantScope::class, $traits);
    }

    public function test_scope_public(): void
    {
        $builder = HelpArticle::query()->public();
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function test_scope_for_modules(): void
    {
        $builder = HelpArticle::query()->forModules(['events', 'feed']);
        $this->assertInstanceOf(Builder::class, $builder);
    }
}
