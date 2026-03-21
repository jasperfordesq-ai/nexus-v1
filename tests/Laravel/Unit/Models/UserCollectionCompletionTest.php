<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\UserCollectionCompletion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class UserCollectionCompletionTest extends TestCase
{
    private UserCollectionCompletion $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new UserCollectionCompletion();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('user_collection_completions', $this->model->getTable());
    }

    public function test_timestamps_disabled(): void
    {
        $this->assertFalse($this->model->usesTimestamps());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = ['user_id', 'collection_id', 'bonus_claimed'];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('boolean', $casts['bonus_claimed']);
    }

    public function test_does_not_use_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(UserCollectionCompletion::class);
        $this->assertNotContains(\App\Models\Concerns\HasTenantScope::class, $traits);
    }

    public function test_user_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->user());
    }

    public function test_collection_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->collection());
    }
}
