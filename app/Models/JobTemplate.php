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

class JobTemplate extends Model
{
    use HasFactory, HasTenantScope;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected $table = 'job_templates';

    protected $fillable = [
        'tenant_id','user_id','name','description','type','commitment',
        'category','skills_required','is_remote','salary_type','salary_currency',
        'salary_min','salary_max','hours_per_week','time_credits','benefits',
        'tagline','is_public','use_count',
    ];

    protected $casts = [
        'is_remote' => 'boolean',
        'is_public' => 'boolean',
        'benefits'  => 'array',
        'salary_min'=> 'float',
        'salary_max'=> 'float',
        'use_count' => 'integer',
    ];
}
