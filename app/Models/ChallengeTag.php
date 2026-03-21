<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChallengeTag extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'challenge_tags';

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'tag_type',
    ];
}
