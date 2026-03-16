<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * SocialController -- Social feed posts, likes, polls.
 *
 * Delegates to legacy: SocialApiController
 */
class SocialController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** GET feed */
    public function feedV2(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\SocialApiController();
            $controller->feedV2();
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

    /** POST feed/posts */
    public function createPostV2(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\SocialApiController();
            $controller->createPostV2();
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

    /** POST feed/like */
    public function likeV2(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\SocialApiController();
            $controller->likeV2();
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

    /** POST feed/posts/hide */
    public function hidePostV2(int $id): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\SocialApiController();
            $controller->hidePostV2($id);
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

    /** POST feed/posts/delete */
    public function deletePostV2(int $id): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\SocialApiController();
            $controller->deletePostV2($id);
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

    /** POST feed/posts/impression */
    public function recordImpression(int $id): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\SocialApiController();
            $controller->recordImpression($id);
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
