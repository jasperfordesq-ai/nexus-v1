<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\NewsletterService;
use Illuminate\Http\JsonResponse;

/**
 * NewsletterController — Newsletter management and distribution.
 */
class NewsletterController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly NewsletterService $newsletterService,
    ) {}

    /** GET /api/v2/newsletters */
    public function index(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $status = $this->query('status');

        $result = $this->newsletterService->getAll($tenantId, $page, $perPage, $status);

        return $this->respondWithPaginatedCollection(
            $result['items'],
            $result['total'],
            $page,
            $perPage
        );
    }

    /** GET /api/v2/newsletters/{id} */
    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $newsletter = $this->newsletterService->getById($id, $tenantId);

        if ($newsletter === null) {
            return $this->respondWithError('NOT_FOUND', 'Newsletter not found', null, 404);
        }

        return $this->respondWithData($newsletter);
    }

    /** POST /api/v2/newsletters */
    public function store(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $this->rateLimit('newsletter_create', 5, 60);

        $data = $this->getAllInput();

        $newsletter = $this->newsletterService->create($tenantId, $data);

        return $this->respondWithData($newsletter, null, 201);
    }

    /**
     * POST /api/v2/newsletters/{id}/send
     *
     * Send/queue a newsletter for distribution (admin only).
     */
    public function send(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $this->rateLimit('newsletter_send', 2, 300);

        $result = $this->newsletterService->send($id, $tenantId);

        if ($result === null) {
            return $this->respondWithError('NOT_FOUND', 'Newsletter not found', null, 404);
        }

        return $this->respondWithData($result);
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


    public function unsubscribe(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\NewsletterApiController::class, 'unsubscribe');
    }

}
