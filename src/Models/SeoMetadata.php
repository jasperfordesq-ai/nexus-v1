<?php

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class SeoMetadata
{
    /**
     * Get SEO metadata for an entity
     */
    public static function get($entityType, $entityId = null)
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $sql = "SELECT * FROM seo_metadata WHERE tenant_id = ? AND entity_type = ?";
        $params = [$tenantId, $entityType];

        if ($entityId !== null) {
            $sql .= " AND entity_id = ?";
            $params[] = $entityId;
        } else {
            $sql .= " AND entity_id IS NULL";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * Save or Update SEO metadata
     */
    public static function save($entityType, $entityId, $data)
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        // Check exists
        $existing = self::get($entityType, $entityId);

        if ($existing) {
            $sql = "UPDATE seo_metadata SET 
                meta_title = ?, 
                meta_description = ?, 
                meta_keywords = ?, 
                canonical_url = ?, 
                og_image_url = ?, 
                noindex = ?
                WHERE id = ?";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                $data['meta_title'] ?? null,
                $data['meta_description'] ?? null,
                $data['meta_keywords'] ?? null,
                $data['canonical_url'] ?? null,
                $data['og_image_url'] ?? null,
                isset($data['noindex']) ? 1 : 0,
                $existing['id']
            ]);
        } else {
            $sql = "INSERT INTO seo_metadata (tenant_id, entity_type, entity_id, meta_title, meta_description, meta_keywords, canonical_url, og_image_url, noindex) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                $tenantId,
                $entityType,
                $entityId, // Can be null
                $data['meta_title'] ?? null,
                $data['meta_description'] ?? null,
                $data['meta_keywords'] ?? null,
                $data['canonical_url'] ?? null,
                $data['og_image_url'] ?? null,
                isset($data['noindex']) ? 1 : 0
            ]);
        }
    }
}
