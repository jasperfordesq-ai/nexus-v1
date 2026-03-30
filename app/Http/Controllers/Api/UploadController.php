<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;

/**
 * UploadController — File/image upload handling.
 */
class UploadController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly ImageUploadService $uploadService,
    ) {}

    /**
     * POST /api/v2/upload
     *
     * Upload an image file. Accepts multipart/form-data with a 'file' field.
     * Optional: type (avatar|cover|listing|post), max_width, max_height.
     */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('upload', 20, 60);

        // Validate file upload. SVG is intentionally excluded (XSS vector).
        // Non-image types are allowed for document uploads (CV, resources).
        request()->validate([
            'file' => 'required|file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv,txt,mp3,mp4,wav',
        ]);

        $file = request()->file('file');

        if (!$file || !$file->isValid()) {
            return $this->respondWithError('UPLOAD_INVALID', __('api.no_valid_file_provided'), 'file', 422);
        }

        // Double-check MIME type via file content inspection (not just extension)
        // Blocks HTML/SVG/PHP disguised as allowed extensions
        $detectedMime = $file->getMimeType();
        $blockedMimes = ['text/html', 'application/xhtml+xml', 'image/svg+xml', 'application/x-httpd-php'];
        if ($detectedMime && in_array($detectedMime, $blockedMimes, true)) {
            return $this->respondWithError('UPLOAD_BLOCKED', __('api.file_type_blocked'), 'file', 422);
        }

        $type = $this->input('type', 'general');
        $tenantId = $this->getTenantId();

        $result = $this->uploadService->upload($file, $userId, $tenantId, $type);

        if (isset($result['error'])) {
            return $this->respondWithError('UPLOAD_FAILED', $result['error'], null, 422);
        }

        return $this->respondWithData($result, null, 201);
    }
}
