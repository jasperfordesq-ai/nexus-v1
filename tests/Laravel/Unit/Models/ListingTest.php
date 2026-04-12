<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class ListingTest extends TestCase
{
    private Listing $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new Listing();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('listings', $this->model->getTable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('array', $casts['sdg_goals']);
        $this->assertEquals('float', $casts['latitude']);
        $this->assertEquals('float', $casts['longitude']);
        $this->assertEquals('decimal:2', $casts['price']);
        $this->assertEquals('decimal:2', $casts['hours_estimate']);
        $this->assertEquals('boolean', $casts['direct_messaging_disabled']);
        $this->assertEquals('boolean', $casts['exchange_workflow_required']);
        $this->assertEquals('boolean', $casts['is_featured']);
        $this->assertEquals('datetime', $casts['renewed_at']);
        $this->assertEquals('datetime', $casts['featured_until']);
        $this->assertEquals('datetime', $casts['reviewed_at']);
        $this->assertEquals('integer', $casts['view_count']);
        $this->assertEquals('integer', $casts['contact_count']);
        $this->assertEquals('integer', $casts['save_count']);
        $this->assertEquals('integer', $casts['renewal_count']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(Listing::class);
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

    public function test_saved_by_users_relationship(): void
    {
        $this->assertInstanceOf(BelongsToMany::class, $this->model->savedByUsers());
    }

    public function test_skill_tags_relationship(): void
    {
        $this->assertInstanceOf(HasMany::class, $this->model->skillTags());
    }

    public function test_scope_active(): void
    {
        $builder = Listing::query()->active();
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function test_scope_of_type(): void
    {
        $builder = Listing::query()->ofType('offer');
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function test_scope_featured(): void
    {
        $builder = Listing::query()->featured();
        $this->assertInstanceOf(Builder::class, $builder);
    }
}
