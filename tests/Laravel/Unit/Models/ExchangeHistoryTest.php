<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\ExchangeHistory;
use App\Models\ExchangeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class ExchangeHistoryTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new ExchangeHistory();
        $this->assertEquals('exchange_history', $model->getTable());
    }

    public function test_timestamps_are_disabled(): void
    {
        $model = new ExchangeHistory();
        $this->assertFalse($model->usesTimestamps());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new ExchangeHistory();
        $expected = [
            'exchange_id', 'action', 'actor_id', 'actor_role',
            'old_status', 'new_status', 'notes', 'created_at',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new ExchangeHistory();
        $casts = $model->getCasts();
        $this->assertEquals('datetime', $casts['created_at']);
    }

    public function test_does_not_use_has_tenant_scope_trait(): void
    {
        $this->assertNotContains(
            HasTenantScope::class,
            class_uses_recursive(ExchangeHistory::class)
        );
    }

    public function test_exchange_relationship_returns_belongs_to(): void
    {
        $model = new ExchangeHistory();
        $this->assertInstanceOf(BelongsTo::class, $model->exchange());
        $this->assertEquals('exchange_id', $model->exchange()->getForeignKeyName());
    }

    public function test_actor_relationship_returns_belongs_to(): void
    {
        $model = new ExchangeHistory();
        $this->assertInstanceOf(BelongsTo::class, $model->actor());
        $this->assertEquals('actor_id', $model->actor()->getForeignKeyName());
    }
}
