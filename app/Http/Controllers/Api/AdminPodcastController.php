<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InteractsWithPodcasts;
use App\Services\PodcastService;
use Illuminate\Http\JsonResponse;

class AdminPodcastController extends BaseApiController
{
    use InteractsWithPodcasts;

    protected bool $isV2Api = true;

    public function index(): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $this->requireAdmin();

        return $this->respondWithData(PodcastService::adminIndex($this->query('moderation_status')));
    }

    public function moderateShow(int $id): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $adminId = $this->requireAdmin();
        $show = $this->findPodcastShowOrFail($id);

        try {
            $show = PodcastService::moderateShow(
                $show,
                $adminId,
                (string) $this->input('action', ''),
                $this->input('notes')
            );
        } catch (\InvalidArgumentException) {
            return $this->respondWithError('VALIDATION_FAILED', __('api_controllers_2.podcasts.invalid_moderation_action'), 'action', 422);
        }

        return $this->respondWithData($show);
    }

    public function moderateEpisode(int $id): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $adminId = $this->requireAdmin();
        $episode = $this->findPodcastEpisodeOrFail($id);

        try {
            $episode = PodcastService::moderateEpisode(
                $episode,
                $adminId,
                (string) $this->input('action', ''),
                $this->input('notes')
            );
        } catch (\InvalidArgumentException) {
            return $this->respondWithError('VALIDATION_FAILED', __('api_controllers_2.podcasts.invalid_moderation_action'), 'action', 422);
        }

        return $this->respondWithData($episode);
    }

    public function validateFeed(int $id): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $this->requireAdmin();
        $show = $this->findPodcastShowOrFail($id);

        return $this->respondWithData(PodcastService::validateFeed($show));
    }

    public function resolveReport(int $episodeId): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $adminId = $this->requireAdmin();
        $episode = $this->findPodcastEpisodeOrFail($episodeId);

        try {
            return $this->respondWithData(PodcastService::resolveEpisodeReports(
                $episode,
                $adminId,
                (string) $this->input('status', 'resolved')
            ));
        } catch (\InvalidArgumentException) {
            return $this->respondWithError('VALIDATION_FAILED', __('api_controllers_2.podcasts.invalid_report_status'), 'status', 422);
        }
    }
}
