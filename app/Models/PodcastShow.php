<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PodcastShow extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'podcast_shows';

    protected $fillable = [
        'title',
        'slug',
        'summary',
        'description',
        'artwork_url',
        'language',
        'category',
        'author_name',
        'owner_email',
        'copyright',
        'funding_url',
        'explicit',
        'visibility',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'owner_user_id' => 'integer',
        'episode_count' => 'integer',
        'subscriber_count' => 'integer',
        'explicit' => 'boolean',
        'moderated_by' => 'integer',
        'moderated_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(PodcastEpisode::class, 'show_id')->orderByDesc('published_at')->orderByDesc('id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where('moderation_status', 'approved');
    }
}
