<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\ApiErrorCodes;
use Nexus\Models\VolOpportunity;
use Nexus\Core\TenantContext;

class VolunteeringApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function index()
    {
        $this->getUserId();
        if (!TenantContext::hasFeature('volunteering')) {
            $this->respondWithError(ApiErrorCodes::FORBIDDEN, 'Feature not available', null, 403);
            return;
        }
        $this->rateLimit('volunteering_legacy_list', 60, 60);
        // Assuming search method exists or direct query.
        $opps = VolOpportunity::search(TenantContext::getId(), '');
        $this->respondWithCollection($opps);
    }
}
