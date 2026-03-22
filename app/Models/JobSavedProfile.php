<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobSavedProfile extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'job_saved_profiles';

    protected $fillable = [
        'tenant_id', 'user_id', 'cv_path', 'cv_filename', 'cv_size',
        'headline', 'cover_text',
    ];

    protected $casts = ['cv_size' => 'integer'];
}
