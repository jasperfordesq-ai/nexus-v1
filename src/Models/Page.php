<?php

namespace Nexus\Models;

use Nexus\Core\Database;

class Page
{
    public static function all()
    {
        $tenantId = \Nexus\Core\TenantContext::getId();
        $db = Database::getConnection();

        // Ensure table exists or handle error gracefull if migration not run?
        // Assuming table 'pages' exists from previous migration context
        try {
            $stmt = $db->prepare("SELECT title, slug FROM pages WHERE tenant_id = ? ORDER BY title ASC");
            $stmt->execute([$tenantId]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            return []; // Table might not exist yet
        }
    }

    public static function findById($id)
    {
        $tenantId = \Nexus\Core\TenantContext::getId();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM pages WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        return $stmt->fetch();
    }

    /**
     * Find a published page by slug
     * @param string $slug
     * @param int $tenantId
     * @return array|false Page data or false if not found
     */
    public static function findBySlug(string $slug, int $tenantId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM pages WHERE slug = ? AND tenant_id = ? AND is_published = 1");
        $stmt->execute([$slug, $tenantId]);
        return $stmt->fetch();
    }

    /**
     * Find ANY page by slug (including unpublished - for admin preview)
     * @param string $slug
     * @param int $tenantId
     * @return array|false
     */
    public static function findBySlugAny(string $slug, int $tenantId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM pages WHERE slug = ? AND tenant_id = ?");
        $stmt->execute([$slug, $tenantId]);
        return $stmt->fetch();
    }

    /**
     * Update page settings (slug, publish status, menu settings)
     * @param int $id
     * @param int $tenantId
     * @param array $data
     * @return bool
     */
    public static function updateSettings(int $id, int $tenantId, array $data): bool
    {
        $db = Database::getConnection();

        $allowedFields = ['title', 'slug', 'is_published', 'publish_at', 'show_in_menu', 'menu_location'];
        $updates = [];
        $values = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $updates[] = "updated_at = NOW()";
        $values[] = $id;
        $values[] = $tenantId;

        $sql = "UPDATE pages SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute($values);
    }
}
