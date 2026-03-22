<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobPipelineRule extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'job_pipeline_rules';

    protected $fillable = [
        'tenant_id','vacancy_id','name','trigger_stage','condition_days',
        'action','action_target','is_active','last_run_at',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'condition_days' => 'integer',
        'last_run_at'    => 'datetime',
    ];
}
