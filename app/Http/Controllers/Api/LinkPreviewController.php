<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\LinkPreviewService;
use Illuminate\Http\JsonResponse;

/**
 * LinkPreviewController — API endpoint for fetching Open Graph metadata.
 *
 * Provides a single endpoint that the React frontend's compose editor
 * calls to get link preview data when a URL is detected in post content.
 */
class LinkPreviewController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly LinkPreviewService $linkPreviewService,
    ) {}

    /**
     * GET /api/v2/link-preview?url=...
     *
     * Fetch OG metadata for a URL. Returns cached data when available.
     * Rate limited to 20 requests per minute per user.
     */
    public function show(): JsonResponse
    {
        $this->requireAuth();
        $this->rateLimit('link_preview', 20, 60);

        $url = $this->query('url');

        if (empty($url)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.url_required'), null, 400);
        }

        // Decode in case it was double-encoded
        $url = urldecode($url);

        // Basic URL validation
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_url'), null, 400);
        }

        $preview = $this->linkPreviewService->fetchPreview($url);

        if ($preview === null) {
            return $this->respondWithError('NOT_FOUND', __('api.link_preview_failed'), null, 404);
        }

        return $this->respondWithData($preview);
    }

    /**
     * POST /api/v2/link-preview
     *
     * Fetch OG metadata for a URL via POST body.
     * Rate limited to 20 requests per minute per user.
     */
    public function fetch(): JsonResponse
    {
        $this->requireAuth();
        $this->rateLimit('link_preview', 20, 60);

        $url = $this->input('url');

        if (empty($url)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.url_required'), null, 400);
        }

        // Basic URL validation
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_url'), null, 400);
        }

        // Only allow HTTP and HTTPS schemes
        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.url_http_https_only'), null, 400);
        }

        $preview = $this->linkPreviewService->fetchPreview($url);

        if ($preview === null) {
            return $this->respondWithError('NOT_FOUND', __('api.link_preview_failed'), null, 404);
        }

        return $this->respondWithData($preview);
    }
}
