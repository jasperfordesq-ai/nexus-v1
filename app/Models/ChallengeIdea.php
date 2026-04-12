<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ChallengeIdea — an idea submitted to an ideation challenge.
 */
// INTENTIONAL: No tenant scope — challenge_ideas has no tenant_id column.
// Scoped indirectly via parent IdeationChallenge (challenge_id -> ideation_challenges.tenant_id).
class ChallengeIdea extends Model
{
    use HasFactory;

    protected $table = 'challenge_ideas';

    protected $fillable = [
        'challenge_id', 'user_id', 'title', 'description',
        'votes_count', 'comments_count', 'status', 'image_url',
    ];

    protected $casts = [
        'votes_count' => 'integer',
        'comments_count' => 'integer',
    ];

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(IdeationChallenge::class, 'challenge_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(IdeaMedia::class, 'idea_id');
    }

    public function teamLink(): HasMany
    {
        return $this->hasMany(IdeaTeamLink::class, 'idea_id');
    }
}
