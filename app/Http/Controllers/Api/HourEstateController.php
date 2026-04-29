<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\CaringCommunity\HourEstateService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use RuntimeException;

class HourEstateController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(private readonly HourEstateService $service)
    {
    }

    public function myEstate(): JsonResponse
    {
        $disabled = $this->guardMember();
        if ($disabled) {
            return $disabled;
        }

        return $this->respondWithData(
            $this->service->myEstate(TenantContext::getId(), (int) auth()->id())
        );
    }

    public function nominate(): JsonResponse
    {
        $disabled = $this->guardMember();
        if ($disabled) {
            return $disabled;
        }

        try {
            $estate = $this->service->nominate(TenantContext::getId(), (int) auth()->id(), $this->getAllInput());
        } catch (InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (RuntimeException $e) {
            return $this->respondWithError('ESTATE_FAILED', $e->getMessage(), null, 422);
        }

        return $this->respondWithData($estate);
    }

    public function adminIndex(): JsonResponse
    {
        $disabled = $this->guardAdmin();
        if ($disabled) {
            return $disabled;
        }

        $status = $this->query('status');
        return $this->respondWithData([
            'items' => $this->service->listEstates(
                TenantContext::getId(),
                is_string($status) && $status !== '' ? $status : null,
            ),
        ]);
    }

    public function reportDeceased(int $id): JsonResponse
    {
        $disabled = $this->guardAdmin();
        if ($disabled) {
            return $disabled;
        }

        $notes = $this->notesFromInput();
        try {
            $estate = $this->service->reportDeceased(TenantContext::getId(), $id, (int) auth()->id(), $notes);
        } catch (RuntimeException $e) {
            return $this->respondWithError('ESTATE_FAILED', $e->getMessage(), null, 422);
        }

        return $this->respondWithData($estate);
    }

    public function settle(int $id): JsonResponse
    {
        $disabled = $this->guardAdmin();
        if ($disabled) {
            return $disabled;
        }

        $notes = $this->notesFromInput();
        try {
            $estate = $this->service->settle(TenantContext::getId(), $id, (int) auth()->id(), $notes);
        } catch (RuntimeException $e) {
            return $this->respondWithError('ESTATE_FAILED', $e->getMessage(), null, 422);
        }

        return $this->respondWithData($estate);
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
            return $this->respondWithError('SERVICE_UNAVAILABLE', __('api.caring_hour_estates_unavailable'), null, 503);
        }

        return null;
    }

    private function notesFromInput(): ?string
    {
        $input = $this->getAllInput();
        $notes = isset($input['coordinator_notes']) ? trim((string) $input['coordinator_notes']) : '';
        return $notes !== '' ? mb_substr($notes, 0, 2000) : null;
    }
}
