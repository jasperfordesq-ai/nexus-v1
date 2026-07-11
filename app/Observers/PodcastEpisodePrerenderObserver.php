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

final class PodcastEpisodePrerenderObserver implements ShouldHandleEventsAfterCommit
{
    use InvalidatesPrerenderContent;

    public function saved(PodcastEpisode $episode): void
    {
        $this->refresh($episode, 'saved');
    }

    public function deleted(PodcastEpisode $episode): void
    {
        $this->refresh($episode, 'deleted');
    }

    private function refresh(PodcastEpisode $episode, string $event): void
    {
        $tenantId = (int) ($episode->tenant_id ?? 0);
        $showIds = $this->originalAndCurrentId($episode, 'show_id');
        $showSlugs = $tenantId > 0 && $showIds !== []
            ? PodcastShow::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $showIds)
                ->pluck('slug')
                ->filter()
                ->map(static fn (mixed $slug): string => (string) $slug)
                ->values()
                ->all()
            : [];
        $episodeSlugs = $this->originalAndCurrentString($episode, 'slug');

        $routes = ['/podcasts'];
        foreach ($showSlugs as $showSlug) {
            $routes[] = "/podcasts/{$showSlug}";
            foreach ($episodeSlugs as $episodeSlug) {
                $routes[] = "/podcasts/{$showSlug}/{$episodeSlug}";
            }
        }

        $this->refreshPrerenderRoutes($episode, $routes, $event);
    }
}
