<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Models\Error404Log;
use Nexus\Models\SeoRedirect;

class Error404Controller
{
    /**
     * Check if user is admin - redirect if not
     */
    private function checkAdmin()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']);
        $isSuper = !empty($_SESSION['is_super_admin']);
        $isAdminSession = !empty($_SESSION['is_admin']);

        if (!$isAdmin && !$isSuper && !$isAdminSession) {
            header('HTTP/1.0 403 Forbidden');
            echo "<h1>403 Forbidden</h1><p>You do not have permission to access this area.</p>";
            exit;
        }
    }

    /**
     * Display 404 error dashboard
     */
    public function index()
    {
        $this->checkAdmin();

        // Get filter parameters
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50;
        $orderBy = $_GET['order_by'] ?? 'hit_count';
        $orderDir = $_GET['order_dir'] ?? 'DESC';
        $resolved = isset($_GET['resolved']) ? ($_GET['resolved'] === '1') : null;

        // Get 404 errors with pagination
        $result = Error404Log::getAll($page, $perPage, $orderBy, $orderDir, $resolved);

        // Get statistics
        $stats = Error404Log::getStats();

        View::render('admin/404-errors/index', [
            'errors' => $result['data'],
            'pagination' => [
                'current_page' => $result['page'],
                'total_pages' => $result['total_pages'],
                'per_page' => $result['per_page'],
                'total' => $result['total']
            ],
            'stats' => $stats,
            'filters' => [
                'order_by' => $orderBy,
                'order_dir' => $orderDir,
                'resolved' => $resolved
            ]
        ]);
    }

    /**
     * Get 404 errors as JSON (for AJAX requests)
     */
    public function apiList()
    {
        $this->checkAdmin();

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50;
        $orderBy = $_GET['order_by'] ?? 'hit_count';
        $orderDir = $_GET['order_dir'] ?? 'DESC';
        $resolved = isset($_GET['resolved']) ? ($_GET['resolved'] === '1') : null;

        $result = Error404Log::getAll($page, $perPage, $orderBy, $orderDir, $resolved);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $result['data'],
            'pagination' => [
                'current_page' => $result['page'],
                'total_pages' => $result['total_pages'],
                'per_page' => $result['per_page'],
                'total' => $result['total']
            ]
        ]);
    }

    /**
     * Get top 404 errors
     */
    public function topErrors()
    {
        $this->checkAdmin();

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $errors = Error404Log::getTopErrors($limit);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $errors
        ]);
    }

    /**
     * Get 404 statistics
     */
    public function stats()
    {
        $this->checkAdmin();

        $stats = Error404Log::getStats();

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Mark 404 error as resolved
     */
    public function markResolved()
    {
        $this->checkAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        $id = $_POST['id'] ?? null;
        $redirectId = $_POST['redirect_id'] ?? null;
        $notes = $_POST['notes'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required field: id']);
            return;
        }

        $success = Error404Log::markResolved($id, $redirectId, $notes);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Error marked as resolved' : 'Failed to mark error as resolved'
        ]);
    }

    /**
     * Mark 404 error as unresolved
     */
    public function markUnresolved()
    {
        $this->checkAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        $id = $_POST['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required field: id']);
            return;
        }

        $success = Error404Log::markUnresolved($id);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Error marked as unresolved' : 'Failed to mark error as unresolved'
        ]);
    }

    /**
     * Delete 404 error log entry
     */
    public function delete()
    {
        $this->checkAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        $id = $_POST['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required field: id']);
            return;
        }

        $success = Error404Log::delete($id);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Error log deleted' : 'Failed to delete error log'
        ]);
    }

    /**
     * Search 404 errors
     */
    public function search()
    {
        $this->checkAdmin();

        $query = $_GET['q'] ?? '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

        if (empty($query)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Search query is required']);
            return;
        }

        $results = Error404Log::search($query, $limit);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $results
        ]);
    }

    /**
     * Create redirect from 404 error
     */
    public function createRedirect()
    {
        $this->checkAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        $errorId = $_POST['error_id'] ?? null;
        $sourceUrl = $_POST['source_url'] ?? null;
        $destinationUrl = $_POST['destination_url'] ?? null;

        if (!$sourceUrl || !$destinationUrl) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Source and destination URLs are required']);
            return;
        }

        // Create the redirect
        $redirectId = SeoRedirect::create($sourceUrl, $destinationUrl);

        if ($redirectId && $errorId) {
            // Mark the 404 error as resolved with the redirect ID
            Error404Log::markResolved($errorId, $redirectId, 'Redirect created');
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => (bool)$redirectId,
            'message' => $redirectId ? 'Redirect created successfully' : 'Failed to create redirect',
            'redirect_id' => $redirectId
        ]);
    }

    /**
     * Clean old resolved 404 errors
     */
    public function cleanOld()
    {
        $this->checkAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        $daysOld = isset($_POST['days']) ? (int)$_POST['days'] : 90;
        $deleted = Error404Log::cleanOldResolved($daysOld);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => "Deleted $deleted old resolved errors",
            'deleted_count' => $deleted
        ]);
    }

    /**
     * Bulk create redirects for multiple 404 errors
     */
    public function bulkRedirect()
    {
        $this->checkAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        // Get JSON input
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        $errorIds = $data['error_ids'] ?? [];
        $destinationUrl = $data['destination_url'] ?? null;

        if (empty($errorIds) || !is_array($errorIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing or invalid error_ids']);
            return;
        }

        if (empty($destinationUrl)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing destination_url']);
            return;
        }

        $successCount = 0;
        $errors = [];

        foreach ($errorIds as $errorId) {
            $errorId = (int)$errorId;

            // Get the error details
            $error = Error404Log::getById($errorId);

            if (!$error) {
                $errors[] = "Error ID $errorId not found";
                continue;
            }

            // Create the redirect
            $redirectId = SeoRedirect::create($error['url'], $destinationUrl);

            if ($redirectId) {
                // Mark the 404 error as resolved with the redirect ID
                Error404Log::markResolved($errorId, $redirectId, 'Bulk redirect created');
                $successCount++;
            } else {
                $errors[] = "Failed to create redirect for {$error['url']}";
            }
        }

        header('Content-Type: application/json');
        if ($successCount > 0) {
            echo json_encode([
                'success' => true,
                'message' => "Created $successCount redirects" . (count($errors) > 0 ? ' with ' . count($errors) . ' errors' : ''),
                'count' => $successCount,
                'errors' => $errors
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to create any redirects',
                'errors' => $errors
            ]);
        }
    }
}
