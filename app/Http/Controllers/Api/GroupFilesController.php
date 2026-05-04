<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use App\Services\GroupFileService;
use App\Services\GroupService;

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
            return $this->error($errors[0]['message'] ?? __('errors.group_files.listing_failed'), $code);
        }

        return $this->respondWithData($result);
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
            return $this->error(__('api.group_file_required'), 400);
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
            return $this->error($errors[0]['message'] ?? __('errors.group_files.upload_failed'), $code);
        }

        return $this->respondWithData($result, null, 201);
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

        $result = $this->fileService->download($groupId, $fileId, $userId);

        if ($result === null) {
            $errors = $this->fileService->getErrors();
            $code = match ($errors[0]['code'] ?? '') {
                'NOT_FOUND' => 404,
                'FORBIDDEN' => 403,
                default => 400,
            };
            return $this->error($errors[0]['message'] ?? __('errors.group_files.download_failed'), $code);
        }

        $disk = Storage::disk('local');
        if (!$disk->exists($result['file_path'])) {
            return $this->error(__('api.group_file_missing_on_disk'), 404);
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

        $success = $this->fileService->delete($groupId, $fileId, $userId);

        if (!$success) {
            $errors = $this->fileService->getErrors();
            $code = match ($errors[0]['code'] ?? '') {
                'NOT_FOUND' => 404,
                'FORBIDDEN' => 403,
                default => 400,
            };
            return $this->error($errors[0]['message'] ?? __('errors.group_files.delete_failed'), $code);
        }

        return $this->respondWithData(['message' => __('api_controllers_1.group_files.file_deleted')]);
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

        if (!GroupService::isActiveMember($groupId, $userId) && !GroupService::canModify($groupId, $userId)) {
            return $this->respondWithError('FORBIDDEN', __('api.group_files_member_required'), null, 403);
        }

        $folders = $this->fileService->getFolders($groupId);
        return $this->respondWithData($folders);
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

        if (!GroupService::isActiveMember($groupId, $userId) && !GroupService::canModify($groupId, $userId)) {
            return $this->respondWithError('FORBIDDEN', __('api.group_files_member_required'), null, 403);
        }

        $stats = $this->fileService->getStats($groupId);
        return $this->respondWithData($stats);
    }
}
