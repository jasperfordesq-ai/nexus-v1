<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\ResourceItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class ResourceItemTest extends TestCase
{
    private ResourceItem $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new ResourceItem();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('resources', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'user_id', 'title', 'description', 'file_path',
            'file_type', 'file_size', 'category_id', 'downloads',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['file_size']);
        $this->assertEquals('integer', $casts['downloads']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(ResourceItem::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_user_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->user());
    }

    public function test_category_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->category());
    }
}
