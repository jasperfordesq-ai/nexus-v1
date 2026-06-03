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

class PodcastEpisodeReaction extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'podcast_episode_reactions';

    protected $fillable = [
        'episode_id',
        'user_id',
        'reaction',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'episode_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function episode(): BelongsTo
    {
        return $this->belongsTo(PodcastEpisode::class, 'episode_id');
    }
}
