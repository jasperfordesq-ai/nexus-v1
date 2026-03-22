<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobReferral extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'job_referrals';
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'vacancy_id', 'referrer_user_id', 'referred_user_id',
        'ref_token', 'applied', 'created_at', 'applied_at',
    ];

    protected $casts = [
        'applied'    => 'boolean',
        'created_at' => 'datetime',
        'applied_at' => 'datetime',
    ];
}
