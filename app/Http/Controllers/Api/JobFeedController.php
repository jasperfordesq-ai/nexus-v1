<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\JobFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * JobFeedController — Public RSS/XML and JSON job feeds for aggregator syndication.
 *
 * These endpoints are public (no auth required) but tenant-scoped via subdomain/header.
 * Designed for consumption by Google Jobs, Indeed, and other job aggregators.
 */
class JobFeedController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly JobFeedService $feedService,
    ) {}

    /**
     * GET /api/v2/jobs/feed.xml — RSS 2.0 feed of open job vacancies.
     *
     * Returns XML with Content-Type: application/rss+xml.
     * Cached for 15 minutes per tenant.
     */
    public function rssFeed(): Response
    {
        $tenantId = TenantContext::getId();

        if (!TenantContext::hasFeature('job_vacancies')) {
            return response('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Feature Disabled</title></channel></rss>', 403)
                ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
        }

        $xml = $this->feedService->generateRssFeed($tenantId);

        return response($xml, 200)
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=900');
    }

    /**
     * GET /api/v2/jobs/feed.json — JSON feed with Google Jobs / Schema.org JobPosting format.
     *
     * Returns structured JSON for search engine consumption.
     * Cached for 15 minutes per tenant.
     */
    public function jsonFeed(): JsonResponse
    {
        $tenantId = TenantContext::getId();

        if (!TenantContext::hasFeature('job_vacancies')) {
            return $this->respondWithError('FEATURE_DISABLED', 'Job Vacancies module is not enabled for this community', null, 403);
        }

        $data = $this->feedService->generateJsonFeed($tenantId);

        return response()->json($data, 200, [
            'Cache-Control' => 'public, max-age=900',
        ]);
    }
}
