<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;

/** Durable pointer to podcast bytes that must be removed from storage. */
class PodcastMediaCleanupTask extends Model
{
    use HasTenantScope;

    protected $table = 'podcast_media_cleanup_tasks';

    protected $fillable = [
        'asset_key',
        'kind',
        'disk',
        'path',
        'source_episode_id',
        'reason',
        'status',
        'attempts',
        'available_at',
        'last_error',
        'completed_at',
    ];

    protected $casts = [
        'source_episode_id' => 'integer',
        'attempts' => 'integer',
        'available_at' => 'immutable_datetime',
        'completed_at' => 'immutable_datetime',
    ];
}
