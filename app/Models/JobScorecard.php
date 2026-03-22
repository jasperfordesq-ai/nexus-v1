<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobScorecard extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'job_scorecards';

    protected $fillable = [
        'tenant_id', 'vacancy_id', 'application_id', 'reviewer_id',
        'criteria', 'total_score', 'max_score', 'notes',
    ];

    protected $casts = [
        'criteria'    => 'array',
        'total_score' => 'float',
        'max_score'   => 'float',
    ];
}
