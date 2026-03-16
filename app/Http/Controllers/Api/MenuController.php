<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * MenuController -- Navigation menu management.
 *
 * Delegates to legacy: MenuApiController
 */
class MenuController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** GET menus */
    public function index(): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\MenuApiController();
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

    /** GET menus/slug */
    public function show(string $slug): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\MenuApiController();
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

    /** GET menus/config */
    public function config(): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\MenuApiController();
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

    /** GET menus/mobile */
    public function mobile(): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\MenuApiController();
            $controller->mobile();
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

    /** POST menus/clear-cache */
    public function clearCache(): JsonResponse
    {
        $this->requireAdmin();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\MenuApiController();
            $controller->clearCache();
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
