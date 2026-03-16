<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * AdminNewsletterController -- Newsletter campaign management.
 *
 * All methods require admin authentication.
 */
class AdminNewsletterController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /** GET /api/v2/admin/newsletter/campaigns */
    public function campaigns(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $offset = ($page - 1) * $perPage;

        $items = DB::select(
            'SELECT * FROM newsletter_campaigns WHERE tenant_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$tenantId, $perPage, $offset]
        );
        $total = DB::selectOne(
            'SELECT COUNT(*) as cnt FROM newsletter_campaigns WHERE tenant_id = ?',
            [$tenantId]
        )->cnt;

        return $this->respondWithPaginatedCollection($items, (int) $total, $page, $perPage);
    }

    /** GET /api/v2/admin/newsletter/campaigns/{id} */
    public function show(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $campaign = DB::selectOne(
            'SELECT * FROM newsletter_campaigns WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );

        if ($campaign === null) {
            return $this->respondWithError('NOT_FOUND', 'Campaign not found', null, 404);
        }

        return $this->respondWithData($campaign);
    }

    /** POST /api/v2/admin/newsletter/campaigns */
    public function create(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $subject = $this->requireInput('subject');
        $body = $this->requireInput('body');
        $audience = $this->input('audience', 'all');

        DB::insert(
            'INSERT INTO newsletter_campaigns (tenant_id, subject, body, audience, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$tenantId, $subject, $body, $audience, 'draft']
        );

        $id = (int) DB::getPdo()->lastInsertId();

        return $this->respondWithData(['id' => $id, 'status' => 'draft'], null, 201);
    }

    /** POST /api/v2/admin/newsletter/campaigns/{id}/send */
    public function send(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $campaign = DB::selectOne(
            'SELECT * FROM newsletter_campaigns WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );

        if ($campaign === null) {
            return $this->respondWithError('NOT_FOUND', 'Campaign not found', null, 404);
        }

        if ($campaign->status === 'sent') {
            return $this->respondWithError('ALREADY_SENT', 'Campaign has already been sent', null, 409);
        }

        DB::update(
            'UPDATE newsletter_campaigns SET status = ?, sent_at = NOW() WHERE id = ? AND tenant_id = ?',
            ['sent', $id, $tenantId]
        );

        return $this->respondWithData(['id' => $id, 'status' => 'sent']);
    }

    /** GET /api/v2/admin/newsletter/campaigns/{id}/stats */
    public function stats(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $campaign = DB::selectOne(
            'SELECT * FROM newsletter_campaigns WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );

        if ($campaign === null) {
            return $this->respondWithError('NOT_FOUND', 'Campaign not found', null, 404);
        }

        $stats = [
            'id' => $id,
            'status' => $campaign->status,
            'sent_at' => $campaign->sent_at ?? null,
            'recipients' => 0,
            'opens' => 0,
            'clicks' => 0,
        ];

        return $this->respondWithData($stats);
    }
}
