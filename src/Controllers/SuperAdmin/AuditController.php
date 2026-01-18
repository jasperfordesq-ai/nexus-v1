<?php

namespace Nexus\Controllers\SuperAdmin;

use Nexus\Core\View;
use Nexus\Middleware\SuperPanelAccess;
use Nexus\Services\SuperAdminAuditService;
use Nexus\Services\TenantVisibilityService;

/**
 * Super Admin Audit Controller
 *
 * Displays audit log of all hierarchy changes.
 */
class AuditController
{
    public function __construct()
    {
        SuperPanelAccess::handle();
    }

    /**
     * Display audit log
     */
    public function index()
    {
        $access = SuperPanelAccess::getAccess();

        $filters = [
            'action_type' => $_GET['action_type'] ?? null,
            'target_type' => $_GET['target_type'] ?? null,
            'search' => $_GET['search'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
            'limit' => 50
        ];

        $logs = SuperAdminAuditService::getLog(array_filter($filters));
        $stats = SuperAdminAuditService::getStats(30);

        View::render('super-admin/audit/index', [
            'access' => $access,
            'logs' => $logs,
            'stats' => $stats,
            'filters' => $filters,
            'pageTitle' => 'Audit Log'
        ]);
    }

    /**
     * API: Get audit log as JSON
     */
    public function apiLog()
    {
        header('Content-Type: application/json');

        $filters = [
            'action_type' => $_GET['action_type'] ?? null,
            'target_type' => $_GET['target_type'] ?? null,
            'search' => $_GET['q'] ?? null,
            'limit' => min((int)($_GET['limit'] ?? 50), 100),
            'offset' => (int)($_GET['offset'] ?? 0)
        ];

        $logs = SuperAdminAuditService::getLog(array_filter($filters));

        echo json_encode([
            'success' => true,
            'logs' => $logs,
            'count' => count($logs)
        ]);
    }
}
