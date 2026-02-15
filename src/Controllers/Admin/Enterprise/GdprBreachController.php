<?php

declare(strict_types=1);

namespace Nexus\Controllers\Admin\Enterprise;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\Enterprise\GdprService;

/**
 * GDPR Breach Controller
 *
 * Handles data breach reporting and management.
 */
class GdprBreachController extends BaseEnterpriseController
{
    private GdprService $gdprService;

    public function __construct()
    {
        parent::__construct();
        $this->gdprService = new GdprService();
    }

    /**
     * GET /admin-legacy/enterprise/gdpr/breaches
     * Data breach log
     */
    public function index(): void
    {
        $tenantId = $this->getTenantId();

        $breaches = Database::query(
            "SELECT * FROM data_breach_log WHERE tenant_id = ? ORDER BY detected_at DESC",
            [$tenantId]
        )->fetchAll();

        $stats = [
            'active_breaches' => 0,
            'investigating' => 0,
            'notified_dpa' => 0,
            'resolved' => 0,
            'notification_required' => 0,
        ];

        foreach ($breaches as $breach) {
            switch ($breach['status'] ?? '') {
                case 'active':
                    $stats['active_breaches']++;
                    if (empty($breach['dpa_notified_at'])) {
                        $stats['notification_required']++;
                    }
                    break;
                case 'investigating':
                    $stats['investigating']++;
                    break;
                case 'resolved':
                case 'closed':
                    $stats['resolved']++;
                    break;
            }
            if (!empty($breach['dpa_notified_at'])) {
                $stats['notified_dpa']++;
            }
        }

        View::render('admin/enterprise/gdpr/breaches', [
            'breaches' => $breaches,
            'stats' => $stats,
            'title' => 'Data Breaches',
        ]);
    }

    /**
     * GET /admin-legacy/enterprise/gdpr/breaches/{id}
     * View single breach details
     */
    public function show(int $id): void
    {
        $breach = Database::query(
            "SELECT * FROM data_breach_log WHERE id = ? AND tenant_id = ?",
            [$id, $this->getTenantId()]
        )->fetch();

        if (!$breach) {
            $_SESSION['flash_error'] = 'Breach not found';
            header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/enterprise/gdpr/breaches');
            exit;
        }

        View::render('admin/enterprise/gdpr/breach-view', [
            'breach' => $breach,
            'title' => "Breach #{$id}",
        ]);
    }

    /**
     * GET /admin-legacy/enterprise/gdpr/breaches/report
     * Show breach report form
     */
    public function create(): void
    {
        View::render('admin/enterprise/gdpr/breach-report', [
            'title' => 'Report Data Breach',
        ]);
    }

    /**
     * POST /admin-legacy/enterprise/gdpr/breaches
     * Report a new data breach
     */
    public function store(): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();

        try {
            $id = $this->gdprService->reportBreach($data, $this->getCurrentUserId());

            echo json_encode([
                'success' => true,
                'id' => $id,
                'message' => 'Breach reported. Remember: GDPR requires notification to authorities within 72 hours.',
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin-legacy/enterprise/gdpr/breaches/{id}/escalate
     * Escalate a breach to incident response team
     */
    public function escalate(int $id): void
    {
        header('Content-Type: application/json');

        $breach = Database::query(
            "SELECT * FROM data_breach_log WHERE id = ? AND tenant_id = ?",
            [$id, $this->getTenantId()]
        )->fetch();

        if (!$breach) {
            http_response_code(404);
            echo json_encode(['error' => 'Breach not found']);
            return;
        }

        try {
            Database::query(
                "UPDATE data_breach_log SET status = 'escalated', escalated_at = NOW(), escalated_by = ? WHERE id = ?",
                [$this->getCurrentUserId(), $id]
            );

            $this->logger->info("Breach #{$id} escalated", ['user_id' => $this->getCurrentUserId()]);

            echo json_encode(['success' => true, 'message' => 'Breach escalated to incident response team']);
        } catch (\Exception $e) {
            $this->logger->error("Failed to escalate breach #{$id}", ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
