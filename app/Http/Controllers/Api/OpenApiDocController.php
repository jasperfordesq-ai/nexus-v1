<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * OpenApiDocController -- OpenAPI documentation endpoints.
 *
 * Delegates to legacy: OpenApiController
 */
class OpenApiDocController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** GET docs/openapi.json */
    public function json(): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\OpenApiController();
            $controller->json();
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

    /** GET docs/openapi.yaml */
    public function yaml(): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\OpenApiController();
            $controller->yaml();
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

    /** GET docs */
    public function ui(): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\OpenApiController();
            $controller->ui();
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
