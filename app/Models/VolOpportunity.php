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

class VolOpportunity extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'vol_opportunities';

    protected $fillable = [
        'tenant_id',
        'created_by',
        'organization_id',
        'title',
        'description',
        'location',
        'skills_needed',
        'start_date',
        'end_date',
        'category_id',
        'status',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(VolOrganization::class, 'organization_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(VolShift::class, 'opportunity_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(VolApplication::class, 'opportunity_id');
    }
}
