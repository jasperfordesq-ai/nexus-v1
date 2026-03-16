<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * CoreController -- Legacy core messaging endpoints.
 *
 * Delegates to legacy: CoreApiController
 */
class CoreController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** POST /api/messages/send */
    public function sendMessage(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\CoreApiController();
            $controller->sendMessage();
        } catch (\Throwable $e) {
            ob_end_clean();
            return $this->respondWithError(
                'INTERNAL_ERROR', $e->getMessage(), null, 500
            );
        }
        $output = ob_get_clean();
        $data = json_decode($output, true);

        if ($data === null) {
            return $this->respondWithData([]);
        }

        return response()->json($data);
    }

    /** POST /api/messages/typing */
    public function typing(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\CoreApiController();
            $controller->typing();
        } catch (\Throwable $e) {
            ob_end_clean();
            return $this->respondWithError(
                'INTERNAL_ERROR', $e->getMessage(), null, 500
            );
        }
        $output = ob_get_clean();
        $data = json_decode($output, true);

        if ($data === null) {
            return $this->respondWithData([]);
        }

        return response()->json($data);
    }

    /** GET /api/messages/poll */
    public function pollMessages(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\CoreApiController();
            $controller->pollMessages();
        } catch (\Throwable $e) {
            ob_end_clean();
            return $this->respondWithError(
                'INTERNAL_ERROR', $e->getMessage(), null, 500
            );
        }
        $output = ob_get_clean();
        $data = json_decode($output, true);

        if ($data === null) {
            return $this->respondWithData([]);
        }

        return response()->json($data);
    }

    /** GET /api/messages/unread-count */
    public function unreadMessagesCount(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\CoreApiController();
            $controller->unreadMessagesCount();
        } catch (\Throwable $e) {
            ob_end_clean();
            return $this->respondWithError(
                'INTERNAL_ERROR', $e->getMessage(), null, 500
            );
        }
        $output = ob_get_clean();
        $data = json_decode($output, true);

        if ($data === null) {
            return $this->respondWithData([]);
        }

        return response()->json($data);
    }

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


    public function apiSubmit(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\ContactController::class, 'apiSubmit');
    }


    public function members(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\CoreApiController::class, 'members');
    }


    public function listings(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\CoreApiController::class, 'listings');
    }


    public function groups(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\CoreApiController::class, 'groups');
    }


    public function messages(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\CoreApiController::class, 'messages');
    }


    public function notifications(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\CoreApiController::class, 'notifications');
    }


    public function checkNotifications(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\CoreApiController::class, 'checkNotifications');
    }


    public function unreadCount(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\CoreApiController::class, 'unreadCount');
    }

}
