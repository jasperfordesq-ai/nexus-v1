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

class CourseDiscussion extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'course_discussions';

    protected $fillable = ['course_id', 'lesson_id', 'user_id', 'parent_id', 'body', 'status'];

    protected $hidden = ['tenant_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(CourseDiscussion::class, 'parent_id')->orderBy('created_at');
    }
}
