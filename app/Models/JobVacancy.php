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

class JobVacancy extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'job_vacancies';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'organization_id',
        'title',
        'description',
        'location',
        'is_remote',
        'type',
        'commitment',
        'category',
        'skills_required',
        'hours_per_week',
        'time_credits',
        'contact_email',
        'contact_phone',
        'deadline',
        'status',
        'latitude',
        'longitude',
        'salary_min',
        'salary_max',
        'salary_type',
        'salary_currency',
        'salary_negotiable',
        'salary_required',
        'is_featured',
        'featured_until',
        'expired_at',
        'renewed_at',
        'renewal_count',
        'views_count',
        'applications_count',
        'tagline',
        'video_url',
        'culture_photos',
        'company_size',
        'benefits',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'organization_id' => 'integer',
        'is_remote' => 'boolean',
        'hours_per_week' => 'float',
        'time_credits' => 'float',
        'salary_min' => 'float',
        'salary_max' => 'float',
        'salary_negotiable' => 'boolean',
        'is_featured' => 'boolean',
        'featured_until' => 'datetime',
        'expired_at' => 'datetime',
        'renewed_at' => 'datetime',
        'renewal_count' => 'integer',
        'views_count' => 'integer',
        'applications_count' => 'integer',
        'deadline' => 'datetime',
        'culture_photos' => 'array',
        'benefits'       => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class, 'vacancy_id');
    }

    public function team(): HasMany
    {
        return $this->hasMany(JobVacancyTeam::class, 'vacancy_id');
    }
}
