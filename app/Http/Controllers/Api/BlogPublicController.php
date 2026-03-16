<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * BlogPublicController -- Public blog posts and categories.
 *
 * Delegates to legacy: BlogPublicApiController
 */
class BlogPublicController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** GET /api/v2/blog */
    public function index(): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\BlogPublicApiController();
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

    /** GET /api/v2/blog/categories */
    public function categories(): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\BlogPublicApiController();
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

    /** GET /api/v2/blog/slug */
    public function show(string $slug): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\BlogPublicApiController();
            $controller->show($slug);
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
