<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdeaTeamLink extends Model
{
    use HasTenantScope;

    protected $table = 'idea_team_links';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'idea_id', 'group_id', 'challenge_id', 'converted_by',
    ];

    public function idea(): BelongsTo
    {
        return $this->belongsTo(ChallengeIdea::class, 'idea_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }
}
