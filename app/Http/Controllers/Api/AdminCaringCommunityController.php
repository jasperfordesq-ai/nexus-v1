<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\CaringCommunityRolePresetService;
use App\Services\CaringCommunityWorkflowPolicyService;
use App\Services\CaringCommunityWorkflowService;
use Illuminate\Http\JsonResponse;

class AdminCaringCommunityController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly CaringCommunityWorkflowService $workflowService,
        private readonly CaringCommunityRolePresetService $rolePresetService,
        private readonly CaringCommunityWorkflowPolicyService $policyService,
    ) {
    }

    public function workflow(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        return $this->respondWithData($this->workflowService->summary(TenantContext::getId()));
    }

    public function rolePresets(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        return $this->respondWithData($this->rolePresetService->status(TenantContext::getId()));
    }

    public function installRolePresets(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $preset = request()->input('preset');
        $presetKey = is_string($preset) && $preset !== '' ? $preset : null;

        return $this->respondWithData($this->rolePresetService->install(TenantContext::getId(), $presetKey));
    }

    public function updatePolicy(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        return $this->respondWithData($this->policyService->update(TenantContext::getId(), $this->getAllInput()));
    }

    public function assignReview(int $id): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $assigneeId = $this->inputInt('assigned_to', null, 1);
        $review = $this->workflowService->assignReview(TenantContext::getId(), $id, $assigneeId);
        if (!$review) {
            return $this->respondWithError('NOT_FOUND', __('api.caring_review_assignment_failed'), null, 404);
        }

        return $this->respondWithData([
            'review' => $review,
            'message' => __('api.caring_review_assigned'),
        ]);
    }

    public function escalateReview(int $id): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $review = $this->workflowService->escalateReview(TenantContext::getId(), $id, (string) request()->input('note', ''));
        if (!$review) {
            return $this->respondWithError('NOT_FOUND', __('api.caring_review_escalation_failed'), null, 404);
        }

        return $this->respondWithData([
            'review' => $review,
            'message' => __('api.caring_review_escalated'),
        ]);
    }

    private function guardCaringCommunity(): ?JsonResponse
    {
        $this->requireAdmin();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        return null;
    }
}
