<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseCategory extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'course_categories';

    protected $fillable = ['name', 'slug', 'description', 'icon', 'position'];

    protected $hidden = ['tenant_id'];

    protected $casts = ['position' => 'integer'];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'category_id');
    }
}
