<?php

declare(strict_types=1);

namespace Nexus\Controllers\Admin\Enterprise;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\Enterprise\GdprService;

/**
 * GDPR Request Controller
 *
 * Handles GDPR data subject requests (access, erasure, portability, etc.)
 */
class GdprRequestController extends BaseEnterpriseController
{
    private GdprService $gdprService;

    public function __construct()
    {
        parent::__construct();
        $this->gdprService = new GdprService();
    }

    /**
     * GET /admin-legacy/enterprise/gdpr
     * GDPR compliance dashboard
     */
    public function dashboard(): void
    {
        $stats = $this->gdprService->getStatistics();
        $pendingRequests = $this->gdprService->getPendingRequests(20);
        $consentTypes = $this->gdprService->getConsentTypes();

        View::render('admin/enterprise/gdpr/dashboard', [
            'stats' => $stats,
            'pendingRequests' => $pendingRequests,
            'consentTypes' => $consentTypes,
            'title' => 'GDPR Compliance',
        ]);
    }

    /**
     * GET /admin-legacy/enterprise/gdpr/requests
     * List all GDPR requests
     */
    public function index(): void
    {
        $page = (int) ($_GET['page'] ?? 1);
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $requests = $this->gdprService->getPendingRequests($limit, $offset);

        $tenantId = $this->getTenantId();
        $summary = [
            'pending' => Database::query(
                "SELECT COUNT(*) as count FROM gdpr_requests WHERE tenant_id = ? AND status = 'pending'",
                [$tenantId]
            )->fetch()['count'] ?? 0,
            'processing' => Database::query(
                "SELECT COUNT(*) as count FROM gdpr_requests WHERE tenant_id = ? AND status = 'processing'",
                [$tenantId]
            )->fetch()['count'] ?? 0,
            'completed' => Database::query(
                "SELECT COUNT(*) as count FROM gdpr_requests WHERE tenant_id = ? AND status = 'completed'",
                [$tenantId]
            )->fetch()['count'] ?? 0,
            'overdue' => Database::query(
                "SELECT COUNT(*) as count FROM gdpr_requests WHERE tenant_id = ? AND status IN ('pending', 'processing') AND requested_at < DATE_SUB(NOW(), INTERVAL 30 DAY)",
                [$tenantId]
            )->fetch()['count'] ?? 0,
        ];

        View::render('admin/enterprise/gdpr/requests', [
            'requests' => $requests,
            'summary' => $summary,
            'page' => $page,
            'title' => 'GDPR Requests',
        ]);
    }

    /**
     * GET /admin-legacy/enterprise/gdpr/requests/{id}
     * View single GDPR request
     */
    public function show(int $id): void
    {
        $request = $this->gdprService->getRequest($id);

        if (!$request) {
            $_SESSION['flash_error'] = 'Request not found';
            header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/enterprise/gdpr/requests');
            exit;
        }

        View::render('admin/enterprise/gdpr/request-view', [
            'request' => $request,
            'title' => "GDPR Request #{$id}",
        ]);
    }

    /**
     * GET /admin-legacy/enterprise/gdpr/requests/new
     * Form to create a new GDPR request (admin-initiated)
     */
    public function create(): void
    {
        View::render('admin/enterprise/gdpr/request-create', [
            'title' => 'Create GDPR Request',
            'requestTypes' => ['access', 'erasure', 'rectification', 'restriction', 'portability', 'objection'],
        ]);
    }

    /**
     * POST /admin-legacy/enterprise/gdpr/requests
     * Store a new GDPR request
     */
    public function store(): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();
        $userId = (int) ($data['user_id'] ?? 0);
        $requestType = $data['request_type'] ?? '';
        $notes = $data['notes'] ?? '';

        if (!$userId || !$requestType) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID and request type are required']);
            return;
        }

        try {
            $result = $this->gdprService->createRequest($userId, $requestType, ['notes' => $notes]);
            $id = $result['id'];

            $this->logger->info("GDPR request created by admin", [
                'request_id' => $id,
                'user_id' => $userId,
                'type' => $requestType,
                'admin_id' => $this->getCurrentUserId(),
            ]);

            echo json_encode(['success' => true, 'id' => $id]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin-legacy/enterprise/gdpr/requests/{id}/process
     * Start processing a GDPR request
     */
    public function process(int $id): void
    {
        header('Content-Type: application/json');

        $request = $this->gdprService->getRequest($id);

        if (!$request) {
            http_response_code(404);
            echo json_encode(['error' => 'Request not found']);
            return;
        }

        $adminId = $this->getCurrentUserId();

        try {
            $this->gdprService->processRequest($id, $adminId);

            switch ($request['request_type']) {
                case 'access':
                case 'portability':
                    $exportPath = $this->gdprService->generateDataExport($request['user_id'], $id);
                    $this->logger->info("Data export generated for request #{$id}");
                    break;

                case 'erasure':
                    $this->gdprService->executeAccountDeletion($request['user_id'], $adminId, $id);
                    $this->logger->info("Account deleted for request #{$id}");
                    break;
            }

            echo json_encode(['success' => true, 'message' => 'Request processed successfully']);
        } catch (\Exception $e) {
            $this->logger->error("Failed to process GDPR request #{$id}", ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin-legacy/enterprise/gdpr/requests/{id}/reject
     * Reject a GDPR request
     */
    public function reject(int $id): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();
        $reason = $data['reason'] ?? '';

        if (empty($reason)) {
            http_response_code(400);
            echo json_encode(['error' => 'Rejection reason is required']);
            return;
        }

        Database::query(
            "UPDATE gdpr_requests SET status = 'rejected', rejection_reason = ?, processed_at = NOW(), processed_by = ? WHERE id = ?",
            [$reason, $this->getCurrentUserId(), $id]
        );

        $this->logger->info("GDPR request #{$id} rejected", ['reason' => $reason]);

        echo json_encode(['success' => true]);
    }

    /**
     * POST /admin-legacy/enterprise/gdpr/requests/{id}/complete
     * Mark a GDPR request as completed
     */
    public function complete(int $id): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();
        $completionNotes = $data['notes'] ?? '';

        try {
            Database::query(
                "UPDATE gdpr_requests SET status = 'completed', processed_at = NOW(), processed_by = ?, notes = CONCAT(COALESCE(notes, ''), '\n[Completed] ', ?) WHERE id = ? AND tenant_id = ?",
                [$this->getCurrentUserId(), $completionNotes, $id, $this->getTenantId()]
            );

            $this->logger->info("GDPR request #{$id} completed", ['admin_id' => $this->getCurrentUserId()]);

            echo json_encode(['success' => true, 'message' => 'Request marked as completed']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin-legacy/enterprise/gdpr/requests/{id}/assign
     * Assign a GDPR request to an admin
     */
    public function assign(int $id): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();
        $assigneeId = (int) ($data['assignee_id'] ?? 0);

        if (!$assigneeId) {
            http_response_code(400);
            echo json_encode(['error' => 'Assignee ID is required']);
            return;
        }

        try {
            Database::query(
                "UPDATE gdpr_requests SET processed_by = ?, status = 'processing' WHERE id = ? AND tenant_id = ?",
                [$assigneeId, $id, $this->getTenantId()]
            );

            $this->logger->info("GDPR request #{$id} assigned", ['assignee_id' => $assigneeId, 'admin_id' => $this->getCurrentUserId()]);

            echo json_encode(['success' => true, 'message' => 'Request assigned successfully']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin-legacy/enterprise/gdpr/requests/{id}/notes
     * Add a note to a GDPR request
     */
    public function addNote(int $id): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();
        $note = trim($data['note'] ?? '');

        if (empty($note)) {
            http_response_code(400);
            echo json_encode(['error' => 'Note content is required']);
            return;
        }

        try {
            $timestamp = date('Y-m-d H:i');
            $adminId = $this->getCurrentUserId();

            Database::query(
                "UPDATE gdpr_requests SET notes = CONCAT(COALESCE(notes, ''), '\n[', ?, ' - Admin #', ?, '] ', ?) WHERE id = ? AND tenant_id = ?",
                [$timestamp, $adminId, $note, $id, $this->getTenantId()]
            );

            $this->logger->info("Note added to GDPR request #{$id}");

            echo json_encode(['success' => true, 'message' => 'Note added successfully']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin-legacy/enterprise/gdpr/requests/{id}/generate-export
     * Generate data export for a user
     */
    public function generateExport(int $id): void
    {
        header('Content-Type: application/json');

        $request = $this->gdprService->getRequest($id);

        if (!$request) {
            http_response_code(404);
            echo json_encode(['error' => 'Request not found']);
            return;
        }

        try {
            $exportPath = $this->gdprService->generateDataExport($request['user_id'], $id);

            $this->logger->info("Data export generated for request #{$id}", ['path' => $exportPath]);

            echo json_encode([
                'success' => true,
                'message' => 'Data export generated successfully',
                'export_path' => $exportPath,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin-legacy/enterprise/gdpr/requests/bulk-process
     * Bulk process multiple GDPR requests
     */
    public function bulkProcess(): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();
        $requestIds = $data['request_ids'] ?? [];
        $action = $data['action'] ?? '';

        if (empty($requestIds) || !is_array($requestIds)) {
            http_response_code(400);
            echo json_encode(['error' => 'Request IDs are required']);
            return;
        }

        $validActions = ['process', 'complete', 'reject'];
        if (!in_array($action, $validActions)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action. Must be: ' . implode(', ', $validActions)]);
            return;
        }

        $processed = 0;
        $errors = [];

        foreach ($requestIds as $id) {
            try {
                switch ($action) {
                    case 'process':
                        $this->gdprService->processRequest((int) $id, $this->getCurrentUserId());
                        break;
                    case 'complete':
                        Database::query(
                            "UPDATE gdpr_requests SET status = 'completed', processed_at = NOW(), processed_by = ? WHERE id = ? AND tenant_id = ?",
                            [$this->getCurrentUserId(), $id, $this->getTenantId()]
                        );
                        break;
                    case 'reject':
                        Database::query(
                            "UPDATE gdpr_requests SET status = 'rejected', processed_at = NOW(), processed_by = ?, rejection_reason = 'Bulk rejection' WHERE id = ? AND tenant_id = ?",
                            [$this->getCurrentUserId(), $id, $this->getTenantId()]
                        );
                        break;
                }
                $processed++;
            } catch (\Exception $e) {
                $errors[] = "Request #{$id}: " . $e->getMessage();
            }
        }

        $this->logger->info("Bulk GDPR action performed", ['action' => $action, 'count' => $processed]);

        echo json_encode([
            'success' => true,
            'processed' => $processed,
            'errors' => $errors,
        ]);
    }
}
