<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;

/**
 * FederationExternalApiClient
 *
 * HTTP client for making authenticated API calls to external federation partners.
 * Supports multiple authentication methods: API Key, HMAC, OAuth2.
 */
class FederationExternalApiClient
{
    private array $partner;
    private ?string $decryptedApiKey = null;
    private ?string $decryptedSigningSecret = null;

    public function __construct(array $partner)
    {
        $this->partner = $partner;

        // Decrypt credentials
        if (!empty($partner['api_key'])) {
            $this->decryptedApiKey = FederationExternalPartnerService::decryptApiKey($partner['api_key']);
        }
        if (!empty($partner['signing_secret'])) {
            $this->decryptedSigningSecret = FederationExternalPartnerService::decryptApiKey($partner['signing_secret']);
        }
    }

    /**
     * Test connection to the partner API
     */
    public function testConnection(): array
    {
        return $this->get('');
    }

    /**
     * Get partner timebanks
     */
    public function getTimebanks(): array
    {
        return $this->get('/timebanks');
    }

    /**
     * Search members on partner platform
     */
    public function searchMembers(array $params = []): array
    {
        return $this->get('/members', $params);
    }

    /**
     * Get a specific member from partner platform
     */
    public function getMember(int $memberId): array
    {
        return $this->get("/members/{$memberId}");
    }

    /**
     * Search listings on partner platform
     */
    public function searchListings(array $params = []): array
    {
        return $this->get('/listings', $params);
    }

    /**
     * Get a specific listing from partner platform
     */
    public function getListing(int $listingId): array
    {
        return $this->get("/listings/{$listingId}");
    }

    /**
     * Send a message through the partner platform
     *
     * Transforms field names to match the standard federation API format:
     * - receiver_id -> recipient_id
     * - Adds recipient_tenant_id from partner config if not provided
     */
    public function sendMessage(array $data): array
    {
        // Transform to standard federation message format
        $messageData = [
            'sender_id' => $data['sender_id'] ?? null,
            'sender_name' => $data['sender_name'] ?? null,
            'sender_email' => $data['sender_email'] ?? null,
            'recipient_id' => $data['receiver_id'] ?? $data['recipient_id'] ?? null,
            'recipient_tenant_id' => $data['recipient_tenant_id'] ?? ($this->partner['default_tenant_id'] ?? 1),
            'subject' => $data['subject'] ?? '',
            'body' => $data['body'] ?? '',
        ];

        // Remove null values
        $messageData = array_filter($messageData, fn($v) => $v !== null);

        return $this->post('/messages', $messageData);
    }

    /**
     * Initiate a transaction with the partner platform
     */
    public function createTransaction(array $data): array
    {
        return $this->post('/transactions', $data);
    }

    /**
     * Make a GET request to the partner API
     */
    public function get(string $endpoint, array $params = []): array
    {
        $url = $this->buildUrl($endpoint);
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $this->request('GET', $url);
    }

    /**
     * Make a POST request to the partner API
     */
    public function post(string $endpoint, array $data = []): array
    {
        $url = $this->buildUrl($endpoint);
        return $this->request('POST', $url, $data);
    }

    /**
     * Build full URL from endpoint
     */
    private function buildUrl(string $endpoint): string
    {
        $baseUrl = rtrim($this->partner['base_url'], '/');
        $apiPath = rtrim($this->partner['api_path'] ?? '/api/v1/federation', '/');
        $endpoint = '/' . ltrim($endpoint, '/');

        return $baseUrl . $apiPath . $endpoint;
    }

    /**
     * Make an HTTP request with authentication
     */
    private function request(string $method, string $url, array $data = []): array
    {
        $startTime = microtime(true);

        $ch = curl_init();

        // Build headers based on auth method
        $headers = $this->buildHeaders($method, $url, $data);

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'NexusFederation/1.0',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                $jsonBody = json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $endTime = microtime(true);
        $responseTimeMs = (int)(($endTime - $startTime) * 1000);

        // Log the request
        $this->logRequest($url, $method, $data, $httpCode, $response, $responseTimeMs, $error);

        // Parse response
        if ($error) {
            return [
                'success' => false,
                'error' => "Connection error: {$error}",
                'http_code' => 0
            ];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $decoded,
                'http_code' => $httpCode,
                'response_time_ms' => $responseTimeMs
            ];
        }

        return [
            'success' => false,
            'error' => $decoded['message'] ?? $decoded['error'] ?? "HTTP {$httpCode} error",
            'http_code' => $httpCode,
            'data' => $decoded
        ];
    }

    /**
     * Build authentication headers based on auth method
     */
    private function buildHeaders(string $method, string $url, array $data = []): array
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $authMethod = $this->partner['auth_method'] ?? 'api_key';

        switch ($authMethod) {
            case 'hmac':
                $headers = array_merge($headers, $this->buildHmacHeaders($method, $url, $data));
                break;

            case 'oauth2':
                // TODO: Implement OAuth2 token fetching
                break;

            case 'api_key':
            default:
                if ($this->decryptedApiKey) {
                    $headers[] = 'Authorization: Bearer ' . $this->decryptedApiKey;
                }
                break;
        }

        return $headers;
    }

    /**
     * Build HMAC authentication headers
     */
    private function buildHmacHeaders(string $method, string $url, array $data = []): array
    {
        $timestamp = date('c');
        $body = !empty($data) ? json_encode($data) : '';

        // Parse URL to get path
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '/';
        if (!empty($parsedUrl['query'])) {
            $path .= '?' . $parsedUrl['query'];
        }

        // Build string to sign
        $stringToSign = implode("\n", [
            strtoupper($method),
            $path,
            $timestamp,
            $body
        ]);

        // Generate signature
        $signature = hash_hmac('sha256', $stringToSign, $this->decryptedSigningSecret ?? '');

        // Generate a platform ID (use tenant ID or a configured value)
        $platformId = $this->partner['platform_id'] ?? 'nexus-' . $this->partner['tenant_id'];

        return [
            'X-Federation-Signature: ' . $signature,
            'X-Federation-Timestamp: ' . $timestamp,
            'X-Federation-Platform-Id: ' . $platformId,
        ];
    }

    /**
     * Log API request for debugging and auditing
     */
    private function logRequest(string $url, string $method, array $requestData, int $httpCode, ?string $response, int $responseTimeMs, ?string $error): void
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                INSERT INTO federation_external_partner_logs
                (partner_id, endpoint, method, request_body, response_code, response_body, response_time_ms, success, error_message, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $this->partner['id'],
                $url,
                $method,
                !empty($requestData) ? json_encode($requestData) : null,
                $httpCode,
                $response ? substr($response, 0, 10000) : null, // Limit response size
                $responseTimeMs,
                ($httpCode >= 200 && $httpCode < 300) ? 1 : 0,
                $error
            ]);
        } catch (\Exception $e) {
            // Don't fail the request if logging fails
            error_log("Failed to log external partner request: " . $e->getMessage());
        }
    }
}
