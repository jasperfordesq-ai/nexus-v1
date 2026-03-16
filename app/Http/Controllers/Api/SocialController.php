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


    public function createPollV2(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'createPollV2');
    }


    public function getPollV2($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'getPollV2', [$id]);
    }


    public function votePollV2($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'votePollV2', [$id]);
    }


    public function reportPostV2($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'reportPostV2', [$id]);
    }


    public function muteUserV2($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'muteUserV2', [$id]);
    }


    public function recordClick($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'recordClick', [$id]);
    }


    public function test(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'test');
    }


    public function like(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'like');
    }


    public function likers(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'likers');
    }


    public function comments(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'comments');
    }


    public function share(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'share');
    }


    public function delete(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'delete');
    }


    public function reaction(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'reaction');
    }


    public function reply(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'reply');
    }


    public function editComment(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'editComment');
    }


    public function deleteComment(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'deleteComment');
    }


    public function mentionSearch(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'mentionSearch');
    }


    public function feed(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'feed');
    }


    public function createPost(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'createPost');
    }

}
