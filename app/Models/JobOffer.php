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

class JobOffer extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'job_offers';

    protected $fillable = [
        'tenant_id',
        'vacancy_id',
        'application_id',
        'salary_offered',
        'salary_currency',
        'salary_type',
        'start_date',
        'message',
        'status',
        'responded_at',
        'expires_at',
    ];

    protected $casts = [
        'tenant_id'      => 'integer',
        'vacancy_id'     => 'integer',
        'application_id' => 'integer',
        'salary_offered' => 'float',
        'start_date'     => 'date',
        'responded_at'   => 'datetime',
        'expires_at'     => 'datetime',
    ];

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(JobVacancy::class, 'vacancy_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'application_id');
    }
}
