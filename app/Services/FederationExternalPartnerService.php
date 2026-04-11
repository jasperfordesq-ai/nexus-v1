<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FederationExternalPartnerService — Manages external federation partner connections.
 *
 * Handles CRUD for federation_external_partners including API credentials,
 * status management, and partner logs. All credentials (api_key, signing_secret,
 * oauth_client_secret) are encrypted at rest using Laravel's Crypt facade.
 */
class FederationExternalPartnerService
{
    /**
     * Valid status values for external partners.
     */
    private const VALID_STATUSES = ['pending', 'active', 'suspended', 'failed'];

    /**
     * IP ranges that must never be used as partner base_urls (SSRF prevention).
     */
    private const BLOCKED_IP_RANGES = [
        '127.',          // loopback
        '10.',           // RFC 1918
        '172.16.', '172.17.', '172.18.', '172.19.', '172.20.', '172.21.',
        '172.22.', '172.23.', '172.24.', '172.25.', '172.26.', '172.27.',
        '172.28.', '172.29.', '172.30.', '172.31.', // RFC 1918
        '192.168.',      // RFC 1918
        '169.254.',      // link-local / cloud metadata
        '0.',            // non-routable
        'fd',            // IPv6 ULA
        '::1',           // IPv6 loopback
        'fe80:',         // IPv6 link-local
    ];

    private const BLOCKED_HOSTNAMES = [
        'localhost',
        'metadata.google.internal',
        'metadata.google',
        'kubernetes.default',
    ];

    /**
     * Credential fields that must be encrypted at rest.
     */
    private const ENCRYPTED_FIELDS = ['api_key', 'signing_secret', 'oauth_client_secret'];

    /**
     * Get all external partners for a tenant.
     */
    public static function getAll(int $tenantId): array
    {
        try {
            $rows = DB::select(
                "SELECT * FROM federation_external_partners
                 WHERE tenant_id = ?
                 ORDER BY created_at DESC",
                [$tenantId]
            );

            return array_map(fn ($row) => self::formatPartner($row), $rows);
        } catch (\Throwable $e) {
            Log::error('[FederationExternalPartner] getAll failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get a single external partner by ID, scoped to tenant.
     */
    public static function getById(int $id, int $tenantId): ?array
    {
        try {
            $row = DB::selectOne(
                "SELECT * FROM federation_external_partners
                 WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            if (!$row) {
                return null;
            }

            return self::formatPartner($row);
        } catch (\Throwable $e) {
            Log::error('[FederationExternalPartner] getById failed', [
                'id' => $id,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Check if a base_url already exists for a tenant.
     *
     * @param string   $baseUrl   The URL to check
     * @param int      $tenantId  Tenant scope
     * @param int|null $excludeId Optional partner ID to exclude (for updates)
     */
    public static function urlExists(string $baseUrl, int $tenantId, ?int $excludeId = null): bool
    {
        try {
            $query = "SELECT COUNT(*) as cnt FROM federation_external_partners
                      WHERE base_url = ? AND tenant_id = ?";
            $params = [$baseUrl, $tenantId];

            if ($excludeId !== null) {
                $query .= " AND id != ?";
                $params[] = $excludeId;
            }

            $result = DB::selectOne($query, $params);

            return $result && (int) $result->cnt > 0;
        } catch (\Throwable $e) {
            Log::error('[FederationExternalPartner] urlExists failed', [
                'base_url' => $baseUrl,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create a new external partner.
     *
     * @param array $data    Partner data (name, base_url, auth_method, api_key, etc.)
     * @param int   $tenantId Tenant scope
     * @param int   $userId   User performing the action
     * @return array ['success' => bool, 'id' => int|null, 'error' => string|null]
     */
    public static function create(array $data, int $tenantId, int $userId): array
    {
        // Validate required fields
        if (empty($data['name'])) {
            return ['success' => false, 'error' => 'Partner name is required'];
        }

        if (empty($data['base_url'])) {
            return ['success' => false, 'error' => 'Base URL is required'];
        }

        // SSRF protection: validate base_url is not an internal/private address
        $ssrfError = self::validateBaseUrl($data['base_url']);
        if ($ssrfError) {
            return ['success' => false, 'error' => $ssrfError];
        }

        // Check URL uniqueness
        if (self::urlExists($data['base_url'], $tenantId)) {
            return ['success' => false, 'error' => 'A partner with this URL already exists for this tenant'];
        }

        try {
            // Encrypt credential fields before storage
            $apiKey = !empty($data['api_key']) ? self::encryptApiKey($data['api_key']) : null;
            $signingSecret = !empty($data['signing_secret']) ? self::encryptApiKey($data['signing_secret']) : null;
            $oauthClientSecret = !empty($data['oauth_client_secret']) ? self::encryptApiKey($data['oauth_client_secret']) : null;

            // Validate protocol_type if provided
            $validProtocols = ['nexus', 'timeoverflow', 'komunitin', 'credit_commons'];
            $protocolType = $data['protocol_type'] ?? 'nexus';
            if (!in_array($protocolType, $validProtocols, true)) {
                return ['success' => false, 'error' => 'Invalid protocol_type. Must be one of: ' . implode(', ', $validProtocols)];
            }

            DB::insert(
                "INSERT INTO federation_external_partners
                 (tenant_id, name, description, base_url, api_path, api_key, auth_method,
                  protocol_type, signing_secret, oauth_client_id, oauth_client_secret, oauth_token_url,
                  allow_member_search, allow_listing_search, allow_messaging,
                  allow_transactions, allow_events, allow_groups,
                  status, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())",
                [
                    $tenantId,
                    $data['name'],
                    $data['description'] ?? null,
                    $data['base_url'],
                    $data['api_path'] ?? '/api/v1/federation',
                    $apiKey,
                    $data['auth_method'] ?? 'api_key',
                    $protocolType,
                    $signingSecret,
                    $data['oauth_client_id'] ?? null,
                    $oauthClientSecret,
                    $data['oauth_token_url'] ?? null,
                    (int) ($data['allow_member_search'] ?? 1),
                    (int) ($data['allow_listing_search'] ?? 1),
                    (int) ($data['allow_messaging'] ?? 1),
                    (int) ($data['allow_transactions'] ?? 1),
                    (int) ($data['allow_events'] ?? 0),
                    (int) ($data['allow_groups'] ?? 0),
                    $userId,
                ]
            );

            $id = (int) DB::getPdo()->lastInsertId();

            // Audit log
            FederationAuditService::log(
                'external_partner.created',
                $tenantId,
                null,
                $userId,
                ['partner_id' => $id, 'name' => $data['name'], 'base_url' => $data['base_url']],
                FederationAuditService::LEVEL_INFO
            );

            Log::info('[FederationExternalPartner] Partner created', [
                'id' => $id,
                'tenant_id' => $tenantId,
                'name' => $data['name'],
            ]);

            return ['success' => true, 'id' => $id];
        } catch (\Throwable $e) {
            Log::error('[FederationExternalPartner] create failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Failed to create external partner: ' . $e->getMessage()];
        }
    }

    /**
     * Update an existing external partner.
     *
     * @param int   $id       Partner ID
     * @param array $data     Fields to update
     * @param int   $tenantId Tenant scope
     * @param int   $userId   User performing the action
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function update(int $id, array $data, int $tenantId, int $userId): array
    {
        // Verify partner exists and belongs to tenant
        $existing = self::getById($id, $tenantId);
        if (!$existing) {
            return ['success' => false, 'error' => 'Partner not found'];
        }

        // If base_url is being changed, validate and check uniqueness
        if (!empty($data['base_url']) && $data['base_url'] !== $existing['base_url']) {
            $ssrfError = self::validateBaseUrl($data['base_url']);
            if ($ssrfError) {
                return ['success' => false, 'error' => $ssrfError];
            }
            if (self::urlExists($data['base_url'], $tenantId, $id)) {
                return ['success' => false, 'error' => 'A partner with this URL already exists for this tenant'];
            }
        }

        // Validate status if provided
        if (array_key_exists('status', $data) && !in_array($data['status'], self::VALID_STATUSES, true)) {
            return ['success' => false, 'error' => 'Invalid status value. Must be one of: ' . implode(', ', self::VALID_STATUSES)];
        }

        // Validate auth_method if provided
        $validAuthMethods = ['api_key', 'hmac', 'oauth2'];
        if (array_key_exists('auth_method', $data) && !in_array($data['auth_method'], $validAuthMethods, true)) {
            return ['success' => false, 'error' => 'Invalid auth_method. Must be one of: ' . implode(', ', $validAuthMethods)];
        }

        // Validate protocol_type if provided
        $validProtocols = ['nexus', 'timeoverflow', 'komunitin', 'credit_commons'];
        if (array_key_exists('protocol_type', $data) && !in_array($data['protocol_type'], $validProtocols, true)) {
            return ['success' => false, 'error' => 'Invalid protocol_type. Must be one of: ' . implode(', ', $validProtocols)];
        }

        try {
            $sets = [];
            $params = [];

            // Updatable fields
            $plainFields = [
                'name', 'description', 'base_url', 'api_path', 'auth_method',
                'protocol_type', 'oauth_client_id', 'oauth_token_url', 'status',
                'allow_member_search', 'allow_listing_search', 'allow_messaging',
                'allow_transactions', 'allow_events', 'allow_groups',
            ];

            foreach ($plainFields as $field) {
                if (array_key_exists($field, $data)) {
                    $sets[] = "{$field} = ?";
                    $params[] = $data[$field];
                }
            }

            // Encrypted fields
            foreach (self::ENCRYPTED_FIELDS as $field) {
                if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                    $sets[] = "{$field} = ?";
                    $params[] = self::encryptApiKey($data[$field]);
                }
            }

            if (empty($sets)) {
                return ['success' => false, 'error' => 'No fields to update'];
            }

            $sets[] = "updated_at = NOW()";
            $params[] = $id;
            $params[] = $tenantId;

            $sql = "UPDATE federation_external_partners SET " . implode(', ', $sets)
                 . " WHERE id = ? AND tenant_id = ?";

            DB::update($sql, $params);

            // Audit log
            FederationAuditService::log(
                'external_partner.updated',
                $tenantId,
                null,
                $userId,
                ['partner_id' => $id, 'fields' => array_keys($data)],
                FederationAuditService::LEVEL_INFO
            );

            Log::info('[FederationExternalPartner] Partner updated', [
                'id' => $id,
                'tenant_id' => $tenantId,
            ]);

            return ['success' => true, 'id' => $id];
        } catch (\Throwable $e) {
            Log::error('[FederationExternalPartner] update failed', [
                'id' => $id,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Failed to update external partner: ' . $e->getMessage()];
        }
    }

    /**
     * Delete an external partner.
     *
     * @param int $id       Partner ID
     * @param int $tenantId Tenant scope
     * @param int $userId   User performing the action
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function delete(int $id, int $tenantId, int $userId): array
    {
        // Verify partner exists and belongs to tenant
        $existing = self::getById($id, $tenantId);
        if (!$existing) {
            return ['success' => false, 'error' => 'Partner not found'];
        }

        try {
            // Delete logs first (FK cascade should handle this, but be explicit)
            DB::delete(
                "DELETE FROM federation_external_partner_logs WHERE partner_id = ?",
                [$id]
            );

            // Delete the partner (hard delete — table has no deleted_at column)
            DB::delete(
                "DELETE FROM federation_external_partners WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            // Audit log
            FederationAuditService::log(
                'external_partner.deleted',
                $tenantId,
                null,
                $userId,
                ['partner_id' => $id, 'name' => $existing['name']],
                FederationAuditService::LEVEL_INFO
            );

            Log::info('[FederationExternalPartner] Partner deleted', [
                'id' => $id,
                'tenant_id' => $tenantId,
            ]);

            return ['success' => true];
        } catch (\Throwable $e) {
            Log::error('[FederationExternalPartner] delete failed', [
                'id' => $id,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Failed to delete external partner: ' . $e->getMessage()];
        }
    }

    /**
     * Update the status of an external partner.
     *
     * @param int    $id       Partner ID
     * @param string $status   New status (active, inactive, suspended, error)
     * @param int    $tenantId Tenant scope
     * @param int    $userId   User performing the action
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function updateStatus(int $id, string $status, int $tenantId, int $userId): array
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            return [
                'success' => false,
                'error' => 'Invalid status. Must be one of: ' . implode(', ', self::VALID_STATUSES),
            ];
        }

        // Verify partner exists and belongs to tenant
        $existing = self::getById($id, $tenantId);
        if (!$existing) {
            return ['success' => false, 'error' => 'Partner not found'];
        }

        try {
            DB::update(
                "UPDATE federation_external_partners
                 SET status = ?, updated_at = NOW()
                 WHERE id = ? AND tenant_id = ?",
                [$status, $id, $tenantId]
            );

            // Audit log
            FederationAuditService::log(
                'external_partner.status_changed',
                $tenantId,
                null,
                $userId,
                ['partner_id' => $id, 'old_status' => $existing['status'], 'new_status' => $status],
                FederationAuditService::LEVEL_INFO
            );

            Log::info('[FederationExternalPartner] Partner status updated', [
                'id' => $id,
                'tenant_id' => $tenantId,
                'status' => $status,
            ]);

            return ['success' => true, 'id' => $id, 'status' => $status];
        } catch (\Throwable $e) {
            Log::error('[FederationExternalPartner] updateStatus failed', [
                'id' => $id,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Failed to update partner status: ' . $e->getMessage()];
        }
    }

    /**
     * Get all active partners for a tenant.
     */
    public static function getActivePartners(int $tenantId): array
    {
        try {
            $rows = DB::select(
                "SELECT * FROM federation_external_partners
                 WHERE tenant_id = ? AND status = 'active'
                 ORDER BY name ASC",
                [$tenantId]
            );

            return array_map(fn ($row) => self::formatPartner($row), $rows);
        } catch (\Throwable $e) {
            Log::error('[FederationExternalPartner] getActivePartners failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get active partners that have listing search enabled.
     */
    public static function getActivePartnersForListings(int $tenantId): array
    {
        try {
            $rows = DB::select(
                "SELECT * FROM federation_external_partners
                 WHERE tenant_id = ? AND status = 'active' AND allow_listing_search = 1
                 ORDER BY name ASC",
                [$tenantId]
            );

            return array_map(fn ($row) => self::formatPartner($row), $rows);
        } catch (\Throwable $e) {
            Log::error('[FederationExternalPartner] getActivePartnersForListings failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get API call logs for a partner.
     *
     * @param int $partnerId Partner ID
     * @param int $limit     Max number of logs to return
     */
    public static function getLogs(int $partnerId, int $tenantId, int $limit = 50): array
    {
        try {
            // Verify partner belongs to tenant before returning logs
            $owner = DB::selectOne(
                "SELECT id FROM federation_external_partners WHERE id = ? AND tenant_id = ?",
                [$partnerId, $tenantId]
            );
            if (!$owner) {
                return [];
            }

            $rows = DB::select(
                "SELECT * FROM federation_external_partner_logs
                 WHERE partner_id = ?
                 ORDER BY created_at DESC
                 LIMIT ?",
                [$partnerId, $limit]
            );

            return array_map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'partner_id' => (int) $row->partner_id,
                    'endpoint' => $row->endpoint,
                    'method' => $row->method,
                    'request_body' => $row->request_body,
                    'response_code' => $row->response_code ? (int) $row->response_code : null,
                    'response_body' => $row->response_body,
                    'response_time_ms' => $row->response_time_ms ? (int) $row->response_time_ms : null,
                    'success' => (bool) $row->success,
                    'error_message' => $row->error_message,
                    'created_at' => $row->created_at,
                ];
            }, $rows);
        } catch (\Throwable $e) {
            Log::error('[FederationExternalPartner] getLogs failed', [
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Decrypt an encrypted API key.
     */
    private static function decryptApiKey(string $encryptedKey): string
    {
        return Crypt::decryptString($encryptedKey);
    }

    /**
     * Encrypt an API key for storage.
     */
    private static function encryptApiKey(string $apiKey): string
    {
        return Crypt::encryptString($apiKey);
    }

    /**
     * Format a database row into a partner array.
     */
    private static function formatPartner(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'tenant_id' => (int) $row->tenant_id,
            'name' => $row->name,
            'description' => $row->description,
            'base_url' => $row->base_url,
            'api_path' => $row->api_path,
            'auth_method' => $row->auth_method,
            'oauth_client_id' => $row->oauth_client_id ?? null,
            'oauth_token_url' => $row->oauth_token_url ?? null,
            'status' => $row->status,
            'verified_at' => $row->verified_at,
            'last_sync_at' => $row->last_sync_at,
            'last_error' => $row->last_error,
            'error_count' => (int) $row->error_count,
            'partner_name' => $row->partner_name,
            'partner_version' => $row->partner_version,
            'partner_member_count' => $row->partner_member_count ? (int) $row->partner_member_count : null,
            'partner_metadata' => $row->partner_metadata ? json_decode($row->partner_metadata, true) : null,
            'allow_member_search' => (bool) $row->allow_member_search,
            'allow_listing_search' => (bool) $row->allow_listing_search,
            'allow_messaging' => (bool) $row->allow_messaging,
            'allow_transactions' => (bool) $row->allow_transactions,
            'allow_events' => (bool) $row->allow_events,
            'allow_groups' => (bool) $row->allow_groups,
            'created_by' => $row->created_by ? (int) $row->created_by : null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Validate a base_url is safe (SSRF prevention).
     *
     * Blocks private/internal IPs, localhost, link-local, and cloud metadata endpoints.
     *
     * @return string|null Error message if URL is unsafe, null if safe
     */
    public static function validateBaseUrl(string $url): ?string
    {
        $parsed = parse_url($url);

        if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
            return 'Invalid URL format';
        }

        if (!in_array($parsed['scheme'], ['https', 'http'], true)) {
            return 'URL scheme must be http or https';
        }

        $host = strtolower($parsed['host']);

        // Block known dangerous hostnames
        foreach (self::BLOCKED_HOSTNAMES as $blocked) {
            if ($host === $blocked) {
                return 'URL host is not allowed (internal/reserved hostname)';
            }
        }

        // Resolve hostname to IP and check against blocked ranges
        $ips = gethostbynamel($host);
        if ($ips === false) {
            // If the host is a literal IP address, check it directly
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                $ips = [$host];
            } else {
                // DNS resolution failed for a non-IP hostname — reject to prevent
                // DNS rebinding attacks where the hostname resolves to an internal
                // IP at request time but not at validation time
                return 'URL hostname could not be resolved (DNS lookup failed)';
            }
        }

        foreach ($ips as $ip) {
            foreach (self::BLOCKED_IP_RANGES as $range) {
                if (str_starts_with($ip, $range)) {
                    return 'URL resolves to a private/internal IP address';
                }
            }

            // Also check IPv4-mapped IPv6 (::ffff:127.0.0.1)
            if (str_starts_with($ip, '::ffff:')) {
                $mappedIp = substr($ip, 7);
                foreach (self::BLOCKED_IP_RANGES as $range) {
                    if (str_starts_with($mappedIp, $range)) {
                        return 'URL resolves to a private/internal IP address';
                    }
                }
            }
        }

        return null;
    }
}
