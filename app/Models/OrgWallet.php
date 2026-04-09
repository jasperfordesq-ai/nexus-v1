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
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgWallet extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'org_wallets';

    protected $fillable = [
        'organization_id',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'balance' => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(VolOrganization::class, 'organization_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(OrgTransaction::class, 'organization_id', 'organization_id');
    }
}
