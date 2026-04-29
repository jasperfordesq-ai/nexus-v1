<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\CaringCommunity\KissTreffenService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use RuntimeException;

class KissTreffenController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(private readonly KissTreffenService $service)
    {
    }

    public function index(): JsonResponse
    {
        $disabled = $this->guardMember();
        if ($disabled) {
            return $disabled;
        }

        return $this->respondWithData([
            'items' => $this->service->list(TenantContext::getId(), $this->queryInt('per_page', 20, 1, 100)),
        ]);
    }

    public function show(int $eventId): JsonResponse
    {
        $disabled = $this->guardMember();
        if ($disabled) {
            return $disabled;
        }

        try {
            return $this->respondWithData($this->service->getByEventId(TenantContext::getId(), $eventId));
        } catch (RuntimeException $e) {
            return $this->respondWithError('NOT_FOUND', $e->getMessage(), null, 404);
        }
    }

    public function adminUpsert(int $eventId): JsonResponse
    {
        $disabled = $this->guardAdmin();
        if ($disabled) {
            return $disabled;
        }

        try {
            return $this->respondWithData($this->service->upsert(TenantContext::getId(), $eventId, $this->getAllInput()));
        } catch (InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (RuntimeException $e) {
            return $this->respondWithError('KISS_TREFFEN_FAILED', $e->getMessage(), null, 422);
        }
    }

    public function adminRecordMinutes(int $eventId): JsonResponse
    {
        $disabled = $this->guardAdmin();
        if ($disabled) {
            return $disabled;
        }

        try {
            return $this->respondWithData(
                $this->service->recordMinutes(TenantContext::getId(), $eventId, (int) auth()->id(), $this->getAllInput())
            );
        } catch (InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (RuntimeException $e) {
            return $this->respondWithError('KISS_TREFFEN_FAILED', $e->getMessage(), null, 422);
        }
    }

    private function guardMember(): ?JsonResponse
    {
        $this->requireAuth();
        return $this->guardFeature();
    }

    private function guardAdmin(): ?JsonResponse
    {
        $this->requireAdmin();
        return $this->guardFeature();
    }

    private function guardFeature(): ?JsonResponse
    {
        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        if (!$this->service->isAvailable()) {
            return $this->respondWithError('SERVICE_UNAVAILABLE', __('api.caring_kiss_treffen_unavailable'), null, 503);
        }

        return null;
    }
}
