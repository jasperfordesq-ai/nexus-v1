<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;

class CommunityFundAccount extends Model
{
    use HasTenantScope;

    protected $table = 'community_fund_accounts';

    protected $fillable = [
        'tenant_id', 'balance', 'total_deposited', 'total_withdrawn',
        'total_donated', 'description',
    ];

    protected $casts = [
        'balance' => 'float',
        'total_deposited' => 'float',
        'total_withdrawn' => 'float',
        'total_donated' => 'float',
    ];
}
