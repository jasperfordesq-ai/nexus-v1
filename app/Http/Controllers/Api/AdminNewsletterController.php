<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * AdminNewsletterController -- Newsletter campaign management.
 *
 * This is the largest admin controller (~50 methods). Methods are grouped:
 *
 * 1. CRUD operations (index, show, store, update, destroy) - delegated to legacy
 * 2. Subscriber management - delegated to legacy
 * 3. Segments - delegated to legacy (complex segment builder SQL)
 * 4. Templates - delegated to legacy
 * 5. Analytics & stats - delegated to legacy (complex aggregation queries)
 * 6. Email-sending methods (sendNewsletter, sendTest, resend) - MUST stay as delegation (Mailer)
 * 7. Bounce/suppression management - delegated to legacy
 * 8. CSV exports (exportSubscribers) - delegation (php://output)
 *
 * All methods delegate to the legacy AdminNewsletterApiController which has been
 * thoroughly tested. The legacy controller uses Database::query() directly with
 * proper tenant scoping and respondWithData/respondWithError helpers.
 */
class AdminNewsletterController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * Delegate to legacy controller via output buffering.
     */
    private function delegate(string $method, array $params = []): JsonResponse
    {
        $controller = new \Nexus\Controllers\Api\AdminNewsletterApiController();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Newsletter CRUD
    // ─────────────────────────────────────────────────────────────────────────

    public function campaigns(): JsonResponse { return $this->delegate('campaigns'); }
    public function index(): JsonResponse { return $this->delegate('index'); }
    public function show(int $id): JsonResponse { return $this->delegate('show', [$id]); }
    public function store(): JsonResponse { return $this->delegate('store'); }
    public function create(): JsonResponse { return $this->delegate('create'); }
    public function update($id): JsonResponse { return $this->delegate('update', [(int)$id]); }
    public function destroy($id): JsonResponse { return $this->delegate('destroy', [(int)$id]); }
    public function duplicateNewsletter($id): JsonResponse { return $this->delegate('duplicateNewsletter', [(int)$id]); }

    // ─────────────────────────────────────────────────────────────────────────
    // Email sending — MUST stay as delegation (uses Mailer)
    // ─────────────────────────────────────────────────────────────────────────

    public function send(int $id): JsonResponse { return $this->delegate('send', [$id]); }
    public function sendNewsletter($id): JsonResponse { return $this->delegate('sendNewsletter', [(int)$id]); }
    public function sendTest($id): JsonResponse { return $this->delegate('sendTest', [(int)$id]); }
    public function resend($id): JsonResponse { return $this->delegate('resend', [(int)$id]); }

    // ─────────────────────────────────────────────────────────────────────────
    // Stats & Analytics
    // ─────────────────────────────────────────────────────────────────────────

    public function stats(int $id): JsonResponse { return $this->delegate('stats', [$id]); }
    public function analytics(): JsonResponse { return $this->delegate('analytics'); }
    public function activity($id): JsonResponse { return $this->delegate('activity', [(int)$id]); }
    public function openers($id): JsonResponse { return $this->delegate('openers', [(int)$id]); }
    public function clickers($id): JsonResponse { return $this->delegate('clickers', [(int)$id]); }
    public function nonOpeners($id): JsonResponse { return $this->delegate('nonOpeners', [(int)$id]); }
    public function openersNoClick($id): JsonResponse { return $this->delegate('openersNoClick', [(int)$id]); }
    public function emailClients($id): JsonResponse { return $this->delegate('emailClients', [(int)$id]); }
    public function selectAbWinner($id): JsonResponse { return $this->delegate('selectAbWinner', [(int)$id]); }
    public function recipientCount(): JsonResponse { return $this->delegate('recipientCount'); }
    public function getSendTimeData(): JsonResponse { return $this->delegate('getSendTimeData'); }
    public function getDiagnostics(): JsonResponse { return $this->delegate('getDiagnostics'); }
    public function getBounceTrends(): JsonResponse { return $this->delegate('getBounceTrends'); }
    public function getResendInfo($id): JsonResponse { return $this->delegate('getResendInfo', [(int)$id]); }

    // ─────────────────────────────────────────────────────────────────────────
    // Subscribers
    // ─────────────────────────────────────────────────────────────────────────

    public function subscribers(): JsonResponse { return $this->delegate('subscribers'); }
    public function addSubscriber(): JsonResponse { return $this->delegate('addSubscriber'); }
    public function removeSubscriber($id): JsonResponse { return $this->delegate('removeSubscriber', [(int)$id]); }
    public function importSubscribers(): JsonResponse { return $this->delegate('importSubscribers'); }
    public function exportSubscribers(): JsonResponse { return $this->delegate('exportSubscribers'); }
    public function syncPlatformMembers(): JsonResponse { return $this->delegate('syncPlatformMembers'); }

    // ─────────────────────────────────────────────────────────────────────────
    // Segments
    // ─────────────────────────────────────────────────────────────────────────

    public function segments(): JsonResponse { return $this->delegate('segments'); }
    public function storeSegment(): JsonResponse { return $this->delegate('storeSegment'); }
    public function previewSegment(): JsonResponse { return $this->delegate('previewSegment'); }
    public function getSegmentSuggestions(): JsonResponse { return $this->delegate('getSegmentSuggestions'); }
    public function showSegment($id): JsonResponse { return $this->delegate('showSegment', [(int)$id]); }
    public function updateSegment($id): JsonResponse { return $this->delegate('updateSegment', [(int)$id]); }
    public function destroySegment($id): JsonResponse { return $this->delegate('destroySegment', [(int)$id]); }

    // ─────────────────────────────────────────────────────────────────────────
    // Templates
    // ─────────────────────────────────────────────────────────────────────────

    public function templates(): JsonResponse { return $this->delegate('templates'); }
    public function storeTemplate(): JsonResponse { return $this->delegate('storeTemplate'); }
    public function showTemplate($id): JsonResponse { return $this->delegate('showTemplate', [(int)$id]); }
    public function updateTemplate($id): JsonResponse { return $this->delegate('updateTemplate', [(int)$id]); }
    public function destroyTemplate($id): JsonResponse { return $this->delegate('destroyTemplate', [(int)$id]); }
    public function duplicateTemplate($id): JsonResponse { return $this->delegate('duplicateTemplate', [(int)$id]); }
    public function previewTemplate($id): JsonResponse { return $this->delegate('previewTemplate', [(int)$id]); }

    // ─────────────────────────────────────────────────────────────────────────
    // Bounce & Suppression
    // ─────────────────────────────────────────────────────────────────────────

    public function getBounces(): JsonResponse { return $this->delegate('getBounces'); }
    public function getSuppressionList(): JsonResponse { return $this->delegate('getSuppressionList'); }
    public function unsuppress($email): JsonResponse { return $this->delegate('unsuppress', [(string)$email]); }
    public function suppress($email): JsonResponse { return $this->delegate('suppress', [(string)$email]); }
}
