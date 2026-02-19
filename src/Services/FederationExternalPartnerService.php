<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * FederationExternalPartnerService
 *
 * Manages external federation partners (servers outside this installation).
 * Handles CRUD operations, connection testing, and API calls to external partners.
 */
class FederationExternalPartnerService
{
    /**
     * Get all external partners for a tenant
     */
    public static function getAll(int $tenantId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT
                fep.*,
                u.first_name as creator_first_name,
                u.last_name as creator_last_name
            FROM federation_external_partners fep
            LEFT JOIN users u ON u.id = fep.created_by
            WHERE fep.tenant_id = ?
            ORDER BY fep.name ASC
        ");
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get a single external partner by ID
     */
    public static function getById(int $id, int $tenantId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT * FROM federation_external_partners
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$id, $tenantId]);
        $partner = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $partner ?: null;
    }

    /**
     * Check if a URL already exists for this tenant
     */
    public static function urlExists(string $baseUrl, int $tenantId, ?int $excludeId = null): bool
    {
        $db = Database::getInstance();

        // Normalize URL
        $baseUrl = rtrim($baseUrl, '/');

        $sql = "SELECT id FROM federation_external_partners WHERE tenant_id = ? AND base_url = ?";
        $params = [$tenantId, $baseUrl];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }

    /**
     * Create a new external partner
     */
    public static function create(array $data, int $tenantId, int $userId): array
    {
        $db = Database::getInstance();

        // Normalize URL
        $baseUrl = rtrim($data['base_url'], '/');

        // Check for duplicate URL
        if (self::urlExists($baseUrl, $tenantId)) {
            return ['success' => false, 'error' => 'A partner with this URL already exists'];
        }

        // Encrypt API key if provided
        $apiKey = !empty($data['api_key']) ? self::encryptApiKey($data['api_key']) : null;
        $signingSecret = !empty($data['signing_secret']) ? self::encryptApiKey($data['signing_secret']) : null;

        $stmt = $db->prepare("
            INSERT INTO federation_external_partners (
                tenant_id, name, description, base_url, api_path,
                api_key, auth_method, signing_secret,
                allow_member_search, allow_listing_search, allow_messaging,
                allow_transactions, allow_events, allow_groups,
                status, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
        ");

        $stmt->execute([
            $tenantId,
            $data['name'],
            $data['description'] ?? null,
            $baseUrl,
            $data['api_path'] ?? '/api/v1/federation',
            $apiKey,
            $data['auth_method'] ?? 'api_key',
            $signingSecret,
            $data['allow_member_search'] ?? 1,
            $data['allow_listing_search'] ?? 1,
            $data['allow_messaging'] ?? 1,
            $data['allow_transactions'] ?? 1,
            $data['allow_events'] ?? 0,
            $data['allow_groups'] ?? 0,
            $userId
        ]);

        $partnerId = $db->lastInsertId();

        // Log the creation
        FederationAuditService::log(
            'external_partner_created',
            $tenantId,
            null,
            $userId,
            ['partner_name' => $data['name'], 'base_url' => $baseUrl]
        );

        return ['success' => true, 'id' => $partnerId];
    }

    /**
     * Update an external partner
     */
    public static function update(int $id, array $data, int $tenantId, int $userId): array
    {
        $db = Database::getInstance();

        // Verify ownership
        $existing = self::getById($id, $tenantId);
        if (!$existing) {
            return ['success' => false, 'error' => 'Partner not found'];
        }

        // Normalize URL
        $baseUrl = rtrim($data['base_url'], '/');

        // Check for duplicate URL (excluding this record)
        if (self::urlExists($baseUrl, $tenantId, $id)) {
            return ['success' => false, 'error' => 'A partner with this URL already exists'];
        }

        // Build update query dynamically
        $fields = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'base_url' => $baseUrl,
            'api_path' => $data['api_path'] ?? '/api/v1/federation',
            'auth_method' => $data['auth_method'] ?? 'api_key',
            'allow_member_search' => $data['allow_member_search'] ?? 1,
            'allow_listing_search' => $data['allow_listing_search'] ?? 1,
            'allow_messaging' => $data['allow_messaging'] ?? 1,
            'allow_transactions' => $data['allow_transactions'] ?? 1,
            'allow_events' => $data['allow_events'] ?? 0,
            'allow_groups' => $data['allow_groups'] ?? 0,
        ];

        // Only update API key if provided (not empty)
        if (!empty($data['api_key'])) {
            $fields['api_key'] = self::encryptApiKey($data['api_key']);
        }

        if (!empty($data['signing_secret'])) {
            $fields['signing_secret'] = self::encryptApiKey($data['signing_secret']);
        }

        $setClauses = [];
        $params = [];
        foreach ($fields as $field => $value) {
            $setClauses[] = "$field = ?";
            $params[] = $value;
        }

        $params[] = $id;
        $params[] = $tenantId;

        $stmt = $db->prepare("
            UPDATE federation_external_partners
            SET " . implode(', ', $setClauses) . "
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute($params);

        // Log the update
        FederationAuditService::log(
            'external_partner_updated',
            $tenantId,
            null,
            $userId,
            ['partner_id' => $id, 'partner_name' => $data['name']]
        );

        return ['success' => true];
    }

    /**
     * Delete an external partner
     */
    public static function delete(int $id, int $tenantId, int $userId): array
    {
        $db = Database::getInstance();

        $existing = self::getById($id, $tenantId);
        if (!$existing) {
            return ['success' => false, 'error' => 'Partner not found'];
        }

        $stmt = $db->prepare("DELETE FROM federation_external_partners WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);

        // Log the deletion
        FederationAuditService::log(
            'external_partner_deleted',
            $tenantId,
            null,
            $userId,
            ['partner_name' => $existing['name'], 'base_url' => $existing['base_url']]
        );

        return ['success' => true];
    }

    /**
     * Test connection to an external partner
     */
    public static function testConnection(int $id, int $tenantId): array
    {
        $partner = self::getById($id, $tenantId);
        if (!$partner) {
            return ['success' => false, 'error' => 'Partner not found'];
        }

        $client = new FederationExternalApiClient($partner);
        $result = $client->testConnection();

        $db = Database::getInstance();

        if ($result['success']) {
            // Update partner with metadata from API
            $stmt = $db->prepare("
                UPDATE federation_external_partners
                SET status = 'active',
                    verified_at = NOW(),
                    last_error = NULL,
                    error_count = 0,
                    partner_name = ?,
                    partner_version = ?,
                    partner_metadata = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $result['data']['name'] ?? null,
                $result['data']['version'] ?? null,
                json_encode($result['data']),
                $id
            ]);
        } else {
            // Update error status
            $stmt = $db->prepare("
                UPDATE federation_external_partners
                SET status = 'failed',
                    last_error = ?,
                    error_count = error_count + 1
                WHERE id = ?
            ");
            $stmt->execute([$result['error'], $id]);
        }

        return $result;
    }

    /**
     * Update partner status
     */
    public static function updateStatus(int $id, string $status, int $tenantId, int $userId): array
    {
        $db = Database::getInstance();

        $existing = self::getById($id, $tenantId);
        if (!$existing) {
            return ['success' => false, 'error' => 'Partner not found'];
        }

        $stmt = $db->prepare("
            UPDATE federation_external_partners
            SET status = ?
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$status, $id, $tenantId]);

        FederationAuditService::log(
            'external_partner_status_changed',
            $tenantId,
            null,
            $userId,
            ['partner_id' => $id, 'partner_name' => $existing['name'], 'new_status' => $status]
        );

        return ['success' => true];
    }

    /**
     * Get all active external partners for a tenant
     * Used for fetching federated members from external partners
     */
    public static function getActivePartners(int $tenantId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT * FROM federation_external_partners
            WHERE tenant_id = ?
            AND status = 'active'
            AND allow_member_search = 1
            ORDER BY name ASC
        ");
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all active external partners for listings search
     */
    public static function getActivePartnersForListings(int $tenantId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT * FROM federation_external_partners
            WHERE tenant_id = ?
            AND status = 'active'
            AND allow_listing_search = 1
            ORDER BY name ASC
        ");
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get recent API logs for a partner
     */
    public static function getLogs(int $partnerId, int $limit = 50): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT * FROM federation_external_partner_logs
            WHERE partner_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$partnerId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Encrypt API key for storage
     * Uses OpenSSL with a key derived from app secret
     */
    private static function encryptApiKey(string $apiKey): string
    {
        $secret = getenv('APP_KEY') ?: getenv('ENCRYPTION_KEY') ?: 'nexus-default-key';
        $key = hash('sha256', $secret, true);
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($apiKey, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt API key from storage
     */
    public static function decryptApiKey(string $encryptedKey): string
    {
        $secret = getenv('APP_KEY') ?: getenv('ENCRYPTION_KEY') ?: 'nexus-default-key';
        $key = hash('sha256', $secret, true);
        $data = base64_decode($encryptedKey);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }
}
