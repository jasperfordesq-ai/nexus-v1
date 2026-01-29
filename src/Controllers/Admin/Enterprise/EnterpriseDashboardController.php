<?php

declare(strict_types=1);

namespace Nexus\Controllers\Admin\Enterprise;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Services\Enterprise\GdprService;
use Nexus\Services\Enterprise\ConfigService;

/**
 * Enterprise Dashboard Controller
 *
 * Handles the main enterprise admin dashboard.
 */
class EnterpriseDashboardController extends BaseEnterpriseController
{
    private GdprService $gdprService;
    private ConfigService $configService;

    public function __construct()
    {
        parent::__construct();
        $this->gdprService = new GdprService();
        $this->configService = ConfigService::getInstance();
    }

    /**
     * GET /admin/enterprise
     * Main enterprise dashboard
     */
    public function dashboard(): void
    {
        $stats = [
            'gdpr' => $this->gdprService->getStatistics(),
            'system' => $this->getSystemStatus(),
            'config' => $this->configService->getStatus(),
        ];

        View::render('admin/enterprise/dashboard', [
            'stats' => $stats,
            'title' => 'Enterprise Dashboard',
        ]);
    }
}
