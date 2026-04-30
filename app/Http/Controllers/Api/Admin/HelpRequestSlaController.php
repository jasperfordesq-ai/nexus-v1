<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Api\BaseApiController;
use App\Services\CaringCommunity\HelpRequestSlaService;
use Illuminate\Http\JsonResponse;

/**
 * AG96 — Help Request SLA Breach Dashboard admin endpoint.
 *
 * Surfaces help requests breaching, at risk of breaching, or recently resolved
 * within the AG81 operating-policy SLA windows. Read-only — no mutation.
 */
class HelpRequestSlaController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly HelpRequestSlaService $service,
    ) {
    }

    /** GET /v2/admin/caring-community/sla-dashboard */
    public function dashboard(): JsonResponse
    {
        $this->requireAdmin();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondForbidden('Caring Community feature is not enabled for this tenant.');
        }

        return $this->respondWithData(
            $this->service->dashboard(TenantContext::getId()),
        );
    }
}
