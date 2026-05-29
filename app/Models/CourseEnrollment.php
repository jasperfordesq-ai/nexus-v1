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

class CourseEnrollment extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'course_enrollments';

    protected $fillable = [
        'course_id',
        'user_id',
        'cohort_id',
        'status',
        'progress_percent',
        'credits_paid',
        'credits_earned',
        'enrolled_at',
        'completed_at',
        'last_accessed_at',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'progress_percent' => 'decimal:2',
        'credits_paid' => 'decimal:2',
        'credits_earned' => 'decimal:2',
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_accessed_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(CourseLessonProgress::class, 'enrollment_id');
    }
}
