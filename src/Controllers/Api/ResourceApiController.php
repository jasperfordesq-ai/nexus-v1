<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiAuth;

class ResourceApiController
{
    use ApiAuth;

    public function index()
    {
        // SECURITY: Require authentication
        $this->requireAuth();

        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        // Fetch Categories - SECURITY: Use parameterized query
        $stmt = $db->prepare("
            SELECT * FROM categories
            WHERE type = 'resource'
            AND tenant_id = ?
        ");
        $stmt->execute([$tenantId]);
        $categories = $stmt->fetchAll();

        // Fetch Resources - SECURITY: Use parameterized query
        $stmt = $db->prepare("
            SELECT r.*, c.name as category_name, c.color as category_color,
                   CONCAT(u.first_name, ' ', u.last_name) as uploader_name
            FROM resources r
            LEFT JOIN categories c ON r.category_id = c.id
            LEFT JOIN users u ON r.user_id = u.id
            WHERE r.tenant_id = ?
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$tenantId]);
        $resources = $stmt->fetchAll();

        // Group by Category
        $grouped = [];
        foreach ($categories as $cat) {
            $grouped[$cat['id']] = [
                'id' => $cat['id'],
                'name' => $cat['name'],
                'color' => $cat['color'] ?? 'gray',
                'items' => []
            ];
        }
        // Catch-all for uncategorized
        $grouped[0] = ['id' => 0, 'name' => 'Uncategorized', 'color' => 'gray', 'items' => []];

        foreach ($resources as $res) {
            $catId = $res['category_id'] ?? 0;
            if (!isset($grouped[$catId])) $catId = 0;

            // Format URL
            $res['file_url'] = '/uploads/' . TenantContext::getId() . '/resources/' . $res['file_path'];

            $grouped[$catId]['items'][] = $res;
        }

        // Remove empty
        $final = array_filter($grouped, function ($g) {
            return count($g['items']) > 0;
        });

        echo json_encode(['status' => 'success', 'data' => array_values($final)]);
    }
}
