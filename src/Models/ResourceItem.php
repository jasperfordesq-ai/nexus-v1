<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

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
        return Database::query(
            "SELECT * FROM resources WHERE id = ? AND tenant_id = ?",
            [$id, TenantContext::getId()]
        )->fetch();
    }

    public static function update($id, $title, $description, $categoryId)
    {
        Database::query(
            "UPDATE resources SET title = ?, description = ?, category_id = ? WHERE id = ? AND tenant_id = ?",
            [$title, $description, $categoryId, $id, TenantContext::getId()]
        );
    }

    public static function delete($id)
    {
        $res = self::find($id);
        if ($res && !empty($res['file_path'])) {
            $uploadsDir = realpath(__DIR__ . '/../../httpdocs/uploads');
            $filePath = $res['file_path'];

            // Legacy records store full web path (e.g. /uploads/resources/file.pdf)
            // New records store just the filename (e.g. file.pdf) under tenant-scoped dir
            if (str_starts_with($filePath, '/uploads/')) {
                $fullPath = realpath(__DIR__ . '/../../httpdocs' . $filePath);
            } else {
                $tenantId = $res['tenant_id'] ?? \Nexus\Core\TenantContext::getId();
                $fullPath = realpath(__DIR__ . '/../../httpdocs/uploads/' . $tenantId . '/resources/' . $filePath);
            }

            // Security: Only delete if file exists AND is within the uploads directory
            if ($fullPath && $uploadsDir && strpos($fullPath, $uploadsDir) === 0 && file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
        Database::query("DELETE FROM resources WHERE id = ? AND tenant_id = ?", [$id, TenantContext::getId()]);
    }

    public static function incrementDownload($id)
    {
        Database::query(
            "UPDATE resources SET downloads = downloads + 1 WHERE id = ? AND tenant_id = ?",
            [$id, TenantContext::getId()]
        );
    }
}
