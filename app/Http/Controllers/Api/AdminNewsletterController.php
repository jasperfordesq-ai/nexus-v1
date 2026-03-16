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


    public function index(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'index');
    }


    public function store(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'store');
    }


    public function subscribers(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'subscribers');
    }


    public function addSubscriber(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'addSubscriber');
    }


    public function importSubscribers(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'importSubscribers');
    }


    public function exportSubscribers(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'exportSubscribers');
    }


    public function syncPlatformMembers(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'syncPlatformMembers');
    }


    public function removeSubscriber($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'removeSubscriber', [$id]);
    }


    public function segments(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'segments');
    }


    public function storeSegment(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'storeSegment');
    }


    public function previewSegment(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'previewSegment');
    }


    public function getSegmentSuggestions(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'getSegmentSuggestions');
    }


    public function showSegment($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'showSegment', [$id]);
    }


    public function updateSegment($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'updateSegment', [$id]);
    }


    public function destroySegment($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'destroySegment', [$id]);
    }


    public function templates(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'templates');
    }


    public function storeTemplate(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'storeTemplate');
    }


    public function showTemplate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'showTemplate', [$id]);
    }


    public function updateTemplate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'updateTemplate', [$id]);
    }


    public function destroyTemplate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'destroyTemplate', [$id]);
    }


    public function duplicateTemplate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'duplicateTemplate', [$id]);
    }


    public function previewTemplate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'previewTemplate', [$id]);
    }


    public function analytics(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'analytics');
    }


    public function getBounces(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'getBounces');
    }


    public function getSuppressionList(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'getSuppressionList');
    }


    public function unsuppress($email): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'unsuppress', [$email]);
    }


    public function suppress($email): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'suppress', [$email]);
    }


    public function getSendTimeData(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'getSendTimeData');
    }


    public function getDiagnostics(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'getDiagnostics');
    }


    public function getBounceTrends(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'getBounceTrends');
    }


    public function recipientCount(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'recipientCount');
    }


    public function getResendInfo($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'getResendInfo', [$id]);
    }


    public function resend($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'resend', [$id]);
    }


    public function sendNewsletter($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'sendNewsletter', [$id]);
    }


    public function sendTest($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'sendTest', [$id]);
    }


    public function duplicateNewsletter($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'duplicateNewsletter', [$id]);
    }


    public function activity($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'activity', [$id]);
    }


    public function openers($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'openers', [$id]);
    }


    public function clickers($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'clickers', [$id]);
    }


    public function nonOpeners($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'nonOpeners', [$id]);
    }


    public function openersNoClick($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'openersNoClick', [$id]);
    }


    public function emailClients($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'emailClients', [$id]);
    }


    public function selectAbWinner($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'selectAbWinner', [$id]);
    }


    public function update($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'update', [$id]);
    }


    public function destroy($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminNewsletterApiController::class, 'destroy', [$id]);
    }

}
