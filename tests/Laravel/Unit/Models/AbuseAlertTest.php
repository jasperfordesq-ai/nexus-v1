<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\AbuseAlert;
use App\Models\Concerns\HasTenantScope;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class AbuseAlertTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new AbuseAlert();
        $this->assertEquals('abuse_alerts', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new AbuseAlert();
        $expected = [
            'tenant_id', 'alert_type', 'severity', 'user_id',
            'transaction_id', 'details', 'status',
            'resolved_by', 'resolved_at', 'resolution_notes',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new AbuseAlert();
        $casts = $model->getCasts();
        $this->assertEquals('array', $casts['details']);
        $this->assertEquals('datetime', $casts['resolved_at']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(AbuseAlert::class)
        );
    }

    public function test_user_relationship_returns_belongs_to(): void
    {
        $model = new AbuseAlert();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
        $this->assertEquals('user_id', $model->user()->getForeignKeyName());
    }

    public function test_resolver_relationship_returns_belongs_to(): void
    {
        $model = new AbuseAlert();
        $this->assertInstanceOf(BelongsTo::class, $model->resolver());
        $this->assertEquals('resolved_by', $model->resolver()->getForeignKeyName());
    }

    public function test_transaction_relationship_returns_belongs_to(): void
    {
        $model = new AbuseAlert();
        $this->assertInstanceOf(BelongsTo::class, $model->transaction());
        $this->assertEquals('transaction_id', $model->transaction()->getForeignKeyName());
    }
}
