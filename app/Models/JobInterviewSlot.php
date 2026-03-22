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

class JobInterviewSlot extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'job_interview_slots';

    protected $fillable = [
        'tenant_id',
        'job_id',
        'employer_user_id',
        'slot_start',
        'slot_end',
        'is_booked',
        'booked_by_user_id',
        'booked_at',
        'interview_type',
        'meeting_link',
        'location',
        'notes',
    ];

    protected $casts = [
        'job_id' => 'integer',
        'employer_user_id' => 'integer',
        'is_booked' => 'boolean',
        'booked_by_user_id' => 'integer',
        'slot_start' => 'datetime',
        'slot_end' => 'datetime',
        'booked_at' => 'datetime',
    ];

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(JobVacancy::class, 'job_id');
    }

    public function employer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employer_user_id');
    }

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by_user_id');
    }
}
