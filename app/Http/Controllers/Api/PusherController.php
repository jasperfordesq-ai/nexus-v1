<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * PusherController -- Pusher realtime auth and config.
 *
 * Delegates to legacy: PusherAuthController
 */
class PusherController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** POST pusher/auth */
    public function auth(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\PusherAuthController();
            $controller->auth();
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

    /** GET pusher/config */
    public function config(): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\PusherAuthController();
            $controller->config();
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
}
