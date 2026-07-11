<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Observers;

use App\Models\PodcastEpisode;
use App\Models\PodcastShow;
use App\Observers\Concerns\InvalidatesPrerenderContent;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Collection;

final class PodcastShowPrerenderObserver implements ShouldHandleEventsAfterCommit
{
    use InvalidatesPrerenderContent;

    private const DELETION_EPISODES_RELATION = '__prerenderDeletionEpisodes';

    public function deleting(PodcastShow $show): void
    {
        // Capture dependent paths before a direct delete can cascade episodes.
        $show->setRelation(self::DELETION_EPISODES_RELATION, $this->episodes($show));
    }

    public function saved(PodcastShow $show): void
    {
        $this->refresh($show, 'saved');
    }

    public function deleted(PodcastShow $show): void
    {
        $this->refresh($show, 'deleted');
    }

    private function refresh(PodcastShow $show, string $event): void
    {
        $showSlugs = $this->originalAndCurrentString($show, 'slug');
        $routes = ['/podcasts'];

        foreach ($showSlugs as $showSlug) {
            $routes[] = "/podcasts/{$showSlug}";
        }

        // Episode pages render show title/artwork/visibility, so a show update
        // invalidates every dependent episode page as well as the show itself.
        foreach ($this->episodes($show) as $episode) {
            $episodeSlug = trim((string) $episode->slug);
            if ($episodeSlug === '') {
                continue;
            }
            foreach ($showSlugs as $showSlug) {
                $routes[] = "/podcasts/{$showSlug}/{$episodeSlug}";
            }
        }

        $this->refreshPrerenderRoutes($show, $routes, $event);
    }

    /** @return Collection<int,PodcastEpisode> */
    private function episodes(PodcastShow $show): Collection
    {
        if ($show->relationLoaded(self::DELETION_EPISODES_RELATION)) {
            /** @var Collection<int,PodcastEpisode> $episodes */
            $episodes = $show->getRelation(self::DELETION_EPISODES_RELATION);
            return $episodes;
        }

        if ($show->relationLoaded('episodes')) {
            /** @var Collection<int,PodcastEpisode> $episodes */
            $episodes = $show->getRelation('episodes');
            return $episodes;
        }

        $tenantId = (int) ($show->tenant_id ?? 0);
        $showId = (int) ($show->getKey() ?? 0);
        if ($tenantId <= 0 || $showId <= 0) {
            return collect();
        }

        return PodcastEpisode::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('show_id', $showId)
            ->get(['id', 'slug']);
    }
}
