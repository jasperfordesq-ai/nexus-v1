<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\SuperAdmin;

use Nexus\Core\View;
use Nexus\Middleware\SuperPanelAccess;
use Nexus\Services\TenantVisibilityService;

/**
 * Super Admin Dashboard Controller
 *
 * Main dashboard for the Super Admin Panel showing tenant hierarchy overview.
 */
class DashboardController
{
    public function __construct()
    {
        SuperPanelAccess::handle();
    }

    /**
     * Main dashboard view
     */
    public function index()
    {
        $access = SuperPanelAccess::getAccess();
        $stats = TenantVisibilityService::getDashboardStats();
        $tenants = TenantVisibilityService::getTenantList();

        View::render('super-admin/dashboard', [
            'access' => $access,
            'stats' => $stats,
            'tenants' => $tenants,
            'pageTitle' => 'Super Admin Dashboard'
        ]);
    }
}
