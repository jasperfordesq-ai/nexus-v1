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

class CourseQuestion extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'course_questions';

    protected $fillable = [
        'quiz_id',
        'type',
        'prompt',
        'options',
        'correct',
        'explanation',
        'points',
        'position',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'options' => 'array',
        'correct' => 'array',
        'points' => 'integer',
        'position' => 'integer',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(CourseQuiz::class, 'quiz_id');
    }
}
