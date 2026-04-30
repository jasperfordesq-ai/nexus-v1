<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Api\BaseApiController;
use App\Services\CaringCommunity\LeadNurtureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class LeadNurtureAdminController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly LeadNurtureService $service,
    ) {}

    public function index(): JsonResponse
    {
        $disabled = $this->guard();
        if ($disabled) return $disabled;

        $segment = $this->stringQuery('segment');
        $stage   = $this->stringQuery('stage');
        $limit   = $this->queryInt('limit', 200, 1, 1000) ?? 200;

        return $this->respondWithData(
            $this->service->listContacts(TenantContext::getId(), $segment, $stage, $limit)
        );
    }

    public function summary(): JsonResponse
    {
        $disabled = $this->guard();
        if ($disabled) return $disabled;

        return $this->respondWithData(
            $this->service->summary(TenantContext::getId())
        );
    }

    public function update(string $contactId): JsonResponse
    {
        $disabled = $this->guard();
        if ($disabled) return $disabled;

        $payload = (array) request()->all();
        $result = $this->service->update(TenantContext::getId(), $contactId, $payload);

        if (isset($result['error']) && $result['error'] === 'not_found') {
            return $this->respondWithError('NOT_FOUND', __('api.not_found'), null, 404);
        }
        if (isset($result['errors']) && $result['errors'] !== []) {
            return $this->respondWithErrors(array_map(
                fn ($e) => ['code' => 'VALIDATION_ERROR', 'message' => $e['message'], 'field' => $e['field']],
                $result['errors'],
            ), 422);
        }

        return $this->respondWithData($result['contact'] ?? []);
    }

    public function unsubscribe(string $contactId): JsonResponse
    {
        $disabled = $this->guard();
        if ($disabled) return $disabled;

        $result = $this->service->unsubscribe(TenantContext::getId(), $contactId);
        if (isset($result['error']) && $result['error'] === 'not_found') {
            return $this->respondWithError('NOT_FOUND', __('api.not_found'), null, 404);
        }

        return $this->respondWithData($result['contact'] ?? []);
    }

    public function exportCsv(): Response
    {
        $disabled = $this->guard();
        if ($disabled) {
            return response('feature disabled', 403, ['Content-Type' => 'text/plain']);
        }

        $segment = $this->stringQuery('segment');
        $csv = $this->service->exportCsv(TenantContext::getId(), $segment);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="lead-nurture-export.csv"',
        ]);
    }

    private function guard(): ?JsonResponse
    {
        $this->requireAdmin();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        return null;
    }

    private function stringQuery(string $key): ?string
    {
        $val = $this->query($key);
        return is_string($val) && trim($val) !== '' ? trim($val) : null;
    }
}

/*
 * Routes to register in routes/api.php:
 *   GET  /v2/admin/caring-community/leads => index
 *   GET  /v2/admin/caring-community/leads/summary => summary
 *   GET  /v2/admin/caring-community/leads/export.csv => exportCsv
 *   PUT  /v2/admin/caring-community/leads/{contactId} => update
 *   POST /v2/admin/caring-community/leads/{contactId}/unsubscribe => unsubscribe
 */
