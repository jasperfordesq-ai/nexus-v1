<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use App\Services\GroupFileService;

/**
 * GroupFilesController — File upload, download, list, and delete for groups.
 */
class GroupFilesController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly GroupFileService $fileService,
    ) {}

    /**
     * GET /api/v2/groups/{groupId}/files
     */
    public function index(int $groupId): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
            'cursor' => $this->query('cursor'),
            'folder' => $this->query('folder'),
            'search' => $this->query('q'),
        ];

        $result = $this->fileService->list($groupId, $userId, $filters);

        if ($result === null) {
            $errors = $this->fileService->getErrors();
            $code = ($errors[0]['code'] ?? '') === 'FORBIDDEN' ? 403 : 400;
            return $this->errorResponse($errors[0]['message'] ?? 'Error listing files', $code);
        }

        return $this->successResponse($result);
    }

    /**
     * POST /api/v2/groups/{groupId}/files
     */
    public function store(int $groupId): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $request = request();
        $file = $request->file('file');

        if (!$file) {
            return $this->errorResponse('No file provided', 400);
        }

        $result = $this->fileService->upload($groupId, $userId, [
            'file' => $file,
            'folder' => $request->input('folder'),
            'description' => $request->input('description'),
        ]);

        if ($result === null) {
            $errors = $this->fileService->getErrors();
            $code = match ($errors[0]['code'] ?? '') {
                'FORBIDDEN' => 403,
                'FILE_TOO_LARGE', 'INVALID_TYPE', 'INVALID_FILE' => 422,
                default => 400,
            };
            return $this->errorResponse($errors[0]['message'] ?? 'Upload failed', $code);
        }

        return $this->successResponse($result, 201);
    }

    /**
     * GET /api/v2/groups/{groupId}/files/{fileId}/download
     */
    public function download(int $groupId, int $fileId): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $result = $this->fileService->download($fileId, $userId);

        if ($result === null) {
            $errors = $this->fileService->getErrors();
            $code = match ($errors[0]['code'] ?? '') {
                'NOT_FOUND' => 404,
                'FORBIDDEN' => 403,
                default => 400,
            };
            return $this->errorResponse($errors[0]['message'] ?? 'Download failed', $code);
        }

        $disk = Storage::disk('local');
        if (!$disk->exists($result['file_path'])) {
            return $this->errorResponse('File not found on disk', 404);
        }

        return $disk->download(
            $result['file_path'],
            $result['file_name'],
            ['Content-Type' => $result['file_type']]
        );
    }

    /**
     * DELETE /api/v2/groups/{groupId}/files/{fileId}
     */
    public function destroy(int $groupId, int $fileId): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $success = $this->fileService->delete($fileId, $userId);

        if (!$success) {
            $errors = $this->fileService->getErrors();
            $code = match ($errors[0]['code'] ?? '') {
                'NOT_FOUND' => 404,
                'FORBIDDEN' => 403,
                default => 400,
            };
            return $this->errorResponse($errors[0]['message'] ?? 'Delete failed', $code);
        }

        return $this->successResponse(['message' => 'File deleted']);
    }

    /**
     * GET /api/v2/groups/{groupId}/files/folders
     */
    public function folders(int $groupId): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $folders = $this->fileService->getFolders($groupId);
        return $this->successResponse($folders);
    }

    /**
     * GET /api/v2/groups/{groupId}/files/stats
     */
    public function stats(int $groupId): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $stats = $this->fileService->getStats($groupId);
        return $this->successResponse($stats);
    }
}
