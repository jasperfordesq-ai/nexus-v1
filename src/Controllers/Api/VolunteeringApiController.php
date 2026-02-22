<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Models\VolOpportunity;
use Nexus\Core\TenantContext;

class VolunteeringApiController extends BaseApiController
{
    public function index()
    {
        $this->getUserId();
        // Assuming search method exists or direct query.
        $opps = VolOpportunity::search(TenantContext::getId(), '');
        $this->jsonResponse(['data' => $opps]);
    }
}
