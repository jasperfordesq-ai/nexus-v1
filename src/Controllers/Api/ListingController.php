<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiAuth;

class ListingController
{
    use ApiAuth;

    private function jsonResponse($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    /**
     * SECURITY: Require authentication for API access
     */
    private function requireAuthLocal()
    {
        return $this->requireAuth();
    }

    public function index()
    {
        // SECURITY: Require authentication
        $this->requireAuth();

        // SECURITY: Use tenant from context, not from user input
        $tenantId = TenantContext::getId();

        $db = Database::getConnection();
        $query = "
            SELECT l.*, CONCAT(u.first_name, ' ', u.last_name) as author_name, u.avatar_url as author_avatar
            FROM listings l
            JOIN users u ON l.user_id = u.id
            WHERE l.tenant_id = ?
            ORDER BY l.created_at DESC
            LIMIT 50
        ";

        $stmt = $db->prepare($query);
        $stmt->execute([$tenantId]);
        $listings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->jsonResponse([
            'success' => true,
            'data' => $listings
        ]);
    }

    public function store()
    {
        // SECURITY: Require authentication
        $userId = $this->requireAuthLocal();

        // Security: Verify CSRF token for state-changing operations
        \Nexus\Core\Csrf::verifyOrDieJson();

        // JSON Input
        $input = file_get_contents('php://input');

        $data = json_decode($input, true);

        if (!$data) {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid JSON'], 400);
        }

        $title = $data['title'] ?? '';
        $type = $data['type'] ?? 'offer';
        $description = $data['description'] ?? '';
        // SECURITY: Use tenant from context, user from session - never trust client input
        $tenantId = TenantContext::getId();

        if (empty($title)) {
            $this->jsonResponse(['success' => false, 'message' => 'Missing Title'], 422);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO listings (tenant_id, user_id, title, description, type)
            VALUES (?, ?, ?, ?, ?)
        ");

        try {
            $stmt->execute([$tenantId, $userId, $title, $description, $type]);
            $this->jsonResponse(['success' => true, 'message' => 'Listing Created']);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => 'Server error'], 500);
        }
    }
}
