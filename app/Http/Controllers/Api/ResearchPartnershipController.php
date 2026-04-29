<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\CaringCommunity\ResearchPartnershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;

class ResearchPartnershipController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(private readonly ResearchPartnershipService $service)
    {
    }

    public function adminIndex(): JsonResponse
    {
        $disabled = $this->guardAdminResearch();
        if ($disabled) {
            return $disabled;
        }

        return $this->respondWithData([
            'partners' => $this->service->listPartners(TenantContext::getId()),
        ]);
    }

    public function adminStore(): JsonResponse
    {
        $disabled = $this->guardAdminResearch();
        if ($disabled) {
            return $disabled;
        }

        $input = $this->getAllInput();
        $validator = Validator::make($input, [
            'name' => 'required|string|max:255',
            'institution' => 'required|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'agreement_reference' => 'nullable|string|max:255',
            'methodology_url' => 'nullable|url|max:255',
            'status' => 'nullable|in:draft,active,paused,ended',
            'data_scope' => 'nullable|array',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.validation_failed'), null, 422);
        }

        try {
            $partner = $this->service->createPartner(TenantContext::getId(), (int) auth()->id(), $input);
        } catch (InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (RuntimeException $e) {
            return $this->respondWithError('SERVICE_UNAVAILABLE', $e->getMessage(), null, 503);
        }

        return $this->respondWithData($partner, null, 201);
    }

    public function adminGenerateDataset(int $partnerId): JsonResponse
    {
        $disabled = $this->guardAdminResearch();
        if ($disabled) {
            return $disabled;
        }

        $input = $this->getAllInput();
        $validator = Validator::make($input, [
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        if ($validator->fails()) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.validation_failed'), null, 422);
        }

        try {
            $result = $this->service->generateDatasetExport(
                TenantContext::getId(),
                $partnerId,
                (int) auth()->id(),
                (string) $input['period_start'],
                (string) $input['period_end'],
            );
        } catch (RuntimeException $e) {
            return $this->respondWithError('RESEARCH_EXPORT_FAILED', $e->getMessage(), null, 422);
        }

        return $this->respondWithData($result, null, 201);
    }

    public function myConsent(): JsonResponse
    {
        $disabled = $this->guardMemberResearch();
        if ($disabled) {
            return $disabled;
        }

        return $this->respondWithData(
            $this->service->getConsent(TenantContext::getId(), (int) auth()->id())
        );
    }

    public function updateMyConsent(): JsonResponse
    {
        $disabled = $this->guardMemberResearch();
        if ($disabled) {
            return $disabled;
        }

        $input = $this->getAllInput();
        $status = (string) ($input['consent_status'] ?? '');
        $notes = isset($input['notes']) && $input['notes'] !== '' ? (string) $input['notes'] : null;

        try {
            $consent = $this->service->recordConsent(TenantContext::getId(), (int) auth()->id(), $status, $notes);
        } catch (InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), 'consent_status', 422);
        } catch (RuntimeException $e) {
            return $this->respondWithError('SERVICE_UNAVAILABLE', $e->getMessage(), null, 503);
        }

        return $this->respondWithData($consent);
    }

    private function guardAdminResearch(): ?JsonResponse
    {
        $this->requireAdmin();
        return $this->guardResearchFeature();
    }

    private function guardMemberResearch(): ?JsonResponse
    {
        $this->requireAuth();
        return $this->guardResearchFeature();
    }

    private function guardResearchFeature(): ?JsonResponse
    {
        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        if (!$this->service->isAvailable()) {
            return $this->respondWithError('SERVICE_UNAVAILABLE', __('api.caring_research_unavailable'), null, 503);
        }

        return null;
    }
}
