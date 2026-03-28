<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
