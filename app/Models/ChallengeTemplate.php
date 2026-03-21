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

class ChallengeTemplate extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'challenge_templates';

    protected $fillable = [
        'tenant_id', 'title', 'description', 'default_tags', 'default_category_id',
        'evaluation_criteria', 'prize_description', 'max_ideas_per_user', 'created_by',
    ];

    protected $casts = [
        'default_tags' => 'array',
        'evaluation_criteria' => 'array',
        'max_ideas_per_user' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ChallengeCategory::class, 'default_category_id');
    }
}
