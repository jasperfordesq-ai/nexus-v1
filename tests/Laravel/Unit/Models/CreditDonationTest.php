<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\CreditDonation;
use App\Models\Concerns\HasTenantScope;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class CreditDonationTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new CreditDonation();
        $this->assertEquals('credit_donations', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new CreditDonation();
        $expected = [
            'tenant_id', 'donor_id', 'recipient_type', 'recipient_id',
            'amount', 'message', 'transaction_id',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new CreditDonation();
        $casts = $model->getCasts();
        $this->assertEquals('decimal:2', $casts['amount']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(CreditDonation::class)
        );
    }

    public function test_donor_relationship_returns_belongs_to(): void
    {
        $model = new CreditDonation();
        $this->assertInstanceOf(BelongsTo::class, $model->donor());
        $this->assertEquals('donor_id', $model->donor()->getForeignKeyName());
    }

    public function test_recipient_relationship_returns_belongs_to(): void
    {
        $model = new CreditDonation();
        $this->assertInstanceOf(BelongsTo::class, $model->recipient());
        $this->assertEquals('recipient_id', $model->recipient()->getForeignKeyName());
    }

    public function test_transaction_relationship_returns_belongs_to(): void
    {
        $model = new CreditDonation();
        $this->assertInstanceOf(BelongsTo::class, $model->transaction());
        $this->assertEquals('transaction_id', $model->transaction()->getForeignKeyName());
    }
}
