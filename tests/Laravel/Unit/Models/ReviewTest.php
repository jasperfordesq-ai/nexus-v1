<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class ReviewTest extends TestCase
{
    private Review $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new Review();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('reviews', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'reviewer_id', 'receiver_id', 'transaction_id',
            'group_id', 'rating', 'comment', 'status',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['rating']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(Review::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_reviewer_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->reviewer());
    }

    public function test_receiver_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->receiver());
    }

    public function test_transaction_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->transaction());
    }

    public function test_group_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->group());
    }

    public function test_scope_for_receiver(): void
    {
        $builder = Review::query()->forReceiver(1);
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function test_scope_in_group(): void
    {
        $builder = Review::query()->inGroup(1);
        $this->assertInstanceOf(Builder::class, $builder);
    }
}
