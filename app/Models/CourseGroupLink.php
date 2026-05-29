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

class CourseGroupLink extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'course_group_links';

    protected $fillable = ['course_id', 'group_id'];

    protected $hidden = ['tenant_id'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
