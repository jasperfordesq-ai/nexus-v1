<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api\Concerns;

use App\Core\TenantContext;
use App\Models\PodcastEpisode;
use App\Models\PodcastShow;
use App\Services\PodcastConfigurationService;
use Illuminate\Http\Exceptions\HttpResponseException;

trait InteractsWithPodcasts
{
    protected function ensurePodcastsFeature(): void
    {
        if (!TenantContext::hasFeature('podcasts')) {
            throw new HttpResponseException(
                $this->respondWithError('FEATURE_DISABLED', __('api_controllers_2.podcasts.feature_disabled'), null, 403)
            );
        }
    }

    protected function requirePodcastAuthor(): int
    {
        $userId = $this->requireAuth();

        if (PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_ALLOW_MEMBER_SHOW_CREATION)) {
            return $userId;
        }

        return $this->requireAdmin();
    }

    protected function findPodcastShowOrFail(int $id): PodcastShow
    {
        $show = PodcastShow::find($id);

        if (!$show) {
            throw new HttpResponseException(
                $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.show_not_found'), null, 404)
            );
        }

        return $show;
    }

    protected function findPodcastEpisodeOrFail(int $id): PodcastEpisode
    {
        $episode = PodcastEpisode::with(['show', 'chapters'])->find($id);

        if (!$episode) {
            throw new HttpResponseException(
                $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.episode_not_found'), null, 404)
            );
        }

        return $episode;
    }

    protected function ensurePodcastOwnerOrAdmin(PodcastShow $show, int $userId): void
    {
        if ((int) $show->owner_user_id === $userId) {
            return;
        }

        $this->requireAdmin();
    }

    protected function callerIsAdmin(): bool
    {
        try {
            $this->requireAdmin();
            return true;
        } catch (HttpResponseException) {
            return false;
        }
    }
}
