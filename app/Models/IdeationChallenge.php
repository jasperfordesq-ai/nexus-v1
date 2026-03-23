<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdeationChallenge extends Model
{
    use HasTenantScope;

    protected $table = 'ideation_challenges';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'title',
        'description',
        'category',
        'status',
        'submission_deadline',
        'voting_deadline',
        'cover_image',
        'prize_description',
        'max_ideas_per_user',
        'tags',
        'is_featured',
    ];

    protected $casts = [
        'submission_deadline' => 'datetime',
        'voting_deadline' => 'datetime',
        'tags' => 'array',
        'is_featured' => 'boolean',
        'ideas_count' => 'integer',
        'views_count' => 'integer',
        'favorites_count' => 'integer',
    ];

    public function ideas(): HasMany
    {
        return $this->hasMany(ChallengeIdea::class, 'challenge_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(ChallengeOutcome::class, 'challenge_id');
    }
}
