<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Core;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * HashiCorp Vault Client for Secrets Management
 *
 * Provides secure access to secrets stored in HashiCorp Vault,
 * with support for AppRole authentication, caching, and fallback mechanisms.
 */
class VaultClient
{
    private Client $httpClient;
    private string $vaultAddr;
    private ?string $token = null;
    private array $cache = [];
    private int $cacheTtl;
    private bool $isAvailable = false;

    public function __construct(?string $vaultAddr = null, int $cacheTtl = 300)
    {
        $this->vaultAddr = $vaultAddr ?? getenv('VAULT_ADDR') ?: 'http://vault:8200';
        $this->cacheTtl = $cacheTtl;

        $this->httpClient = new Client([
            'base_uri' => $this->vaultAddr,
            'timeout' => 5.0,
            'connect_timeout' => 2.0,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);

        $this->checkAvailability();
    }

    /**
     * Check if Vault is available
     */
    private function checkAvailability(): void
    {
        try {
            $response = $this->httpClient->get('/v1/sys/health', [
                'timeout' => 2.0,
                'http_errors' => false,
            ]);
            $this->isAvailable = in_array($response->getStatusCode(), [200, 429, 472, 473, 501, 503]);
        } catch (GuzzleException $e) {
            $this->isAvailable = false;
            error_log("Vault not available: " . $e->getMessage());
        }
    }

    /**
     * Check if Vault is available and authenticated
     */
    public function isAvailable(): bool
    {
        return $this->isAvailable && $this->token !== null;
    }

    /**
     * Authenticate using AppRole method
     */
    public function authenticateAppRole(?string $roleId = null, ?string $secretId = null): bool
    {
        $roleId = $roleId ?? getenv('VAULT_ROLE_ID');
        $secretId = $secretId ?? getenv('VAULT_SECRET_ID');

        if (!$roleId || !$secretId) {
            error_log("Vault AppRole credentials not provided");
            return false;
        }

        try {
            $response = $this->httpClient->post('/v1/auth/approle/login', [
                'json' => [
                    'role_id' => $roleId,
                    'secret_id' => $secretId,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $this->token = $data['auth']['client_token'] ?? null;

            if ($this->token) {
                error_log("Vault authentication successful");
                return true;
            }
        } catch (GuzzleException $e) {
            error_log("Vault authentication failed: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Authenticate using token directly (for development)
     */
    public function authenticateToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * Get a secret from Vault (KV v2)
     */
    public function getSecret(string $path): array
    {
        // Check cache first
        $cacheKey = "secret:{$path}";
        if (isset($this->cache[$cacheKey])) {
            $cached = $this->cache[$cacheKey];
            if ($cached['expires'] > time()) {
                return $cached['data'];
            }
            unset($this->cache[$cacheKey]);
        }

        if (!$this->token) {
            throw new \RuntimeException("Vault not authenticated");
        }

        try {
            $response = $this->httpClient->get("/v1/secret/data/{$path}", [
                'headers' => [
                    'X-Vault-Token' => $this->token,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $secrets = $data['data']['data'] ?? [];

            // Cache the result
            $this->cache[$cacheKey] = [
                'data' => $secrets,
                'expires' => time() + $this->cacheTtl,
            ];

            return $secrets;
        } catch (GuzzleException $e) {
            error_log("Vault error fetching {$path}: " . $e->getMessage());
            throw new \RuntimeException("Failed to fetch secret: {$path}");
        }
    }

    /**
     * Get a specific key from a secret path
     */
    public function get(string $path, string $key, $default = null)
    {
        try {
            $secrets = $this->getSecret($path);
            return $secrets[$key] ?? $default;
        } catch (\Exception $e) {
            error_log("Vault get error: " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Write a secret to Vault (KV v2)
     */
    public function putSecret(string $path, array $data): bool
    {
        if (!$this->token) {
            throw new \RuntimeException("Vault not authenticated");
        }

        try {
            $this->httpClient->post("/v1/secret/data/{$path}", [
                'headers' => [
                    'X-Vault-Token' => $this->token,
                ],
                'json' => [
                    'data' => $data,
                ],
            ]);

            // Invalidate cache
            unset($this->cache["secret:{$path}"]);

            return true;
        } catch (GuzzleException $e) {
            error_log("Vault error writing {$path}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a secret from Vault
     */
    public function deleteSecret(string $path): bool
    {
        if (!$this->token) {
            throw new \RuntimeException("Vault not authenticated");
        }

        try {
            $this->httpClient->delete("/v1/secret/data/{$path}", [
                'headers' => [
                    'X-Vault-Token' => $this->token,
                ],
            ]);

            // Invalidate cache
            unset($this->cache["secret:{$path}"]);

            return true;
        } catch (GuzzleException $e) {
            error_log("Vault error deleting {$path}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * List secrets at a path
     */
    public function listSecrets(string $path): array
    {
        if (!$this->token) {
            throw new \RuntimeException("Vault not authenticated");
        }

        try {
            $response = $this->httpClient->request('LIST', "/v1/secret/metadata/{$path}", [
                'headers' => [
                    'X-Vault-Token' => $this->token,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['data']['keys'] ?? [];
        } catch (GuzzleException $e) {
            error_log("Vault error listing {$path}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clear the local cache
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        $total = count($this->cache);
        $valid = 0;
        $expired = 0;

        foreach ($this->cache as $entry) {
            if ($entry['expires'] > time()) {
                $valid++;
            } else {
                $expired++;
            }
        }

        return [
            'total_entries' => $total,
            'valid_entries' => $valid,
            'expired_entries' => $expired,
            'ttl_seconds' => $this->cacheTtl,
        ];
    }
}
