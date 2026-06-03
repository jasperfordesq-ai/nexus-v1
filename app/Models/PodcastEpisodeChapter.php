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

class PodcastEpisodeChapter extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'podcast_episode_chapters';

    protected $fillable = [
        'episode_id',
        'title',
        'starts_at_seconds',
        'url',
        'position',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'episode_id' => 'integer',
        'starts_at_seconds' => 'integer',
        'position' => 'integer',
    ];

    public function episode(): BelongsTo
    {
        return $this->belongsTo(PodcastEpisode::class, 'episode_id');
    }
}
