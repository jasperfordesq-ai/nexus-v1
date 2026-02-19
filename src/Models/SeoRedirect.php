<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class SeoRedirect
{
    /**
     * Get all redirects for current tenant
     */
    public static function all(): array
    {
        $tenantId = TenantContext::getId();
        return Database::query(
            "SELECT * FROM seo_redirects WHERE tenant_id = ? ORDER BY source_url ASC",
            [$tenantId]
        )->fetchAll();
    }

    /**
     * Find redirect by source URL
     */
    public static function findBySource(string $sourceUrl): ?array
    {
        $tenantId = TenantContext::getId();
        $results = Database::query(
            "SELECT * FROM seo_redirects WHERE tenant_id = ? AND source_url = ? LIMIT 1",
            [$tenantId, $sourceUrl]
        )->fetchAll();
        return $results[0] ?? null;
    }

    /**
     * Create new redirect
     */
    public static function create(string $sourceUrl, string $destinationUrl): int
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare(
            "INSERT INTO seo_redirects (tenant_id, source_url, destination_url, hits, created_at)
             VALUES (?, ?, ?, 0, NOW())
             ON DUPLICATE KEY UPDATE destination_url = VALUES(destination_url)"
        );
        $stmt->execute([$tenantId, $sourceUrl, $destinationUrl]);

        return $db->lastInsertId();
    }

    /**
     * Delete redirect by ID
     */
    public static function delete(int $id): bool
    {
        $tenantId = TenantContext::getId();
        return Database::query(
            "DELETE FROM seo_redirects WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        ) !== false;
    }

    /**
     * Increment hit counter
     */
    public static function incrementHits(int $id): void
    {
        Database::query(
            "UPDATE seo_redirects SET hits = hits + 1 WHERE id = ?",
            [$id]
        );
    }

    /**
     * Check for redirect and return destination if found
     */
    public static function checkRedirect(string $requestUri): ?string
    {
        $redirect = self::findBySource($requestUri);

        if ($redirect) {
            self::incrementHits($redirect['id']);
            return $redirect['destination_url'];
        }

        return null;
    }
}
