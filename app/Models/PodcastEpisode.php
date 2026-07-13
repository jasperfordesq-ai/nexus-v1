<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use App\Services\PodcastConfigurationService;
use App\Services\PodcastService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PodcastEpisode extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'podcast_episodes';

    // Note: media lifecycle + storage columns (audio_storage_path/disk,
    // media_processing_status, media_scan_status, media_waveform_json,
    // media_duration_source) are intentionally NOT fillable. They are
    // server-controlled and set only by PodcastService / the media job via
    // direct assignment, so a client can never inject e.g. scan_status="clean".
    protected $fillable = [
        'show_id',
        'title',
        'slug',
        'summary',
        'description',
        'audio_url',
        'audio_mime',
        'audio_bytes',
        'duration_seconds',
        'episode_number',
        'season_number',
        'explicit',
        'episode_type',
        'visibility',
        'transcript',
        'transcript_language',
        'cover_image_url',
        'scheduled_for',
    ];

    protected $hidden = [
        'tenant_id',
        'audio_storage_path',
        'audio_storage_disk',
        'moderation_notes',
        'moderated_by',
        'moderated_at',
    ];

    protected $casts = [
        'show_id' => 'integer',
        'author_user_id' => 'integer',
        'audio_bytes' => 'integer',
        'duration_seconds' => 'integer',
        'episode_number' => 'integer',
        'season_number' => 'integer',
        'explicit' => 'boolean',
        'listen_count' => 'integer',
        'moderated_by' => 'integer',
        'moderated_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'published_at' => 'datetime',
        'announced_at' => 'datetime',
        'media_waveform_json' => 'array',
    ];

    /** Unsafe or cross-tenant legacy cover art is never emitted to a browser or RSS. */
    public function getCoverImageUrlAttribute(mixed $value): ?string
    {
        return PodcastService::safePodcastArtworkPath($value);
    }

    /** Response-only tenant capability; never persisted as a database column. */
    public function getTranscriptsEnabledAttribute(): bool
    {
        return (bool) PodcastConfigurationService::get(
            PodcastConfigurationService::CONFIG_ENABLE_TRANSCRIPTS
        );
    }

    /** Response-only tenant capability; never persisted as a database column. */
    public function getChaptersEnabledAttribute(): bool
    {
        return (bool) PodcastConfigurationService::get(
            PodcastConfigurationService::CONFIG_ENABLE_CHAPTERS
        );
    }

    /** Response-only tenant capability; never persisted as a database column. */
    public function getReactionsEnabledAttribute(): bool
    {
        return (bool) PodcastConfigurationService::get(
            PodcastConfigurationService::CONFIG_ENABLE_EPISODE_REACTIONS
        );
    }

    /** Response-only media mode; never persisted as a database column. */
    public function getHostedAudioAttribute(): bool
    {
        return ! empty($this->attributes['audio_storage_path']);
    }

    public function show(): BelongsTo
    {
        return $this->belongsTo(PodcastShow::class, 'show_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(PodcastEpisodeChapter::class, 'episode_id')->orderBy('position')->orderBy('starts_at_seconds');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where('moderation_status', 'approved')
            ->where(function (Builder $q): void {
                $q->whereNull('scheduled_for')
                    ->orWhere('scheduled_for', '<=', now());
            });
    }

    /** SQL projection matching the fail-closed hosted-media distribution gate. */
    public function scopeDistributionReady(Builder $query): Builder
    {
        return $query->where(function (Builder $media): void {
            $media->where(function (Builder $external): void {
                $external->whereNull('audio_storage_path')
                    ->where('audio_url', 'like', 'https://%')
                    ->where('audio_url', 'not like', '%/api/v2/podcasts/media/%');
            })->orWhere(function (Builder $hosted): void {
                $hosted->whereNotNull('audio_storage_path')
                    ->where('media_processing_status', 'complete')
                    ->whereIn('media_scan_status', ['clean', 'not_required']);
            });
        });
    }
}
