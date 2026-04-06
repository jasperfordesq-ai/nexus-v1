<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VolOrganization extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'vol_organizations';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'description',
        'contact_email',
        'website',
        'slug',
        'status',
        'logo_url',
        'auto_pay_enabled',
        'balance',
    ];

    protected $casts = [
        'auto_pay_enabled' => 'boolean',
        'balance' => 'decimal:2',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(VolOpportunity::class, 'organization_id');
    }
}
