<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;


/**
 * GoalsController -- CRUD and progress tracking for member goals.
 */
class GoalsController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * Delegate to legacy controller via output buffering.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }

    public function index(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalsApiController::class, 'index');
    }

    public function show(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalsApiController::class, 'show', func_get_args());
    }

    public function store(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalsApiController::class, 'store');
    }

    public function progress(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalsApiController::class, 'progress', func_get_args());
    }

    public function complete(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalsApiController::class, 'complete', func_get_args());
    }

    public function discover(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalsApiController::class, 'discover');
    }

    public function mentoring(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalsApiController::class, 'mentoring');
    }

    public function templates(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalsApiController::class, 'templates');
    }

    public function templateCategories(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalsApiController::class, 'templateCategories');
    }

    public function createTemplate(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalsApiController::class, 'createTemplate');
    }

    public function createFromTemplate($templateId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalsApiController::class, 'createFromTemplate', func_get_args());
    }

    public function update($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalsApiController::class, 'update', func_get_args());
    }

    public function destroy($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalsApiController::class, 'destroy', func_get_args());
    }

    public function buddy($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalsApiController::class, 'buddy', func_get_args());
    }

    public function listCheckins($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalsApiController::class, 'listCheckins', func_get_args());
    }

    public function createCheckin($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalsApiController::class, 'createCheckin', func_get_args());
    }

    public function history($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalsApiController::class, 'history', func_get_args());
    }

    public function historySummary($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalsApiController::class, 'historySummary', func_get_args());
    }

    public function getReminder($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalsApiController::class, 'getReminder', func_get_args());
    }

    public function setReminder($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalsApiController::class, 'setReminder', func_get_args());
    }

    public function deleteReminder($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalsApiController::class, 'deleteReminder', func_get_args());
    }

    public function updateProgress(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalApiController::class, 'updateProgress');
    }

    public function offerBuddy(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GoalApiController::class, 'offerBuddy');
    }
}
