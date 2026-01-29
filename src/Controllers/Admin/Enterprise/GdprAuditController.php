<?php

declare(strict_types=1);

namespace Nexus\Controllers\Admin\Enterprise;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Services\Enterprise\GdprService;

/**
 * GDPR Audit Controller
 *
 * Handles GDPR audit logging and compliance reports.
 */
class GdprAuditController extends BaseEnterpriseController
{
    private GdprService $gdprService;

    public function __construct()
    {
        parent::__construct();
        $this->gdprService = new GdprService();
    }

    /**
     * GET /admin/enterprise/gdpr/audit
     * GDPR audit log
     */
    public function index(): void
    {
        $logs = Database::query(
            "SELECT al.*, u.email, u.first_name, u.last_name
             FROM gdpr_audit_log al
             LEFT JOIN users u ON al.user_id = u.id
             WHERE al.tenant_id = ?
             ORDER BY al.created_at DESC
             LIMIT 500",
            [$this->getTenantId()]
        )->fetchAll();

        View::render('admin/enterprise/gdpr/audit', [
            'logs' => $logs,
            'title' => 'GDPR Audit Log',
        ]);
    }

    /**
     * GET /admin/enterprise/gdpr/audit/export
     * Export GDPR audit log
     */
    public function export(): void
    {
        $format = $_GET['format'] ?? 'csv';
        $tenantId = $this->getTenantId();

        $logs = Database::query(
            "SELECT al.*, u.email
             FROM gdpr_audit_log al
             LEFT JOIN users u ON al.user_id = u.id
             WHERE al.tenant_id = ?
             ORDER BY al.created_at DESC
             LIMIT 10000",
            [$tenantId]
        )->fetchAll();

        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="gdpr_audit_log_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['Timestamp', 'User ID', 'Email', 'Admin ID', 'Action', 'Entity Type', 'Entity ID', 'IP Address']);

            foreach ($logs as $log) {
                fputcsv($output, [
                    $log['created_at'],
                    $log['user_id'],
                    $log['email'],
                    $log['admin_id'],
                    $log['action'],
                    $log['entity_type'],
                    $log['entity_id'],
                    $log['ip_address'],
                ]);
            }

            fclose($output);
        } else {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="gdpr_audit_log_' . date('Y-m-d') . '.json"');
            echo json_encode($logs, JSON_PRETTY_PRINT);
        }
    }

    /**
     * POST /admin/enterprise/gdpr/export-report
     * Generate comprehensive GDPR compliance report
     */
    public function complianceReport(): void
    {
        header('Content-Type: application/json');

        $tenantId = $this->getTenantId();

        try {
            $report = [
                'generated_at' => date('c'),
                'tenant_id' => $tenantId,
                'statistics' => $this->gdprService->getStatistics(),
                'consent_types' => $this->gdprService->getConsentTypes(),
                'pending_requests' => $this->gdprService->getPendingRequests(100),
                'data_processing_activities' => Database::query(
                    "SELECT * FROM data_processing_log WHERE tenant_id = ? AND is_active = 1",
                    [$tenantId]
                )->fetchAll(),
                'retention_policies' => Database::query(
                    "SELECT * FROM data_retention_policies WHERE tenant_id = ? AND is_active = 1",
                    [$tenantId]
                )->fetchAll(),
                'recent_breaches' => Database::query(
                    "SELECT * FROM data_breach_log WHERE tenant_id = ? ORDER BY detected_at DESC LIMIT 10",
                    [$tenantId]
                )->fetchAll(),
            ];

            header('Content-Disposition: attachment; filename="gdpr_compliance_report_' . date('Y-m-d') . '.json"');
            echo json_encode($report, JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
