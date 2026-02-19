<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Models\VolOpportunity;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiAuth;

class VolunteeringApiController
{
    use ApiAuth;

    private function jsonResponse($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    private function getUserId()
    {
        return $this->requireAuth();
    }

    public function index()
    {
        $this->getUserId();
        // Assuming search method exists or direct query.
        $opps = VolOpportunity::search(TenantContext::getId(), '');
        $this->jsonResponse(['data' => $opps]);
    }
}
