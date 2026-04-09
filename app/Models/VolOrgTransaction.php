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

class VolOrgTransaction extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'vol_org_transactions';

    const UPDATED_AT = null;

    protected $fillable = [
        'vol_organization_id', 'user_id', 'vol_log_id',
        'type', 'amount', 'balance_after', 'description',
    ];

    protected $casts = [
        'vol_organization_id' => 'integer',
        'user_id' => 'integer',
        'vol_log_id' => 'integer',
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(VolOrganization::class, 'vol_organization_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function volLog(): BelongsTo
    {
        return $this->belongsTo(VolLog::class, 'vol_log_id');
    }
}
