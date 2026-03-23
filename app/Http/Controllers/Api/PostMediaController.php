<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\PostMediaService;
use Illuminate\Http\JsonResponse;

/**
 * PostMediaController — Manages media attachments for feed posts.
 *
 * Endpoints:
 *   POST   /api/v2/posts/{id}/media           uploadMedia()
 *   PUT    /api/v2/posts/{id}/media/reorder    reorderMedia()
 *   DELETE /api/v2/posts/media/{mediaId}       removeMedia()
 *   PUT    /api/v2/posts/media/{mediaId}/alt   updateAltText()
 */
class PostMediaController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly PostMediaService $postMediaService,
    ) {}

    /**
     * POST /api/v2/posts/{id}/media
     *
     * Upload additional media to an existing post.
     * Accepts multipart/form-data with media[] files.
     */
    public function uploadMedia(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('post_media_upload', 30, 60);

        // Verify post ownership
        if (!$this->postMediaService->isPostOwnedByUser($id, $userId)) {
            return $this->respondWithError('FORBIDDEN', 'You can only add media to your own posts', null, 403);
        }

        $request = request();

        // Collect files from request (support both media[] and individual media_0, media_1, etc.)
        $files = [];

        if ($request->hasFile('media')) {
            $mediaFiles = $request->file('media');
            if (is_array($mediaFiles)) {
                $files = array_merge($files, $mediaFiles);
            } else {
                $files[] = $mediaFiles;
            }
        }

        // Also check for numbered media fields (media_0, media_1, etc.)
        for ($i = 0; $i < 10; $i++) {
            $key = "media_{$i}";
            if ($request->hasFile($key)) {
                $files[] = $request->file($key);
            }
        }

        // Also support 'image' field for backward compat with single-image uploads
        if (empty($files) && $request->hasFile('image')) {
            $files[] = $request->file('image');
        }

        if (empty($files)) {
            return $this->respondWithError('VALIDATION_ERROR', 'No media files provided', 'media', 422);
        }

        $media = $this->postMediaService->attachMedia($id, $files);

        if (empty($media)) {
            return $this->respondWithError('UPLOAD_FAILED', 'No files were uploaded successfully. Maximum 10 images per post.', null, 422);
        }

        return $this->respondWithData($media, null, 201);
    }

    /**
     * PUT /api/v2/posts/{id}/media/reorder
     *
     * Reorder media items for a post.
     * Body: { "media_ids": [3, 1, 2] }
     */
    public function reorderMedia(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        if (!$this->postMediaService->isPostOwnedByUser($id, $userId)) {
            return $this->respondWithError('FORBIDDEN', 'You can only reorder media on your own posts', null, 403);
        }

        $mediaIds = $this->input('media_ids');

        if (!is_array($mediaIds) || empty($mediaIds)) {
            return $this->respondWithError('VALIDATION_ERROR', 'media_ids must be a non-empty array', 'media_ids', 422);
        }

        $this->postMediaService->reorderMedia($id, $mediaIds);

        return $this->respondWithData(['success' => true]);
    }

    /**
     * DELETE /api/v2/posts/media/{mediaId}
     *
     * Remove a single media item.
     */
    public function removeMedia(int $mediaId): JsonResponse
    {
        $userId = $this->requireAuth();

        if (!$this->postMediaService->isMediaOwnedByUser($mediaId, $userId)) {
            return $this->respondWithError('FORBIDDEN', 'You can only remove your own media', null, 403);
        }

        $this->postMediaService->removeMedia($mediaId);

        return $this->respondWithData(['success' => true]);
    }

    /**
     * PUT /api/v2/posts/media/{mediaId}/alt
     *
     * Update alt text for accessibility.
     * Body: { "alt_text": "Description of the image" }
     */
    public function updateAltText(int $mediaId): JsonResponse
    {
        $userId = $this->requireAuth();

        if (!$this->postMediaService->isMediaOwnedByUser($mediaId, $userId)) {
            return $this->respondWithError('FORBIDDEN', 'You can only update your own media', null, 403);
        }

        $altText = $this->input('alt_text', '');

        if (!is_string($altText)) {
            return $this->respondWithError('VALIDATION_ERROR', 'alt_text must be a string', 'alt_text', 422);
        }

        $this->postMediaService->updateAltText($mediaId, $altText);

        return $this->respondWithData(['success' => true]);
    }
}
