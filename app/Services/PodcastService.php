<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\PodcastEpisode;
use App\Models\PodcastEpisodeChapter;
use App\Models\PodcastEpisodeListen;
use App\Models\PodcastEpisodeReaction;
use App\Models\PodcastShow;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PodcastService
{
    private const HOSTED_AUDIO_PLACEHOLDER = 'podcast-hosted://pending';
    private const HOSTED_AUDIO_ROUTE_TTL_SECONDS = 3600;
    private const ALLOWED_AUDIO_TYPES = [
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/aac' => 'aac',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/ogg' => 'ogg',
        'audio/webm' => 'webm',
        'video/webm' => 'webm',
    ];

    /**
     * @param array{search?:string,page?:int,per_page?:int,include_member_only?:bool} $filters
     * @return array{items:array,total:int,page:int,per_page:int}
     */
    public static function browse(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($filters['per_page'] ?? 12)));

        $query = PodcastShow::query()
            ->published()
            ->with(['owner:id,name,avatar_url'])
            ->withCount(['episodes as approved_episode_count' => fn (Builder $q) => $q->published()]);

        if (empty($filters['include_member_only'])) {
            $query->where('visibility', 'public');
        } else {
            $query->whereIn('visibility', ['public', 'members']);
        }

        if (!empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('summary', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        $total = (clone $query)->count();
        $items = $query->orderByDesc('published_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get()
            ->toArray();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function authoredBy(int $userId): array
    {
        $shows = PodcastShow::where('owner_user_id', $userId)
            ->with([
                'episodes' => fn (Builder $q) => $q->with('chapters'),
            ])
            ->withCount('episodes')
            ->orderByDesc('updated_at')
            ->get();

        $shows->each(function (PodcastShow $show) use ($userId): void {
            $show->episodes->each(fn (PodcastEpisode $episode) => self::prepareEpisodeForResponse($episode, $userId, false));
        });

        return $shows->toArray();
    }

    public static function findShowById(int $id): ?PodcastShow
    {
        return PodcastShow::with(['owner:id,name,avatar_url'])->find($id);
    }

    public static function findShowBySlug(string $slug, ?int $userId = null, bool $isAdmin = false): ?PodcastShow
    {
        $show = PodcastShow::where('slug', $slug)
            ->with('owner:id,name,avatar_url')
            ->first();

        if (!$show) {
            return null;
        }

        $show->load([
            'episodes' => fn ($q) => self::visibleEpisodeQuery($q, $show, $userId, $isAdmin)
                ->when(
                    PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_ENABLE_CHAPTERS),
                    fn ($episodeQuery) => $episodeQuery->with('chapters')
                ),
        ]);

        $show->episodes->each(fn (PodcastEpisode $episode) => self::prepareEpisodeForResponse($episode, $userId, $isAdmin));

        return $show;
    }

    public static function findEpisodeBySlug(PodcastShow $show, string $slug): ?PodcastEpisode
    {
        $episode = PodcastEpisode::where('show_id', $show->id)
            ->where('slug', $slug)
            ->with(['show.owner:id,name,avatar_url', 'author:id,name,avatar_url', 'chapters'])
            ->first();

        if ($episode) {
            self::applyConfigVisibilityToEpisode($episode);
            self::prepareEpisodeForResponse($episode, null, false);
        }

        return $episode;
    }

    public static function createShow(int $ownerUserId, array $data): PodcastShow
    {
        $title = trim((string) ($data['title'] ?? ''));
        $visibility = self::normalizeVisibility((string) ($data['visibility'] ?? 'public'));

        $show = new PodcastShow([
            'title' => $title,
            'slug' => self::uniqueShowSlug((string) ($data['slug'] ?? $title)),
            'summary' => self::nullableText($data['summary'] ?? null, 600),
            'description' => self::nullableText($data['description'] ?? null),
            'artwork_url' => self::nullableUrl($data['artwork_url'] ?? null),
            'language' => self::nullableText($data['language'] ?? 'en', 20) ?: 'en',
            'category' => self::nullableText($data['category'] ?? null, 120),
            'visibility' => $visibility,
        ]);

        $show->owner_user_id = $ownerUserId;
        $show->status = 'draft';
        $show->moderation_status = self::moderationEnabled() ? 'pending' : 'approved';
        $show->save();

        return $show;
    }

    public static function updateShow(PodcastShow $show, array $data): PodcastShow
    {
        foreach (['title', 'summary', 'description', 'artwork_url', 'language', 'category'] as $field) {
            if (array_key_exists($field, $data)) {
                $limit = match ($field) {
                    'summary' => 600,
                    'artwork_url' => 1000,
                    'language' => 20,
                    'category' => 120,
                    default => null,
                };
                $show->{$field} = $field === 'artwork_url'
                    ? self::nullableUrl($data[$field] ?? null)
                    : self::nullableText($data[$field], $limit);
            }
        }

        if (array_key_exists('visibility', $data)) {
            $show->visibility = self::normalizeVisibility((string) $data['visibility']);
        }

        $show->save();

        return $show;
    }

    public static function publishShow(PodcastShow $show): PodcastShow
    {
        $show->status = 'published';
        $show->moderation_status = self::moderationEnabled() ? 'pending' : 'approved';
        if (!$show->published_at) {
            $show->published_at = now();
        }
        $show->save();

        if ($show->moderation_status === 'approved') {
            self::recordFeedActivity('podcast_show', $show->id, (int) $show->owner_user_id, $show->title, $show->summary, $show->artwork_url, [
                'slug' => $show->slug,
            ]);
        }

        return $show;
    }

    public static function createEpisode(PodcastShow $show, int $authorUserId, array $data, ?UploadedFile $audioFile = null): PodcastEpisode
    {
        return DB::transaction(function () use ($show, $authorUserId, $data, $audioFile): PodcastEpisode {
            $title = trim((string) ($data['title'] ?? ''));
            $hasHostedAudio = $audioFile !== null;
            $episode = new PodcastEpisode([
                'show_id' => $show->id,
                'title' => $title,
                'slug' => self::uniqueEpisodeSlug($show->id, (string) ($data['slug'] ?? $title)),
                'summary' => self::nullableText($data['summary'] ?? null, 600),
                'description' => self::nullableText($data['description'] ?? null),
                'audio_url' => $hasHostedAudio ? self::HOSTED_AUDIO_PLACEHOLDER : self::requiredUrl($data['audio_url'] ?? ''),
                'audio_mime' => $hasHostedAudio ? null : self::nullableText($data['audio_mime'] ?? null, 120),
                'audio_bytes' => $hasHostedAudio ? null : self::normalizeAudioBytes($data['audio_bytes'] ?? null),
                'duration_seconds' => isset($data['duration_seconds']) ? max(0, (int) $data['duration_seconds']) : null,
                'episode_number' => isset($data['episode_number']) ? max(0, (int) $data['episode_number']) : null,
                'season_number' => isset($data['season_number']) ? max(0, (int) $data['season_number']) : null,
                'explicit' => filter_var($data['explicit'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'episode_type' => self::normalizeEpisodeType((string) ($data['episode_type'] ?? 'full')),
                'visibility' => self::normalizeEpisodeVisibility((string) ($data['visibility'] ?? 'inherit')),
                'transcript' => PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_ENABLE_TRANSCRIPTS)
                    ? self::nullableText($data['transcript'] ?? null)
                    : null,
                'transcript_language' => self::nullableText($data['transcript_language'] ?? null, 20),
                'cover_image_url' => self::nullableUrl($data['cover_image_url'] ?? null),
                'scheduled_for' => !empty($data['scheduled_for']) ? Carbon::parse($data['scheduled_for']) : null,
            ]);

            $episode->author_user_id = $authorUserId;
            $episode->status = 'draft';
            $episode->moderation_status = self::moderationEnabled() ? 'pending' : 'approved';
            $episode->save();

            if ($audioFile) {
                self::storeHostedAudio($episode, $audioFile);
            }

            self::syncChapters($episode, $data['chapters'] ?? null);
            self::refreshEpisodeCount($show);
            self::prepareEpisodeForResponse($episode, $authorUserId, false);

            return $episode->load('chapters');
        });
    }

    public static function updateEpisode(PodcastEpisode $episode, array $data, ?UploadedFile $audioFile = null): PodcastEpisode
    {
        foreach ([
            'title', 'summary', 'description', 'audio_url', 'audio_mime',
            'transcript', 'transcript_language', 'cover_image_url',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $limit = match ($field) {
                    'summary' => 600,
                    'audio_url', 'cover_image_url' => 1000,
                    'audio_mime' => 120,
                    'transcript_language' => 20,
                    default => null,
                };
                if ($field === 'audio_url') {
                    $episode->{$field} = self::requiredUrl($data[$field] ?? '');
                    self::clearHostedAudio($episode);
                } elseif ($field === 'cover_image_url') {
                    $episode->{$field} = self::nullableUrl($data[$field] ?? null);
                } elseif ($field === 'transcript' && !PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_ENABLE_TRANSCRIPTS)) {
                    $episode->{$field} = null;
                } else {
                    $episode->{$field} = self::nullableText($data[$field], $limit);
                }
            }
        }

        foreach (['audio_bytes', 'duration_seconds', 'episode_number', 'season_number'] as $field) {
            if (array_key_exists($field, $data)) {
                $episode->{$field} = $field === 'audio_bytes'
                    ? self::normalizeAudioBytes($data[$field])
                    : ($data[$field] === null ? null : max(0, (int) $data[$field]));
            }
        }

        if (array_key_exists('explicit', $data)) {
            $episode->explicit = filter_var($data['explicit'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('episode_type', $data)) {
            $episode->episode_type = self::normalizeEpisodeType((string) $data['episode_type']);
        }
        if (array_key_exists('visibility', $data)) {
            $episode->visibility = self::normalizeEpisodeVisibility((string) $data['visibility']);
        }
        if (array_key_exists('scheduled_for', $data)) {
            $episode->scheduled_for = !empty($data['scheduled_for']) ? Carbon::parse($data['scheduled_for']) : null;
        }

        $episode->save();
        if ($audioFile) {
            self::replaceHostedAudio($episode, $audioFile);
        }
        self::syncChapters($episode, $data['chapters'] ?? null);
        self::prepareEpisodeForResponse($episode, (int) $episode->author_user_id, false);

        return $episode->load('chapters');
    }

    public static function publishEpisode(PodcastEpisode $episode): PodcastEpisode
    {
        $episode->status = 'published';
        $episode->moderation_status = self::moderationEnabled() ? 'pending' : 'approved';
        if (!$episode->published_at) {
            $episode->published_at = $episode->scheduled_for && $episode->scheduled_for->isFuture()
                ? $episode->scheduled_for
                : now();
        }
        $episode->save();

        self::refreshEpisodeCount($episode->show);
        if ($episode->moderation_status === 'approved') {
            self::recordFeedActivity('podcast_episode', $episode->id, (int) $episode->author_user_id, $episode->title, $episode->summary, $episode->cover_image_url, [
                'show_id' => $episode->show_id,
                'slug' => $episode->slug,
            ]);
        }

        self::prepareEpisodeForResponse($episode, (int) $episode->author_user_id, false);

        return $episode->load('chapters');
    }

    public static function storeHostedAudio(PodcastEpisode $episode, UploadedFile $file): PodcastEpisode
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Invalid podcast media upload');
        }

        $mime = (string) $file->getMimeType();
        if (!array_key_exists($mime, self::ALLOWED_AUDIO_TYPES)) {
            throw new \InvalidArgumentException('Invalid podcast media upload');
        }

        self::normalizeAudioBytes($file->getSize());

        $path = sprintf(
            'podcasts/%d/shows/%d/episodes/%d/audio_%s.%s',
            TenantContext::getId(),
            $episode->show_id,
            $episode->id,
            bin2hex(random_bytes(12)),
            self::ALLOWED_AUDIO_TYPES[$mime]
        );

        $stream = fopen($file->getRealPath(), 'rb');
        if (!$stream || !Storage::disk('local')->put($path, $stream)) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw new \InvalidArgumentException('Podcast media upload failed');
        }
        if (is_resource($stream)) {
            fclose($stream);
        }

        $episode->audio_storage_disk = 'local';
        $episode->audio_storage_path = $path;
        $episode->audio_mime = $mime;
        $episode->audio_bytes = $file->getSize();
        $episode->audio_url = self::episodeAudioUrl($episode, false);
        $episode->save();

        return $episode;
    }

    public static function episodeAudioUrl(PodcastEpisode $episode, bool $signed = true): string
    {
        $tenantId = (int) ($episode->tenant_id ?: TenantContext::getId());
        $base = self::apiUrl('/api/v2/podcasts/media/' . $tenantId . '/' . $episode->id . '/audio');
        if (!$signed) {
            return $base;
        }

        $expires = time() + self::HOSTED_AUDIO_ROUTE_TTL_SECONDS;
        return $base . '?expires=' . $expires . '&signature=' . self::mediaSignature($tenantId, $episode->id, $expires);
    }

    public static function hasValidMediaSignature(PodcastEpisode $episode, int $tenantId, ?string $expires, ?string $signature): bool
    {
        if (!$expires || !$signature || !ctype_digit($expires) || (int) $expires < time()) {
            return false;
        }

        return (int) $episode->tenant_id === $tenantId
            && hash_equals(self::mediaSignature($tenantId, $episode->id, (int) $expires), $signature);
    }

    public static function mediaPath(PodcastEpisode $episode): ?string
    {
        if (!$episode->audio_storage_path || ($episode->audio_storage_disk ?? 'local') !== 'local') {
            return null;
        }

        return Storage::disk('local')->path($episode->audio_storage_path);
    }

    public static function archiveShow(PodcastShow $show): PodcastShow
    {
        $show->status = 'archived';
        $show->save();

        return $show;
    }

    public static function archiveEpisode(PodcastEpisode $episode): PodcastEpisode
    {
        $episode->status = 'archived';
        $episode->save();
        self::refreshEpisodeCount($episode->show);

        return $episode->load('chapters');
    }

    public static function deleteShow(PodcastShow $show): void
    {
        DB::transaction(function () use ($show): void {
            PodcastEpisode::where('show_id', $show->id)->get()
                ->each(fn (PodcastEpisode $episode) => self::deleteEpisode($episode, false));
            $show->delete();
        });
    }

    public static function deleteEpisode(PodcastEpisode $episode, bool $refreshShow = true): void
    {
        DB::transaction(function () use ($episode, $refreshShow): void {
            $show = $episode->show;
            self::deleteHostedAudioFile($episode);
            PodcastEpisodeChapter::where('episode_id', $episode->id)->delete();
            PodcastEpisodeListen::where('episode_id', $episode->id)->delete();
            PodcastEpisodeReaction::where('episode_id', $episode->id)->delete();
            $episode->delete();

            if ($refreshShow && $show) {
                self::refreshEpisodeCount($show);
            }
        });
    }

    public static function moderateShow(PodcastShow $show, int $adminId, string $action, ?string $notes): PodcastShow
    {
        $show->moderation_status = self::moderationActionToStatus($action);
        $show->moderation_notes = $notes;
        $show->moderated_by = $adminId;
        $show->moderated_at = now();
        if ($action === 'reject') {
            $show->status = 'draft';
        }
        $show->save();

        if ($action === 'approve' && $show->status === 'published') {
            self::recordFeedActivity('podcast_show', $show->id, (int) $show->owner_user_id, $show->title, $show->summary, $show->artwork_url, [
                'slug' => $show->slug,
            ]);
        }

        return $show;
    }

    public static function moderateEpisode(PodcastEpisode $episode, int $adminId, string $action, ?string $notes): PodcastEpisode
    {
        $episode->moderation_status = self::moderationActionToStatus($action);
        $episode->moderation_notes = $notes;
        $episode->moderated_by = $adminId;
        $episode->moderated_at = now();
        if ($action === 'reject') {
            $episode->status = 'draft';
        }
        $episode->save();
        self::refreshEpisodeCount($episode->show);

        if ($action === 'approve' && $episode->status === 'published') {
            self::recordFeedActivity('podcast_episode', $episode->id, (int) $episode->author_user_id, $episode->title, $episode->summary, $episode->cover_image_url, [
                'show_id' => $episode->show_id,
                'slug' => $episode->slug,
            ]);
        }

        return $episode;
    }

    public static function recordListen(PodcastEpisode $episode, ?int $userId, array $data, ?string $userAgent, ?string $ip): void
    {
        if (!PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_ENABLE_LISTEN_ANALYTICS)) {
            return;
        }

        PodcastEpisodeListen::create([
            'episode_id' => $episode->id,
            'user_id' => $userId,
            'session_hash' => !empty($data['session_id']) ? self::privateHash((string) $data['session_id']) : null,
            'listened_seconds' => max(0, (int) ($data['listened_seconds'] ?? 0)),
            'completed' => filter_var($data['completed'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'user_agent_hash' => $userAgent ? self::privateHash($userAgent) : null,
            'ip_hash' => $ip ? self::privateHash($ip) : null,
            'created_at' => now(),
        ]);

        $episode->increment('listen_count');
    }

    public static function toggleReaction(PodcastEpisode $episode, int $userId, string $reaction = 'like'): bool
    {
        if (!PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_ENABLE_EPISODE_REACTIONS)) {
            return false;
        }

        $reaction = preg_replace('/[^a-z0-9_-]/i', '', $reaction) ?: 'like';
        $existing = PodcastEpisodeReaction::where('episode_id', $episode->id)
            ->where('user_id', $userId)
            ->where('reaction', $reaction)
            ->first();

        if ($existing) {
            $existing->delete();
            return false;
        }

        PodcastEpisodeReaction::create([
            'episode_id' => $episode->id,
            'user_id' => $userId,
            'reaction' => $reaction,
        ]);

        return true;
    }

    /**
     * @return array{shows:array,episodes:array,stats:array,top_episodes:array}
     */
    public static function adminIndex(?string $moderationStatus = null): array
    {
        $showQuery = PodcastShow::with('owner:id,name')->orderByDesc('created_at');
        $episodeQuery = PodcastEpisode::with(['show:id,title,slug', 'author:id,name'])->orderByDesc('created_at');

        if ($moderationStatus) {
            $showQuery->where('moderation_status', $moderationStatus);
            $episodeQuery->where('moderation_status', $moderationStatus);
        }

        return [
            'shows' => $showQuery->limit(200)->get()->toArray(),
            'episodes' => $episodeQuery->limit(200)->get()->toArray(),
            'stats' => [
                'total_shows' => PodcastShow::count(),
                'published_shows' => PodcastShow::where('status', 'published')->count(),
                'pending_shows' => PodcastShow::where('moderation_status', 'pending')->count(),
                'total_episodes' => PodcastEpisode::count(),
                'published_episodes' => PodcastEpisode::where('status', 'published')->count(),
                'pending_episodes' => PodcastEpisode::where('moderation_status', 'pending')->count(),
                'total_listens' => PodcastEpisodeListen::count(),
                'completed_listens' => PodcastEpisodeListen::where('completed', true)->count(),
                'completion_rate' => self::completionRate(),
            ],
            'top_episodes' => self::topEpisodes(),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function topEpisodes(): array
    {
        return PodcastEpisode::with(['show:id,title,slug'])
            ->where('listen_count', '>', 0)
            ->orderByDesc('listen_count')
            ->limit(10)
            ->get(['id', 'show_id', 'title', 'slug', 'listen_count'])
            ->toArray();
    }

    private static function completionRate(): int
    {
        $total = PodcastEpisodeListen::count();
        if ($total === 0) {
            return 0;
        }

        $completed = PodcastEpisodeListen::where('completed', true)->count();
        return (int) round(($completed / $total) * 100);
    }

    public static function buildRss(PodcastShow $show): string
    {
        $episodes = PodcastEpisode::where('show_id', $show->id)
            ->published()
            ->whereIn('visibility', ['inherit', 'public'])
            ->with('chapters')
            ->orderByDesc('published_at')
            ->limit(300)
            ->get();

        $channelLink = self::frontendUrl('/podcasts/' . $show->slug);
        $rss = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:podcast="https://podcastindex.org/namespace/1.0">',
            '<channel>',
            '<title>' . self::xml($show->title) . '</title>',
            '<link>' . self::xml($channelLink) . '</link>',
            '<description>' . self::xml((string) ($show->description ?: $show->summary ?: $show->title)) . '</description>',
            '<language>' . self::xml((string) $show->language) . '</language>',
            '<itunes:author>' . self::xml((string) ($show->owner?->name ?? '')) . '</itunes:author>',
            '<itunes:explicit>false</itunes:explicit>',
        ];

        if (self::isHttpUrl((string) $show->artwork_url)) {
            $rss[] = '<itunes:image href="' . self::xml($show->artwork_url) . '" />';
        }

        foreach ($episodes as $episode) {
            $audioUrl = $episode->audio_storage_path
                ? self::episodeAudioUrl($episode, false)
                : (string) $episode->audio_url;
            if (!self::isHttpUrl($audioUrl)) {
                continue;
            }
            $episodeLink = self::frontendUrl('/podcasts/' . $show->slug . '/' . $episode->slug);
            $rss[] = '<item>';
            $rss[] = '<title>' . self::xml($episode->title) . '</title>';
            $rss[] = '<link>' . self::xml($episodeLink) . '</link>';
            $rss[] = '<guid isPermaLink="false">' . self::xml("podcast-{$show->id}-episode-{$episode->id}") . '</guid>';
            $rss[] = '<description>' . self::xml((string) ($episode->description ?: $episode->summary ?: $episode->title)) . '</description>';
            if ($episode->published_at) {
                $rss[] = '<pubDate>' . $episode->published_at->toRfc2822String() . '</pubDate>';
            }
            $enclosureAttrs = [
                'url="' . self::xml($audioUrl) . '"',
                'type="' . self::xml((string) ($episode->audio_mime ?: 'audio/mpeg')) . '"',
            ];
            $enclosureAttrs[] = 'length="' . (int) ($episode->audio_bytes ?? 0) . '"';
            $rss[] = '<enclosure ' . implode(' ', $enclosureAttrs) . ' />';
            if ($episode->duration_seconds) {
                $rss[] = '<itunes:duration>' . gmdate('H:i:s', (int) $episode->duration_seconds) . '</itunes:duration>';
            }
            if ($episode->transcript && PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_ENABLE_TRANSCRIPTS)) {
                $rss[] = '<podcast:transcript url="' . self::xml(self::transcriptUrl($episode)) . '" type="text/plain" language="' . self::xml((string) ($episode->transcript_language ?: $show->language ?: 'en')) . '" />';
            }
            if ($episode->chapters->isNotEmpty() && PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_ENABLE_CHAPTERS)) {
                $rss[] = '<podcast:chapters url="' . self::xml(self::chaptersUrl($episode)) . '" type="application/json+chapters" />';
            }
            $rss[] = '<itunes:explicit>' . ($episode->explicit ? 'true' : 'false') . '</itunes:explicit>';
            $rss[] = '</item>';
        }

        $rss[] = '</channel>';
        $rss[] = '</rss>';

        return implode("\n", $rss);
    }

    public static function transcriptUrl(PodcastEpisode $episode): string
    {
        return self::apiUrl('/api/v2/podcasts/transcripts/' . (int) $episode->tenant_id . '/' . $episode->id . '.txt');
    }

    public static function chaptersUrl(PodcastEpisode $episode): string
    {
        return self::apiUrl('/api/v2/podcasts/chapters/' . (int) $episode->tenant_id . '/' . $episode->id . '.json');
    }

    public static function findPublicShowForTenant(int $tenantId, string $slug): ?PodcastShow
    {
        if (!TenantContext::setById($tenantId)) {
            return null;
        }

        return self::findShowBySlug($slug);
    }

    public static function findPublicEpisodeForTenant(int $tenantId, int $episodeId): ?PodcastEpisode
    {
        if (!TenantContext::setById($tenantId)) {
            return null;
        }

        $episode = PodcastEpisode::with(['show', 'chapters'])
            ->published()
            ->find($episodeId);

        if (!$episode || !$episode->show || !self::canViewEpisode($episode, $episode->show, null, false)) {
            return null;
        }

        return $episode;
    }

    public static function canViewShow(PodcastShow $show, ?int $userId, bool $isAdmin): bool
    {
        if ($isAdmin || ((int) $show->owner_user_id === (int) $userId && $userId !== null)) {
            return true;
        }

        if ($show->status !== 'published' || $show->moderation_status !== 'approved') {
            return false;
        }

        return match ($show->visibility) {
            'public' => true,
            'members' => $userId !== null,
            default => false,
        };
    }

    public static function canViewEpisode(PodcastEpisode $episode, PodcastShow $show, ?int $userId, bool $isAdmin): bool
    {
        if ($isAdmin || ((int) $episode->author_user_id === (int) $userId && $userId !== null)) {
            return true;
        }

        if (!self::canViewShow($show, $userId, $isAdmin)) {
            return false;
        }

        if ($episode->status !== 'published' || $episode->moderation_status !== 'approved') {
            return false;
        }

        $visibility = $episode->visibility === 'inherit' ? $show->visibility : $episode->visibility;

        return match ($visibility) {
            'public' => true,
            'members' => $userId !== null,
            default => false,
        };
    }

    private static function visibleEpisodeQuery($query, PodcastShow $show, ?int $userId, bool $isAdmin)
    {
        if ($isAdmin || ($userId !== null && (int) $show->owner_user_id === (int) $userId)) {
            return $query;
        }

        $query->published();

        if ($userId !== null) {
            return $query->whereIn('visibility', ['inherit', 'public', 'members']);
        }

        if ($show->visibility === 'public') {
            return $query->whereIn('visibility', ['inherit', 'public']);
        }

        return $query->where('visibility', 'public');
    }

    public static function prepareEpisodeForResponse(PodcastEpisode $episode, ?int $userId, bool $isAdmin): void
    {
        self::applyConfigVisibilityToEpisode($episode);

        if (!$episode->audio_storage_path) {
            return;
        }

        $showVisibility = $episode->show?->visibility ?? 'public';
        $visibility = $episode->visibility === 'inherit' ? $showVisibility : $episode->visibility;
        $episode->audio_url = $visibility === 'public'
            ? self::episodeAudioUrl($episode, false)
            : self::episodeAudioUrl($episode, true);
    }

    private static function syncChapters(PodcastEpisode $episode, mixed $chapters): void
    {
        if (!PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_ENABLE_CHAPTERS)) {
            PodcastEpisodeChapter::where('episode_id', $episode->id)->delete();
            return;
        }

        if (!is_array($chapters)) {
            return;
        }

        PodcastEpisodeChapter::where('episode_id', $episode->id)->delete();

        foreach (array_values($chapters) as $position => $chapter) {
            if (!is_array($chapter) || trim((string) ($chapter['title'] ?? '')) === '') {
                continue;
            }
            PodcastEpisodeChapter::create([
                'episode_id' => $episode->id,
                'title' => self::nullableText($chapter['title'], 200),
                'starts_at_seconds' => max(0, (int) ($chapter['starts_at_seconds'] ?? 0)),
                'url' => self::nullableText($chapter['url'] ?? null, 1000),
                'position' => $position,
            ]);
        }
    }

    private static function refreshEpisodeCount(PodcastShow $show): void
    {
        $show->episode_count = PodcastEpisode::where('show_id', $show->id)->published()->count();
        $show->save();
    }

    private static function moderationEnabled(): bool
    {
        return (bool) PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_MODERATION_ENABLED);
    }

    private static function moderationActionToStatus(string $action): string
    {
        return match ($action) {
            'approve' => 'approved',
            'reject' => 'rejected',
            'flag' => 'flagged',
            default => throw new \InvalidArgumentException('Invalid moderation action'),
        };
    }

    private static function normalizeVisibility(string $visibility): string
    {
        if (in_array($visibility, ['members', 'private'], true) && !PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_ENABLE_PRIVATE_SHOWS)) {
            return 'public';
        }

        return in_array($visibility, ['public', 'members', 'private'], true) ? $visibility : 'public';
    }

    private static function normalizeEpisodeVisibility(string $visibility): string
    {
        if (in_array($visibility, ['members', 'private'], true) && !PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_ENABLE_PRIVATE_SHOWS)) {
            return 'inherit';
        }

        return in_array($visibility, ['inherit', 'public', 'members', 'private'], true) ? $visibility : 'inherit';
    }

    private static function normalizeEpisodeType(string $type): string
    {
        return in_array($type, ['full', 'trailer', 'bonus'], true) ? $type : 'full';
    }

    private static function uniqueShowSlug(string $base): string
    {
        return self::uniqueSlug($base, fn (string $candidate): bool => PodcastShow::where('slug', $candidate)->exists(), 'podcast');
    }

    private static function uniqueEpisodeSlug(int $showId, string $base): string
    {
        return self::uniqueSlug($base, fn (string $candidate): bool => PodcastEpisode::where('show_id', $showId)->where('slug', $candidate)->exists(), 'episode');
    }

    private static function uniqueSlug(string $base, callable $exists, string $fallback): string
    {
        $slug = Str::slug($base) ?: $fallback;
        $candidate = $slug;
        $i = 2;

        while ($exists($candidate)) {
            $candidate = $slug . '-' . $i;
            $i++;
        }

        return $candidate;
    }

    private static function nullableText(mixed $value, ?int $limit = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return $limit ? mb_substr($text, 0, $limit) : $text;
    }

    private static function applyConfigVisibilityToEpisode(PodcastEpisode $episode): void
    {
        if (!PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_ENABLE_TRANSCRIPTS)) {
            $episode->transcript = null;
            $episode->transcript_language = null;
        }

        if (!PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_ENABLE_CHAPTERS)) {
            $episode->setRelation('chapters', collect());
        }
    }

    private static function nullableUrl(mixed $value): ?string
    {
        $url = self::nullableText($value, 1000);
        if ($url === null) {
            return null;
        }

        return self::isHttpUrl($url) ? $url : null;
    }

    private static function requiredUrl(mixed $value): string
    {
        $url = self::nullableText($value, 1000);
        if ($url === null || !self::isHttpUrl($url)) {
            throw new \InvalidArgumentException('Invalid podcast media URL');
        }

        return $url;
    }

    private static function isHttpUrl(string $value): bool
    {
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private static function normalizeAudioBytes(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $bytes = max(0, (int) $value);
        $maxMb = (int) PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_MAX_AUDIO_SIZE_MB);
        if ($maxMb > 0 && $bytes > ($maxMb * 1024 * 1024)) {
            throw new \InvalidArgumentException('Podcast audio file is too large');
        }

        return $bytes;
    }

    private static function replaceHostedAudio(PodcastEpisode $episode, UploadedFile $file): void
    {
        $oldPath = $episode->audio_storage_path;
        $oldDisk = $episode->audio_storage_disk ?? 'local';

        self::storeHostedAudio($episode, $file);

        if ($oldPath && $oldDisk === 'local' && $oldPath !== $episode->audio_storage_path) {
            Storage::disk('local')->delete($oldPath);
        }
    }

    private static function clearHostedAudio(PodcastEpisode $episode): void
    {
        self::deleteHostedAudioFile($episode);
        $episode->audio_storage_path = null;
        $episode->audio_storage_disk = null;
    }

    private static function deleteHostedAudioFile(PodcastEpisode $episode): void
    {
        if ($episode->audio_storage_path && ($episode->audio_storage_disk ?? 'local') === 'local') {
            Storage::disk('local')->delete($episode->audio_storage_path);
        }
    }

    private static function privateHash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    private static function mediaSignature(int $tenantId, int $episodeId, int $expires): string
    {
        return hash_hmac('sha256', $tenantId . '|' . $episodeId . '|' . $expires, (string) config('app.key'));
    }

    private static function recordFeedActivity(string $type, int $entityId, int $userId, string $title, ?string $summary, ?string $imageUrl, array $metadata): void
    {
        try {
            app(FeedActivityService::class)->recordActivity(
                TenantContext::getId(),
                $userId,
                $type,
                $entityId,
                [
                    'title' => $title,
                    'content' => (string) ($summary ?? ''),
                    'image_url' => $imageUrl,
                    'metadata' => $metadata,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('[PodcastService] feed post failed', ['error' => $e->getMessage()]);
        }
    }

    private static function frontendUrl(string $path): string
    {
        return rtrim(TenantContext::getFrontendUrl(), '/') . TenantContext::getSlugPrefix() . $path;
    }

    private static function apiUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/') . $path;
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
