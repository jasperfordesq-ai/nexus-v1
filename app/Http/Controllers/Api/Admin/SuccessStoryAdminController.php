<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Api\BaseApiController;
use App\Services\CaringCommunity\SuccessStoryService;
use Illuminate\Http\JsonResponse;

/**
 * AG91 — Success-Story Proof Cards admin endpoints.
 *
 * CRUD over the per-tenant success-story envelope, plus a "seed demo" action
 * that lets the admin populate three illustrative cards on a fresh tenant
 * without manual entry. Tenant-scoped via TenantContext::getId(). Feature
 * gate `caring_community` enforced inline as defence in depth on top of the
 * route-level admin middleware.
 */
class SuccessStoryAdminController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly SuccessStoryService $service,
    ) {
    }

    /** GET /v2/admin/caring-community/success-stories */
    public function index(): JsonResponse
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $items = $this->service->listStories(TenantContext::getId(), false);

        return $this->respondWithData([
            'items' => $items,
        ]);
    }

    /** POST /v2/admin/caring-community/success-stories */
    public function store(): JsonResponse
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $payload = (array) request()->all();
        $result = $this->service->createStory(TenantContext::getId(), $payload);

        if (isset($result['errors']) && $result['errors'] !== []) {
            return $this->respondWithErrors($result['errors'], 422);
        }

        return $this->respondWithData([
            'story' => $result['story'] ?? null,
        ], null, 201);
    }

    /** PUT /v2/admin/caring-community/success-stories/{storyId} */
    public function update(string $storyId): JsonResponse
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $payload = (array) request()->all();
        $result = $this->service->updateStory(TenantContext::getId(), $storyId, $payload);

        if (isset($result['error']) && $result['error'] === 'not_found') {
            return $this->respondNotFound('Success story not found.');
        }

        if (isset($result['errors']) && $result['errors'] !== []) {
            return $this->respondWithErrors($result['errors'], 422);
        }

        return $this->respondWithData([
            'story' => $result['story'] ?? null,
        ]);
    }

    /** DELETE /v2/admin/caring-community/success-stories/{storyId} */
    public function destroy(string $storyId): JsonResponse
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $result = $this->service->deleteStory(TenantContext::getId(), $storyId);

        if (isset($result['error']) && $result['error'] === 'not_found') {
            return $this->respondNotFound('Success story not found.');
        }

        return $this->respondWithData(['ok' => true]);
    }

    /** POST /v2/admin/caring-community/success-stories/seed-demo */
    public function seed(): JsonResponse
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $result = $this->service->seedDemoStories(TenantContext::getId());

        if (isset($result['error']) && $result['error'] === 'already_seeded') {
            return $this->respondWithError(
                'ALREADY_SEEDED',
                'Stories already exist — refusing to seed demo cards.',
                null,
                409,
            );
        }

        return $this->respondWithData([
            'items' => $result['items'] ?? [],
        ]);
    }

    /** POST /v2/admin/caring-community/success-stories/{storyId}/refresh-live */
    public function refresh(string $storyId): JsonResponse
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $result = $this->service->refreshLiveMetrics(TenantContext::getId(), $storyId);

        if (isset($result['error'])) {
            return match ($result['error']) {
                'not_found' => $this->respondNotFound('Success story not found.'),
                'manual_metric' => $this->respondWithError(
                    'MANUAL_METRIC',
                    'This story has a manual metric — there is nothing to refresh.',
                    null,
                    422,
                ),
                'metric_unavailable' => $this->respondWithError(
                    'METRIC_UNAVAILABLE',
                    'Live metric value is currently unavailable for this story.',
                    null,
                    503,
                ),
                default => $this->respondServerError('Failed to refresh metric.'),
            };
        }

        return $this->respondWithData([
            'story' => $result['story'] ?? null,
        ]);
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function guard(): ?JsonResponse
    {
        $this->requireAdmin();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondForbidden('Caring Community feature is not enabled for this tenant.');
        }

        return null;
    }
}

/*
 * Routes to register in routes/api.php:
 *   GET    /v2/admin/caring-community/success-stories                          => index
 *   POST   /v2/admin/caring-community/success-stories                          => store
 *   POST   /v2/admin/caring-community/success-stories/seed-demo                => seed
 *   PUT    /v2/admin/caring-community/success-stories/{storyId}                => update
 *   DELETE /v2/admin/caring-community/success-stories/{storyId}                => destroy
 *   POST   /v2/admin/caring-community/success-stories/{storyId}/refresh-live   => refresh
 */
