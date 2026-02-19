<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;

class ResourceItem
{
    public static function create($tenantId, $userId, $title, $description, $filePath, $fileType, $fileSize, $categoryId = null)
    {
        $sql = "INSERT INTO resources (tenant_id, user_id, title, description, file_path, file_type, file_size, category_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        Database::query($sql, [$tenantId, $userId, $title, $description, $filePath, $fileType, $fileSize, $categoryId]);
        return Database::getConnection()->lastInsertId();
    }

    public static function all($tenantId, $categoryId = null)
    {
        $sql = "SELECT r.*, u.name as uploader_name, c.name as category_name, c.color as category_color
                FROM resources r
                JOIN users u ON r.user_id = u.id
                LEFT JOIN categories c ON r.category_id = c.id
                WHERE r.tenant_id = ?";

        $params = [$tenantId];

        if ($categoryId) {
            $sql .= " AND r.category_id = ?";
            $params[] = $categoryId;
        }

        $sql .= " ORDER BY r.created_at DESC";
        return Database::query($sql, $params)->fetchAll();
    }

    public static function find($id)
    {
        return Database::query("SELECT * FROM resources WHERE id = ?", [$id])->fetch();
    }

    public static function update($id, $title, $description, $categoryId)
    {
        $sql = "UPDATE resources SET title = ?, description = ?, category_id = ? WHERE id = ?";
        Database::query($sql, [$title, $description, $categoryId, $id]);
    }

    public static function delete($id)
    {
        $res = self::find($id);
        if ($res && !empty($res['file_path'])) {
            // Security: Validate path is within uploads directory to prevent path traversal
            $uploadsDir = realpath(__DIR__ . '/../../httpdocs/uploads');
            $fullPath = realpath(__DIR__ . '/../../httpdocs' . $res['file_path']);

            // Only delete if file exists AND is within the uploads directory
            if ($fullPath && $uploadsDir && strpos($fullPath, $uploadsDir) === 0 && file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
        Database::query("DELETE FROM resources WHERE id = ?", [$id]);
    }

    public static function incrementDownload($id)
    {
        Database::query("UPDATE resources SET downloads = downloads + 1 WHERE id = ?", [$id]);
    }
}
