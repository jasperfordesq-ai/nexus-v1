<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Http\Controllers\Api\Concerns\InteractsWithPodcasts;
use App\Models\PodcastEpisode;
use App\Models\PodcastShow;
use App\Services\PodcastConfigurationService;
use App\Services\PodcastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PodcastController extends BaseApiController
{
    use InteractsWithPodcasts;

    protected bool $isV2Api = true;

    public function index(): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $this->rateLimit('podcasts_browse', 60, 60);

        $userId = $this->getOptionalUserId() ?? $this->resolveSanctumUserOptionally();
        $result = PodcastService::browse([
            'page' => $this->queryInt('page', 1, 1),
            'per_page' => $this->queryInt('per_page', 12, 1, 50),
            'search' => $this->query('q'),
            'include_member_only' => $userId !== null,
        ]);

        return $this->respondWithPaginatedCollection(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page'],
        );
    }

    public function show(string $showSlug): JsonResponse
    {
        $this->ensurePodcastsFeature();

        $userId = $this->getOptionalUserId() ?? $this->resolveSanctumUserOptionally();
        $isAdmin = $this->callerIsAdmin();
        $show = PodcastService::findShowBySlug($showSlug, $userId, $isAdmin);
        if (!$show) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.show_not_found'), null, 404);
        }

        if (!PodcastService::canViewShow($show, $userId, $isAdmin)) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.show_not_found'), null, 404);
        }

        $data = $show->toArray();
        $data['rss_enabled'] = $show->visibility === 'public'
            && PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_ENABLE_RSS_FEED);

        return $this->respondWithData($data);
    }

    public function episode(string $showSlug, string $episodeSlug): JsonResponse
    {
        $this->ensurePodcastsFeature();

        $userId = $this->getOptionalUserId() ?? $this->resolveSanctumUserOptionally();
        $isAdmin = $this->callerIsAdmin();
        $show = PodcastService::findShowBySlug($showSlug, $userId, $isAdmin);
        if (!$show) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.show_not_found'), null, 404);
        }

        $episode = PodcastService::findEpisodeBySlug($show, $episodeSlug);
        if (!$episode) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.episode_not_found'), null, 404);
        }

        if (!PodcastService::canViewEpisode($episode, $show, $userId, $isAdmin)) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.episode_not_found'), null, 404);
        }

        return $this->respondWithData($episode->toArray());
    }

    public function rss(string $showSlug): Response
    {
        $this->ensurePodcastsFeature();

        if (!PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_ENABLE_RSS_FEED)) {
            abort(404);
        }

        $show = PodcastService::findShowBySlug($showSlug);
        if (!$show || !PodcastService::canViewShow($show, null, false) || $show->visibility !== 'public') {
            abort(404);
        }

        return response(PodcastService::buildRss($show), 200)
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    public function rssForTenant(int $tenantId, string $showSlug): Response
    {
        if (!TenantContext::setById($tenantId)) {
            abort(404);
        }
        $this->ensurePodcastsFeature();

        if (!PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_ENABLE_RSS_FEED)) {
            abort(404);
        }

        $show = PodcastService::findPublicShowForTenant($tenantId, $showSlug);
        if (!$show || !PodcastService::canViewShow($show, null, false) || $show->visibility !== 'public') {
            abort(404);
        }

        return response(PodcastService::buildRss($show), 200)
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    public function authored(): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $userId = $this->requirePodcastAuthor();

        return $this->respondWithData(PodcastService::authoredBy($userId));
    }

    public function store(): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $this->rateLimit('podcasts_write', 30, 60);
        $userId = $this->requirePodcastAuthor();

        $title = trim((string) $this->input('title', ''));
        if ($title === '') {
            return $this->respondWithError('VALIDATION_FAILED', __('api_controllers_2.podcasts.title_required'), 'title', 422);
        }

        $maxShows = (int) PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_MAX_SHOWS_PER_USER);
        if ($maxShows > 0 && count(PodcastService::authoredBy($userId)) >= $maxShows) {
            return $this->respondWithError('VALIDATION_FAILED', __('api_controllers_2.podcasts.max_shows_reached', ['max' => $maxShows]), null, 422);
        }

        $show = PodcastService::createShow($userId, $this->getAllInput());

        return $this->respondWithData($show, null, 201);
    }

    public function update(int $id): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $this->rateLimit('podcasts_write', 30, 60);
        $userId = $this->requirePodcastAuthor();

        $show = $this->findPodcastShowOrFail($id);
        $this->ensurePodcastOwnerOrAdmin($show, $userId);

        return $this->respondWithData(PodcastService::updateShow($show, $this->getAllInput()));
    }

    public function publish(int $id): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $this->rateLimit('podcasts_write', 30, 60);
        $userId = $this->requirePodcastAuthor();

        $show = $this->findPodcastShowOrFail($id);
        $this->ensurePodcastOwnerOrAdmin($show, $userId);

        return $this->respondWithData(PodcastService::publishShow($show));
    }

    public function archive(int $id): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $this->rateLimit('podcasts_write', 30, 60);
        $userId = $this->requirePodcastAuthor();

        $show = $this->findPodcastShowOrFail($id);
        $this->ensurePodcastOwnerOrAdmin($show, $userId);

        return $this->respondWithData(PodcastService::archiveShow($show));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $this->rateLimit('podcasts_write', 30, 60);
        $userId = $this->requirePodcastAuthor();

        $show = $this->findPodcastShowOrFail($id);
        $this->ensurePodcastOwnerOrAdmin($show, $userId);
        PodcastService::deleteShow($show);

        return $this->respondWithData(['deleted' => true]);
    }

    public function storeEpisode(int $showId): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $this->rateLimit('podcasts_write', 30, 60);
        $userId = $this->requirePodcastAuthor();

        $show = $this->findPodcastShowOrFail($showId);
        $this->ensurePodcastOwnerOrAdmin($show, $userId);

        $title = trim((string) $this->input('title', ''));
        $audioUrl = trim((string) $this->input('audio_url', ''));
        $audioFile = request()->file('audio');
        if ($title === '') {
            return $this->respondWithError('VALIDATION_FAILED', __('api_controllers_2.podcasts.episode_title_required'), 'title', 422);
        }
        if ($audioUrl === '' && !$audioFile) {
            return $this->respondWithError('VALIDATION_FAILED', __('api_controllers_2.podcasts.audio_url_required'), 'audio_url', 422);
        }

        try {
            $episode = PodcastService::createEpisode($show, $userId, $this->podcastInput(), $audioFile);
        } catch (\InvalidArgumentException $e) {
            return $this->podcastValidationError($e);
        }

        return $this->respondWithData($episode, null, 201);
    }

    public function updateEpisode(int $showId, int $episodeId): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $this->rateLimit('podcasts_write', 30, 60);
        $userId = $this->requirePodcastAuthor();

        $show = $this->findPodcastShowOrFail($showId);
        $this->ensurePodcastOwnerOrAdmin($show, $userId);
        $episode = $this->findPodcastEpisodeOrFail($episodeId);
        if ((int) $episode->show_id !== $show->id) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.episode_not_found'), null, 404);
        }

        try {
            return $this->respondWithData(PodcastService::updateEpisode($episode, $this->podcastInput(), request()->file('audio')));
        } catch (\InvalidArgumentException $e) {
            return $this->podcastValidationError($e);
        }
    }

    public function publishEpisode(int $showId, int $episodeId): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $this->rateLimit('podcasts_write', 30, 60);
        $userId = $this->requirePodcastAuthor();

        $show = $this->findPodcastShowOrFail($showId);
        $this->ensurePodcastOwnerOrAdmin($show, $userId);
        $episode = $this->findPodcastEpisodeOrFail($episodeId);
        if ((int) $episode->show_id !== $show->id) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.episode_not_found'), null, 404);
        }

        return $this->respondWithData(PodcastService::publishEpisode($episode));
    }

    public function archiveEpisode(int $showId, int $episodeId): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $this->rateLimit('podcasts_write', 30, 60);
        $userId = $this->requirePodcastAuthor();

        $show = $this->findPodcastShowOrFail($showId);
        $this->ensurePodcastOwnerOrAdmin($show, $userId);
        $episode = $this->findPodcastEpisodeOrFail($episodeId);
        if ((int) $episode->show_id !== $show->id) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.episode_not_found'), null, 404);
        }

        return $this->respondWithData(PodcastService::archiveEpisode($episode));
    }

    public function destroyEpisode(int $showId, int $episodeId): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $this->rateLimit('podcasts_write', 30, 60);
        $userId = $this->requirePodcastAuthor();

        $show = $this->findPodcastShowOrFail($showId);
        $this->ensurePodcastOwnerOrAdmin($show, $userId);
        $episode = $this->findPodcastEpisodeOrFail($episodeId);
        if ((int) $episode->show_id !== $show->id) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.episode_not_found'), null, 404);
        }
        PodcastService::deleteEpisode($episode);

        return $this->respondWithData(['deleted' => true]);
    }

    public function listen(int $episodeId): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $this->rateLimit('podcasts_listen', 120, 60);
        $userId = $this->getOptionalUserId() ?? $this->resolveSanctumUserOptionally();
        $episode = PodcastEpisode::with(['show', 'chapters'])->find($episodeId);

        if (!$episode || !PodcastService::canViewEpisode($episode, $episode->show, $userId, $this->callerIsAdmin())) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.episode_not_found'), null, 404);
        }

        PodcastService::recordListen($episode, $userId, $this->getAllInput(), request()->userAgent(), request()->ip());

        return $this->respondWithData(['recorded' => true]);
    }

    public function reaction(int $episodeId): JsonResponse
    {
        $this->ensurePodcastsFeature();
        $this->rateLimit('podcasts_reaction', 60, 60);
        $userId = $this->requireAuth();
        $episode = $this->findPodcastEpisodeOrFail($episodeId);

        if (!PodcastService::canViewEpisode($episode, $episode->show, $userId, $this->callerIsAdmin())) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.episode_not_found'), null, 404);
        }

        $active = PodcastService::toggleReaction($episode, $userId, (string) $this->input('reaction', 'like'));

        return $this->respondWithData(['active' => $active]);
    }

    public function audio(int $tenantId, int $episodeId): BinaryFileResponse|JsonResponse
    {
        if (!TenantContext::setById($tenantId)) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.episode_not_found'), null, 404);
        }
        $this->ensurePodcastsFeature();
        $episode = PodcastEpisode::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($episodeId);
        $show = $episode
            ? PodcastShow::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->find($episode->show_id)
            : null;

        if (!$episode || !$show) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.episode_not_found'), null, 404);
        }
        $episode->setRelation('show', $show);

        $userId = $this->getOptionalUserId() ?? $this->resolveSanctumUserOptionally();
        $isAdmin = $this->callerIsAdmin();
        $canView = PodcastService::canViewEpisode($episode, $show, $userId, $isAdmin)
            || PodcastService::hasValidMediaSignature(
                $episode,
                $tenantId,
                request()->query('expires') !== null ? (string) request()->query('expires') : null,
                request()->query('signature') !== null ? (string) request()->query('signature') : null
            );

        if (!$canView) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.episode_not_found'), null, 404);
        }

        $path = PodcastService::mediaPath($episode);
        if (!$path || !is_file($path)) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.episode_not_found'), null, 404);
        }

        return response()->file($path, [
            'Content-Type' => $episode->audio_mime ?: 'audio/mpeg',
            'Content-Length' => (string) filesize($path),
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function transcript(int $tenantId, int $episodeId): Response|JsonResponse
    {
        if (!TenantContext::setById($tenantId)) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.episode_not_found'), null, 404);
        }
        $this->ensurePodcastsFeature();

        if (!PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_ENABLE_TRANSCRIPTS)) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.episode_not_found'), null, 404);
        }

        $episode = PodcastService::findPublicEpisodeForTenant($tenantId, $episodeId);
        if (!$episode || !$episode->transcript) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.episode_not_found'), null, 404);
        }

        return response((string) $episode->transcript, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=300')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function chapters(int $tenantId, int $episodeId): JsonResponse
    {
        if (!TenantContext::setById($tenantId)) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.episode_not_found'), null, 404);
        }
        $this->ensurePodcastsFeature();

        if (!PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_ENABLE_CHAPTERS)) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.episode_not_found'), null, 404);
        }

        $episode = PodcastService::findPublicEpisodeForTenant($tenantId, $episodeId);
        if (!$episode || $episode->chapters->isEmpty()) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.podcasts.episode_not_found'), null, 404);
        }

        $chapters = $episode->chapters->map(fn ($chapter) => [
            'startTime' => (int) $chapter->starts_at_seconds,
            'title' => (string) $chapter->title,
            'url' => $chapter->url,
        ])->values()->all();

        return response()->json([
            'version' => '1.2.0',
            'chapters' => $chapters,
        ])->header('Cache-Control', 'public, max-age=300');
    }

    private function podcastValidationError(\InvalidArgumentException $e): JsonResponse
    {
        $key = str_contains($e->getMessage(), 'too large')
            ? 'audio_too_large'
            : 'invalid_media_url';

        return $this->respondWithError('VALIDATION_FAILED', __("api_controllers_2.podcasts.{$key}"), null, 422);
    }

    private function podcastInput(): array
    {
        $input = $this->getAllInput();
        if (isset($input['chapters']) && is_string($input['chapters'])) {
            $decoded = json_decode($input['chapters'], true);
            $input['chapters'] = is_array($decoded) ? $decoded : [];
        }

        return $input;
    }
}
