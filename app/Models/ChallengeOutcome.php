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

class ChallengeOutcome extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'challenge_outcomes';

    protected $fillable = [
        'tenant_id', 'challenge_id', 'winning_idea_id', 'status', 'impact_description',
    ];

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(IdeationChallenge::class, 'challenge_id');
    }
}
