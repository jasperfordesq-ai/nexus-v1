<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;

class ChallengeCategory extends Model
{
    use HasTenantScope;

    protected $table = 'challenge_categories';

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'icon', 'color', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}
