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

class CourseQuiz extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'course_quizzes';

    protected $fillable = [
        'course_id',
        'lesson_id',
        'title',
        'description',
        'pass_mark_percent',
        'max_attempts',
        'time_limit_minutes',
        'shuffle_questions',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'pass_mark_percent' => 'integer',
        'max_attempts' => 'integer',
        'time_limit_minutes' => 'integer',
        'shuffle_questions' => 'boolean',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'lesson_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(CourseQuestion::class, 'quiz_id')->orderBy('position');
    }
}
