<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedJob extends Model
{
    use HasTenantScope;

    protected $table = 'saved_jobs';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'job_id',
        'saved_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'job_id' => 'integer',
        'saved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(JobVacancy::class, 'job_id');
    }
}
