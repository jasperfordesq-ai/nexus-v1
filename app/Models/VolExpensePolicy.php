<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolExpensePolicy extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'vol_expense_policies';

    protected $fillable = [
        'tenant_id',
        'organization_id',
        'expense_type',
        'max_amount',
        'max_monthly',
        'requires_receipt_above',
        'requires_approval',
    ];

    protected $casts = [
        'max_amount' => 'decimal:2',
        'max_monthly' => 'decimal:2',
        'requires_receipt_above' => 'decimal:2',
        'requires_approval' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(VolOrganization::class, 'organization_id');
    }
}
