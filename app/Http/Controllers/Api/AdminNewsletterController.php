<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;


/**
 * AdminNewsletterController -- Newsletter campaign management.
 */
class AdminNewsletterController extends BaseApiController
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

    public function campaigns(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'campaigns');
    }

    public function show(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'show', func_get_args());
    }

    public function create(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'create');
    }

    public function send(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'send', func_get_args());
    }

    public function stats(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'stats', func_get_args());
    }

    public function index(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'index');
    }

    public function store(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'store');
    }

    public function subscribers(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'subscribers');
    }

    public function addSubscriber(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'addSubscriber');
    }

    public function importSubscribers(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'importSubscribers');
    }

    public function exportSubscribers(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'exportSubscribers');
    }

    public function syncPlatformMembers(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'syncPlatformMembers');
    }

    public function removeSubscriber($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'removeSubscriber', func_get_args());
    }

    public function segments(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'segments');
    }

    public function storeSegment(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'storeSegment');
    }

    public function previewSegment(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'previewSegment');
    }

    public function getSegmentSuggestions(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'getSegmentSuggestions');
    }

    public function showSegment($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'showSegment', func_get_args());
    }

    public function updateSegment($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'updateSegment', func_get_args());
    }

    public function destroySegment($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'destroySegment', func_get_args());
    }

    public function templates(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'templates');
    }

    public function storeTemplate(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'storeTemplate');
    }

    public function showTemplate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'showTemplate', func_get_args());
    }

    public function updateTemplate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'updateTemplate', func_get_args());
    }

    public function destroyTemplate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'destroyTemplate', func_get_args());
    }

    public function duplicateTemplate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'duplicateTemplate', func_get_args());
    }

    public function previewTemplate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'previewTemplate', func_get_args());
    }

    public function analytics(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'analytics');
    }

    public function getBounces(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'getBounces');
    }

    public function getSuppressionList(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'getSuppressionList');
    }

    public function unsuppress($email): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'unsuppress', func_get_args());
    }

    public function suppress($email): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'suppress', func_get_args());
    }

    public function getSendTimeData(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'getSendTimeData');
    }

    public function getDiagnostics(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'getDiagnostics');
    }

    public function getBounceTrends(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'getBounceTrends');
    }

    public function recipientCount(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'recipientCount');
    }

    public function getResendInfo($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'getResendInfo', func_get_args());
    }

    public function resend($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'resend', func_get_args());
    }

    public function sendNewsletter($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'sendNewsletter', func_get_args());
    }

    public function sendTest($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'sendTest', func_get_args());
    }

    public function duplicateNewsletter($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'duplicateNewsletter', func_get_args());
    }

    public function activity($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'activity', func_get_args());
    }

    public function openers($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'openers', func_get_args());
    }

    public function clickers($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'clickers', func_get_args());
    }

    public function nonOpeners($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'nonOpeners', func_get_args());
    }

    public function openersNoClick($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'openersNoClick', func_get_args());
    }

    public function emailClients($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'emailClients', func_get_args());
    }

    public function selectAbWinner($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'selectAbWinner', func_get_args());
    }

    public function update($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'update', func_get_args());
    }

    public function destroy($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'destroy', func_get_args());
    }
}
