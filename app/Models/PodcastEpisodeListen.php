<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PodcastEpisodeListen extends Model
{
    use HasTenantScope;

    protected $table = 'podcast_episode_listens';

    public $timestamps = false;

    protected $fillable = [
        'episode_id',
        'user_id',
        'session_hash',
        'listened_seconds',
        'completed',
        'client_family',
        'retention_bucket',
        'user_agent_hash',
        'ip_hash',
        'created_at',
    ];

    protected $hidden = ['tenant_id', 'session_hash', 'user_agent_hash', 'ip_hash'];

    protected $casts = [
        'episode_id' => 'integer',
        'user_id' => 'integer',
        'listened_seconds' => 'integer',
        'completed' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function episode(): BelongsTo
    {
        return $this->belongsTo(PodcastEpisode::class, 'episode_id');
    }
}
