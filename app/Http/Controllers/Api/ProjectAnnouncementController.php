<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\CaringCommunity\ProjectAnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;

/**
 * AG69 — Multi-stage project announcement tracking.
 */
class ProjectAnnouncementController extends BaseApiController
{
    protected bool $isV2Api = true;

    private function assertFeatureEnabled(): ?JsonResponse
    {
        if (! TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        if (! ProjectAnnouncementService::isAvailable()) {
            return $this->respondWithError('SERVICE_UNAVAILABLE', __('api.service_unavailable'), null, 503);
        }

        return null;
    }

    public function index(): JsonResponse
    {
        $tenantId = TenantContext::getId();

        if ($err = $this->assertFeatureEnabled()) {
            return $err;
        }

        try {
            return $this->respondWithData(ProjectAnnouncementService::listPublished($tenantId));
        } catch (RuntimeException $e) {
            return $this->respondWithError('SERVICE_ERROR', $e->getMessage(), null, 503);
        }
    }

    public function show(int $id): JsonResponse
    {
        $tenantId = TenantContext::getId();

        if ($err = $this->assertFeatureEnabled()) {
            return $err;
        }

        $viewerId = request()->user()?->id;
        $project = ProjectAnnouncementService::getProject($id, $tenantId, false, $viewerId ? (int) $viewerId : null);

        if ($project === null) {
            return $this->respondWithError('NOT_FOUND', __('api.caring_project_not_found'), null, 404);
        }

        return $this->respondWithData($project);
    }

    public function subscribe(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        if ($err = $this->assertFeatureEnabled()) {
            return $err;
        }

        try {
            ProjectAnnouncementService::subscribe($id, $tenantId, $userId);
            return $this->respondWithData(['ok' => true]);
        } catch (RuntimeException $e) {
            return $this->respondWithError('SERVICE_ERROR', $e->getMessage(), null, 404);
        }
    }

    public function unsubscribe(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        if ($err = $this->assertFeatureEnabled()) {
            return $err;
        }

        ProjectAnnouncementService::unsubscribe($id, $tenantId, $userId);

        return $this->respondWithData(['ok' => true]);
    }

    public function adminIndex(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        if ($err = $this->assertFeatureEnabled()) {
            return $err;
        }

        if (! $this->hasAnnouncerAccess($userId, $tenantId)) {
            return $this->respondWithError('FORBIDDEN', __('api.forbidden'), null, 403);
        }

        $status = request()->query('status');
        if (! is_string($status) || ! in_array($status, ['draft', 'active', 'paused', 'completed', 'cancelled'], true)) {
            $status = null;
        }

        return $this->respondWithData(ProjectAnnouncementService::listAdmin($tenantId, $status));
    }

    public function adminShow(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        if ($err = $this->assertFeatureEnabled()) {
            return $err;
        }

        if (! $this->hasAnnouncerAccess($userId, $tenantId)) {
            return $this->respondWithError('FORBIDDEN', __('api.forbidden'), null, 403);
        }

        $project = ProjectAnnouncementService::getProject($id, $tenantId, true);

        if ($project === null) {
            return $this->respondWithError('NOT_FOUND', __('api.caring_project_not_found'), null, 404);
        }

        return $this->respondWithData($project);
    }

    public function adminStore(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        if ($err = $this->assertFeatureEnabled()) {
            return $err;
        }

        if (! $this->hasAnnouncerAccess($userId, $tenantId)) {
            return $this->respondWithError('FORBIDDEN', __('api.forbidden'), null, 403);
        }

        $input = $this->getAllInput();
        $validator = $this->projectValidator($input, true);

        if ($validator->fails()) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                __('api.validation_failed'),
                $validator->errors()->toJson(),
                422,
            );
        }

        try {
            $project = ProjectAnnouncementService::createProject($tenantId, $userId, $input);
            return $this->respondWithData($project, null, 201);
        } catch (InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (RuntimeException $e) {
            return $this->respondWithError('SERVICE_ERROR', $e->getMessage(), null, 503);
        }
    }

    public function adminUpdate(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        if ($err = $this->assertFeatureEnabled()) {
            return $err;
        }

        if (! $this->hasAnnouncerAccess($userId, $tenantId)) {
            return $this->respondWithError('FORBIDDEN', __('api.forbidden'), null, 403);
        }

        $input = $this->getAllInput();
        $validator = $this->projectValidator($input, false);

        if ($validator->fails()) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                __('api.validation_failed'),
                $validator->errors()->toJson(),
                422,
            );
        }

        try {
            return $this->respondWithData(ProjectAnnouncementService::updateProject($id, $tenantId, $input));
        } catch (InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (RuntimeException $e) {
            return $this->respondWithError('SERVICE_ERROR', $e->getMessage(), null, 404);
        }
    }

    public function adminPublish(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        if ($err = $this->assertFeatureEnabled()) {
            return $err;
        }

        if (! $this->hasAnnouncerAccess($userId, $tenantId)) {
            return $this->respondWithError('FORBIDDEN', __('api.forbidden'), null, 403);
        }

        try {
            return $this->respondWithData(ProjectAnnouncementService::publishProject($id, $tenantId));
        } catch (RuntimeException $e) {
            return $this->respondWithError('SERVICE_ERROR', $e->getMessage(), null, 404);
        }
    }

    public function adminCreateUpdate(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        if ($err = $this->assertFeatureEnabled()) {
            return $err;
        }

        if (! $this->hasAnnouncerAccess($userId, $tenantId)) {
            return $this->respondWithError('FORBIDDEN', __('api.forbidden'), null, 403);
        }

        $input = $this->getAllInput();
        $validator = Validator::make($input, [
            'title' => 'required|string|max:255',
            'body' => 'nullable|string|max:5000',
            'stage_label' => 'nullable|string|max:120',
            'progress_percent' => 'nullable|integer|min:0|max:100',
            'is_milestone' => 'nullable|boolean',
            'status' => 'nullable|in:draft,published',
        ]);

        if ($validator->fails()) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                __('api.validation_failed'),
                $validator->errors()->toJson(),
                422,
            );
        }

        try {
            $update = ProjectAnnouncementService::createUpdate($id, $tenantId, $userId, $input);
            return $this->respondWithData($update, null, 201);
        } catch (InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (RuntimeException $e) {
            return $this->respondWithError('SERVICE_ERROR', $e->getMessage(), null, 404);
        }
    }

    public function adminPublishUpdate(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        if ($err = $this->assertFeatureEnabled()) {
            return $err;
        }

        if (! $this->hasAnnouncerAccess($userId, $tenantId)) {
            return $this->respondWithError('FORBIDDEN', __('api.forbidden'), null, 403);
        }

        try {
            return $this->respondWithData(ProjectAnnouncementService::publishUpdate($id, $tenantId));
        } catch (RuntimeException $e) {
            return $this->respondWithError('SERVICE_ERROR', $e->getMessage(), null, 404);
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return \Illuminate\Validation\Validator
     */
    private function projectValidator(array $input, bool $creating): \Illuminate\Validation\Validator
    {
        return Validator::make($input, [
            'title' => ($creating ? 'required' : 'nullable') . '|string|max:255',
            'summary' => 'nullable|string|max:5000',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|in:draft,active,paused,completed,cancelled',
            'current_stage' => 'nullable|string|max:120',
            'progress_percent' => 'nullable|integer|min:0|max:100',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
        ]);
    }

    private function hasAnnouncerAccess(int $userId, int $tenantId): bool
    {
        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->first(['role', 'is_admin', 'is_super_admin', 'is_tenant_super_admin', 'is_god']);

        if ($user && (
            in_array((string) $user->role, ['admin', 'tenant_admin', 'super_admin', 'god'], true)
            || (int) ($user->is_admin ?? 0) === 1
            || (int) ($user->is_super_admin ?? 0) === 1
            || (int) ($user->is_tenant_super_admin ?? 0) === 1
            || (int) ($user->is_god ?? 0) === 1
        )) {
            return true;
        }

        if (! Schema::hasTable('user_roles') || ! Schema::hasTable('roles')) {
            return false;
        }

        return (bool) DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $userId)
            ->where('user_roles.tenant_id', $tenantId)
            ->whereIn('roles.name', ['admin', 'municipality_announcer'])
            ->exists();
    }
}
