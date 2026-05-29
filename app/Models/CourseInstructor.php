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

class CourseInstructor extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'course_instructors';

    protected $fillable = ['user_id', 'granted_by', 'granted_at'];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'granted_by' => 'integer',
        'granted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
