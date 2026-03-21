<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\SearchLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class SearchLogTest extends TestCase
{
    private SearchLog $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new SearchLog();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('search_logs', $this->model->getTable());
    }

    public function test_timestamps_disabled(): void
    {
        $this->assertFalse($this->model->usesTimestamps());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = ['user_id', 'query', 'search_type', 'result_count', 'filters'];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['user_id']);
        $this->assertEquals('integer', $casts['result_count']);
        $this->assertEquals('array', $casts['filters']);
        $this->assertEquals('datetime', $casts['created_at']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(SearchLog::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_user_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->user());
    }
}
