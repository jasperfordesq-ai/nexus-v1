<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\CaringCommunity\FederationAggregateService;
use Illuminate\Http\JsonResponse;

/**
 * AdminFederationAggregateController — admin self-service for the
 * /federation/aggregates opt-in surface.
 *
 * Lets a tenant admin enable/disable cross-node aggregate sharing,
 * rotate the HMAC signing secret, preview the JSON that would be
 * exposed, and inspect the audit trail of recent queries.
 */
class AdminFederationAggregateController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly FederationAggregateService $service,
    ) {
    }

    /** GET /v2/admin/federation/aggregate-consent */
    public function consent(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = (int) TenantContext::getId();

        $consent = $this->service->getConsent($tenantId) ?? [
            'enabled'         => false,
            'has_secret'      => false,
            'last_rotated_at' => null,
        ];

        return $this->respondWithData($consent);
    }

    /** PUT /v2/admin/federation/aggregate-consent */
    public function updateConsent(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = (int) TenantContext::getId();

        $enabled = (bool) $this->input('enabled', false);
        $consent = $this->service->setEnabled($tenantId, $enabled);

        return $this->respondWithData($consent);
    }

    /** POST /v2/admin/federation/aggregate-consent/rotate-secret */
    public function rotateSecret(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = (int) TenantContext::getId();

        $this->service->rotateSecret($tenantId);
        $consent = $this->service->getConsent($tenantId) ?? [
            'enabled'         => false,
            'has_secret'      => true,
            'last_rotated_at' => null,
        ];

        return $this->respondWithData([
            'rotated' => true,
            'consent' => $consent,
        ]);
    }

    /** GET /v2/admin/federation/aggregate-consent/audit-log */
    public function auditLog(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = (int) TenantContext::getId();

        $entries = $this->service->recentAuditLog($tenantId, 100);
        return $this->respondWithData(['entries' => $entries]);
    }

    /** GET /v2/admin/federation/aggregate-consent/preview */
    public function preview(): JsonResponse
    {
        $this->requireAdmin();

        [$from, $to] = $this->resolvePeriod();
        $payload = $this->service->compute($from, $to);

        return $this->respondWithData([
            'payload'   => $payload,
            'algorithm' => FederationAggregateService::ALGORITHM,
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolvePeriod(): array
    {
        $from = (string) $this->input('period_from', '');
        $to   = (string) $this->input('period_to', '');

        $isValid = static fn (string $d): bool =>
            $d !== '' && (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);

        if (!$isValid($to)) {
            $to = date('Y-m-d');
        }
        if (!$isValid($from)) {
            $from = date('Y-m-d', strtotime('-30 days', strtotime($to)));
        }
        return [$from, $to];
    }
}
