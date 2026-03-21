<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Connection;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class ConnectionTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new Connection();
        $this->assertEquals('connections', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new Connection();
        $expected = [
            'tenant_id', 'requester_id', 'receiver_id', 'status',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new Connection();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['requester_id']);
        $this->assertEquals('integer', $casts['receiver_id']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(Connection::class)
        );
    }

    public function test_requester_relationship_returns_belongs_to(): void
    {
        $model = new Connection();
        $this->assertInstanceOf(BelongsTo::class, $model->requester());
        $this->assertEquals('requester_id', $model->requester()->getForeignKeyName());
    }

    public function test_receiver_relationship_returns_belongs_to(): void
    {
        $model = new Connection();
        $this->assertInstanceOf(BelongsTo::class, $model->receiver());
        $this->assertEquals('receiver_id', $model->receiver()->getForeignKeyName());
    }
}
