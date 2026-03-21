<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\ExchangeHistory;
use App\Models\ExchangeRequest;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class ExchangeRequestTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new ExchangeRequest();
        $this->assertEquals('exchange_requests', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new ExchangeRequest();
        $expected = [
            'tenant_id', 'listing_id', 'requester_id', 'provider_id',
            'proposed_hours', 'requester_notes', 'status',
            'broker_id', 'broker_notes',
            'requester_confirmed_at', 'requester_confirmed_hours',
            'provider_confirmed_at', 'provider_confirmed_hours',
            'final_hours', 'transaction_id',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new ExchangeRequest();
        $casts = $model->getCasts();
        $this->assertEquals('decimal:2', $casts['proposed_hours']);
        $this->assertEquals('decimal:2', $casts['requester_confirmed_hours']);
        $this->assertEquals('decimal:2', $casts['provider_confirmed_hours']);
        $this->assertEquals('decimal:2', $casts['final_hours']);
        $this->assertEquals('datetime', $casts['requester_confirmed_at']);
        $this->assertEquals('datetime', $casts['provider_confirmed_at']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(ExchangeRequest::class)
        );
    }

    public function test_listing_relationship_returns_belongs_to(): void
    {
        $model = new ExchangeRequest();
        $this->assertInstanceOf(BelongsTo::class, $model->listing());
        $this->assertEquals('listing_id', $model->listing()->getForeignKeyName());
    }

    public function test_requester_relationship_returns_belongs_to(): void
    {
        $model = new ExchangeRequest();
        $this->assertInstanceOf(BelongsTo::class, $model->requester());
        $this->assertEquals('requester_id', $model->requester()->getForeignKeyName());
    }

    public function test_provider_relationship_returns_belongs_to(): void
    {
        $model = new ExchangeRequest();
        $this->assertInstanceOf(BelongsTo::class, $model->provider());
        $this->assertEquals('provider_id', $model->provider()->getForeignKeyName());
    }

    public function test_broker_relationship_returns_belongs_to(): void
    {
        $model = new ExchangeRequest();
        $this->assertInstanceOf(BelongsTo::class, $model->broker());
        $this->assertEquals('broker_id', $model->broker()->getForeignKeyName());
    }

    public function test_transaction_relationship_returns_belongs_to(): void
    {
        $model = new ExchangeRequest();
        $this->assertInstanceOf(BelongsTo::class, $model->transaction());
        $this->assertEquals('transaction_id', $model->transaction()->getForeignKeyName());
    }

    public function test_history_relationship_returns_has_many(): void
    {
        $model = new ExchangeRequest();
        $this->assertInstanceOf(HasMany::class, $model->history());
        $this->assertEquals('exchange_id', $model->history()->getForeignKeyName());
    }
}
