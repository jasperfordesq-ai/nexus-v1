<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Tests\Laravel\TestCase;

class PageTest extends TestCase
{
    private Page $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new Page();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('pages', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'title', 'slug', 'content', 'is_published',
            'publish_at', 'show_in_menu', 'menu_location', 'sort_order',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('boolean', $casts['is_published']);
        $this->assertEquals('boolean', $casts['show_in_menu']);
        $this->assertEquals('datetime', $casts['publish_at']);
        $this->assertEquals('integer', $casts['sort_order']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(Page::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_scope_published(): void
    {
        $builder = Page::query()->published();
        $this->assertInstanceOf(Builder::class, $builder);
    }
}
