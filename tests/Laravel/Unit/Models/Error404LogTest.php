<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Error404Log;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class Error404LogTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new Error404Log();
        $this->assertEquals('error_404_log', $model->getTable());
    }

    public function test_timestamps_are_disabled(): void
    {
        $model = new Error404Log();
        $this->assertFalse($model->usesTimestamps());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new Error404Log();
        $expected = [
            'url', 'referer', 'user_agent', 'ip_address',
            'user_id', 'hit_count', 'first_seen_at', 'last_seen_at',
            'resolved', 'redirect_id', 'notes',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new Error404Log();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['hit_count']);
        $this->assertEquals('boolean', $casts['resolved']);
        $this->assertEquals('datetime', $casts['first_seen_at']);
        $this->assertEquals('datetime', $casts['last_seen_at']);
    }

    public function test_does_not_use_has_tenant_scope_trait(): void
    {
        $this->assertNotContains(
            HasTenantScope::class,
            class_uses_recursive(Error404Log::class)
        );
    }

    public function test_user_relationship_returns_belongs_to(): void
    {
        $model = new Error404Log();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }
}
