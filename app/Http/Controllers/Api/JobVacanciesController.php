<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;


/**
 * JobVacanciesController — Community job vacancy listings.
 */
class JobVacanciesController extends BaseApiController
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
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'index');
    }

    public function show(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'show', func_get_args());
    }

    public function store(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'store');
    }

    public function apply(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'apply', func_get_args());
    }

    public function savedJobs(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'savedJobs');
    }

    public function myApplications(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'myApplications');
    }

    public function myPostings(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'myPostings');
    }

    public function listAlerts(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'listAlerts');
    }

    public function createAlert(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'createAlert');
    }

    public function deleteAlert($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'deleteAlert', func_get_args());
    }

    public function unsubscribeAlert($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'unsubscribeAlert', func_get_args());
    }

    public function resubscribeAlert($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'resubscribeAlert', func_get_args());
    }

    public function update($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'update', func_get_args());
    }

    public function destroy($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'destroy', func_get_args());
    }

    public function saveJob($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'saveJob', func_get_args());
    }

    public function unsaveJob($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'unsaveJob', func_get_args());
    }

    public function matchPercentage($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'matchPercentage', func_get_args());
    }

    public function qualificationAssessment($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'qualificationAssessment', func_get_args());
    }

    public function applications($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'applications', func_get_args());
    }

    public function analytics($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'analytics', func_get_args());
    }

    public function renewJob($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'renewJob', func_get_args());
    }

    public function featureJob($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'featureJob', func_get_args());
    }

    public function unfeatureJob($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'unfeatureJob', func_get_args());
    }

    public function updateApplication($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'updateApplication', func_get_args());
    }

    public function applicationHistory($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'applicationHistory', func_get_args());
    }
}
