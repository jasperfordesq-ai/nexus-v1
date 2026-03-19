<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\NewsletterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * NewsletterController — Newsletter management and distribution.
 *
 * All endpoints migrated to native DB facade — no legacy delegation.
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
     * POST /api/v2/newsletter/unsubscribe
     *
     * Processes a newsletter unsubscribe using a token from an email link.
     * No authentication required — token acts as the credential.
     *
     * Body: { "token": "..." }
     * Returns: { "success": true } or { "success": false, "message": "..." }
     */
    public function unsubscribe(): JsonResponse
    {
        $input = $this->getAllInput();
        $token = trim($input['token'] ?? $this->query('token', ''));

        if (empty($token)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Unsubscribe token is required.', 'token', 400);
        }

        $subscriber = DB::table('newsletter_subscribers')
            ->where('unsubscribe_token', $token)
            ->first();

        if (! $subscriber) {
            return $this->respondWithError('NOT_FOUND', 'This unsubscribe link is invalid or has already been used.', null, 404);
        }

        if ($subscriber->status === 'unsubscribed') {
            return $this->respondWithData([
                'message' => 'You are already unsubscribed.',
                'already_done' => true,
            ]);
        }

        $updated = DB::table('newsletter_subscribers')
            ->where('unsubscribe_token', $token)
            ->update([
                'status'             => 'unsubscribed',
                'unsubscribed_at'    => now(),
                'unsubscribe_reason' => 'email_link',
            ]);

        if ($updated) {
            return $this->respondWithData(['unsubscribed' => true]);
        }

        return $this->respondWithError('SERVER_ERROR', 'Unable to process your request. Please try again.', null, 500);
    }
}
