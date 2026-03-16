<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * ResourcePublicController -- Public resource library.
 *
 * Delegates to legacy: ResourcesPublicApiController
 */
class ResourcePublicController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** GET resources */
    public function index(): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\ResourcesPublicApiController();
            $controller->index();
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

    /** GET resources/categories */
    public function categories(): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\ResourcesPublicApiController();
            $controller->categories();
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

    /** POST resources */
    public function store(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\ResourcesPublicApiController();
            $controller->store();
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

    /** GET resources/download */
    public function download(int $id): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\ResourcesPublicApiController();
            $controller->download($id);
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

    /** DELETE resources/id */
    public function destroy(int $id): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\ResourcesPublicApiController();
            $controller->destroy($id);
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
