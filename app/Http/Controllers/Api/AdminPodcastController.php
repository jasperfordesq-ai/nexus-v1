<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InteractsWithPodcasts;
use App\Services\PodcastConfigurationService;
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

        $showsPage = $this->queryInt('shows_page', 1, 1) ?? 1;
        $episodesPage = $this->queryInt('episodes_page', 1, 1) ?? 1;
        // Default 200 preserves the pre-pagination response shape exactly.
        $perPage = $this->queryInt('per_page', 200, 1, 200) ?? 200;
        $rawModerationStatus = $this->query('moderation_status');
        $moderationStatus = is_string($rawModerationStatus) ? trim($rawModerationStatus) : null;
        $rawSearch = $this->query('q');
        $search = is_string($rawSearch) ? trim($rawSearch) : null;

        $payload = PodcastService::adminIndex(
            $moderationStatus,
            $showsPage,
            $episodesPage,
            $perPage,
            $search
        );

        return $this->respondWithData($payload, [
            'shows_page' => $showsPage,
            'episodes_page' => $episodesPage,
            'per_page' => $perPage,
            'shows_total' => (int) ($payload['totals']['shows'] ?? 0),
            'episodes_total' => (int) ($payload['totals']['episodes'] ?? 0),
        ]);
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

        return $this->respondWithData($show->makeVisible(['owner_email', 'moderation_notes', 'moderated_by', 'moderated_at']));
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
        } catch (\InvalidArgumentException $e) {
            if (str_contains($e->getMessage(), 'not ready for publishing')) {
                return $this->respondWithError('MEDIA_NOT_READY', __('api_controllers_2.podcasts.media_not_ready'), null, 409);
            }
            return $this->respondWithError('VALIDATION_FAILED', __('api_controllers_2.podcasts.invalid_moderation_action'), 'action', 422);
        }

        PodcastService::prepareEpisodeForResponse($episode, $adminId, true);

        return $this->respondWithData($episode->makeVisible(['moderation_notes', 'moderated_by', 'moderated_at']));
    }

    public function validateFeed(int $id): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $this->requireAdmin();
        $show = $this->findPodcastShowOrFail($id);

        return $this->respondWithData(PodcastService::validateFeed($show));
    }

    /**
     * POST /v2/admin/podcasts/storage/verify
     *
     * Probe a filesystem disk (write/read/delete round-trip) so an admin can
     * confirm cloud credentials work BEFORE flipping
     * podcasts.media_storage_driver to 'cloud'. Defaults to the tenant's
     * configured cloud disk when no disk is supplied.
     */
    public function verifyStorage(): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $this->requireAdmin();

        $disk = trim((string) $this->input('disk', ''));
        if ($disk === '') {
            $disk = (string) PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_CLOUD_STORAGE_DISK, 's3') ?: 's3';
        }

        return $this->respondWithData(PodcastService::verifyMediaDisk($disk));
    }

    public function resolveReport(int $reportId): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $adminId = $this->requireAdmin();

        try {
            $result = PodcastService::resolveEpisodeReport(
                $reportId,
                $adminId,
                (string) $this->input('status', 'resolved')
            );
            if ($result === null) {
                return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.episode_not_found'), null, 404);
            }

            return $this->respondWithData($result);
        } catch (\InvalidArgumentException) {
            return $this->respondWithError('VALIDATION_FAILED', __('api_controllers_2.podcasts.invalid_report_status'), 'status', 422);
        }
    }
}
