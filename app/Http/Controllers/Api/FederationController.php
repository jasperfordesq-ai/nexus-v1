<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * FederationController -- Federation cross-tenant features.
 *
 * Delegates to legacy: FederationV2ApiController
 */
class FederationController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** GET federation/status */
    public function status(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\FederationV2ApiController();
            $controller->status();
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

    /** POST federation/opt-in */
    public function optIn(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\FederationV2ApiController();
            $controller->optIn();
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

    /** POST federation/setup */
    public function setup(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\FederationV2ApiController();
            $controller->setup();
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

    /** POST federation/opt-out */
    public function optOut(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\FederationV2ApiController();
            $controller->optOut();
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

    /** GET federation/partners */
    public function partners(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\FederationV2ApiController();
            $controller->partners();
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

    /** GET federation/activity */
    public function activity(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\FederationV2ApiController();
            $controller->activity();
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


    public function index(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationApiController::class, 'index');
    }


    public function timebanks(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationApiController::class, 'timebanks');
    }


    public function members(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationApiController::class, 'members');
    }


    public function member($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationApiController::class, 'member', [$id]);
    }


    public function listings(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationApiController::class, 'listings');
    }


    public function listing($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationApiController::class, 'listing', [$id]);
    }


    public function sendMessage(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationApiController::class, 'sendMessage');
    }


    public function createTransaction(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationApiController::class, 'createTransaction');
    }


    public function oauthToken(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationApiController::class, 'oauthToken');
    }


    public function testWebhook(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationApiController::class, 'testWebhook');
    }

}
