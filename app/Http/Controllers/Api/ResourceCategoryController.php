<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * ResourceCategoryController -- Resource category tree management.
 *
 * Delegates to legacy: ResourceCategoriesApiController
 */
class ResourceCategoryController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** GET resources/categories/tree */
    public function tree(): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\ResourceCategoriesApiController();
            $controller->tree();
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

    /** POST resources/categories */
    public function store(): JsonResponse
    {
        $this->requireAdmin();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\ResourceCategoriesApiController();
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

    /** PUT resources/categories/id */
    public function update(int $id): JsonResponse
    {
        $this->requireAdmin();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\ResourceCategoriesApiController();
            $controller->update($id);
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

    /** DELETE resources/categories/id */
    public function destroy(int $id): JsonResponse
    {
        $this->requireAdmin();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\ResourceCategoriesApiController();
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

    /** PUT resources/reorder */
    public function reorder(): JsonResponse
    {
        $this->requireAdmin();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\ResourceCategoriesApiController();
            $controller->reorder();
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
