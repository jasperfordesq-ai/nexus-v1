<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class Post
{
    public static function create($authorId, $data)
    {
        $tenantId = TenantContext::getId();
        $sql = "INSERT INTO posts (tenant_id, author_id, title, slug, excerpt, content, featured_image, status, category_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        Database::query($sql, [
            $tenantId,
            $authorId,
            $data['title'],
            $data['slug'],
            $data['excerpt'] ?? '',
            $data['content'],
            $data['featured_image'] ?? '',
            $data['status'] ?? 'draft',
            $data['category_id'] ?? null
        ]);
        return Database::lastInsertId();
    }

    public static function update($id, $data)
    {
        $tenantId = TenantContext::getId();

        // Data Loss Prevention: Fetch existing post to preserve values not being updated
        $existing = self::findById($id);
        if (!$existing) {
            return false;
        }

        // Merge with existing data - only update fields that are explicitly provided
        // For image fields, preserve existing if new value is empty
        $featured_image = $existing['featured_image'];
        if (isset($data['featured_image']) && $data['featured_image'] !== '') {
            $featured_image = $data['featured_image'];
        }

        $sql = "UPDATE posts SET title = ?, slug = ?, excerpt = ?, content = ?, featured_image = ?, status = ?, category_id = ?, content_json = ?, html_render = ? WHERE id = ? AND tenant_id = ?";
        Database::query($sql, [
            $data['title'] ?? $existing['title'],
            $data['slug'] ?? $existing['slug'],
            $data['excerpt'] ?? $existing['excerpt'] ?? '',
            $data['content'] ?? $existing['content'],
            $featured_image,
            $data['status'] ?? $existing['status'],
            $data['category_id'] ?? $existing['category_id'],
            $data['content_json'] ?? $existing['content_json'],
            $data['html_render'] ?? $existing['html_render'],
            $id,
            $tenantId
        ]);
    }

    public static function delete($id)
    {
        $tenantId = TenantContext::getId();
        Database::query("DELETE FROM posts WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
    }

    public static function findBySlug($slug)
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT p.*, u.first_name, u.last_name, CONCAT(u.first_name, ' ', u.last_name) as author_name 
                FROM posts p 
                LEFT JOIN users u ON p.author_id = u.id 
                WHERE p.slug = ? AND p.tenant_id = ?";
        return Database::query($sql, [$slug, $tenantId])->fetch();
    }

    public static function findById($id)
    {
        $tenantId = TenantContext::getId();
        return Database::query("SELECT * FROM posts WHERE id = ? AND tenant_id = ?", [$id, $tenantId])->fetch();
    }

    public static function getAll($limit = 10, $offset = 0, $status = 'published')
    {
        $tenantId = TenantContext::getId();
        // SECURITY: Cast to int to prevent SQL injection
        $limit = (int)$limit;
        $offset = (int)$offset;

        $sql = "SELECT p.*, COALESCE(u.name, 'Unknown Author') as author_name
                FROM posts p
                LEFT JOIN users u ON p.author_id = u.id
                WHERE p.tenant_id = ? ";

        $params = [$tenantId];

        if ($status !== 'all') {
            $sql .= "AND p.status = ? ";
            $params[] = $status;
        }

        $sql .= "ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset";

        return Database::query($sql, $params)->fetchAll();
    }
    public static function count($status = 'published')
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT COUNT(*) as total FROM posts WHERE tenant_id = ?";
        $params = [$tenantId];

        if ($status !== 'all') {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        return (int)Database::query($sql, $params)->fetch()['total'];
    }
}
