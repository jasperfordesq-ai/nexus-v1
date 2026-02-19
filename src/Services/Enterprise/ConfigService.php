<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Services\Enterprise;

use Nexus\Core\VaultClient;

/**
 * Unified Configuration Service
 *
 * Provides a single interface for accessing configuration values,
 * with support for HashiCorp Vault secrets management and fallback to environment variables.
 */
class ConfigService
{
    private static ?ConfigService $instance = null;
    private ?VaultClient $vault = null;
    private bool $useVault;
    private array $localCache = [];

    private function __construct()
    {
        $this->useVault = strtolower(getenv('USE_VAULT') ?: 'false') === 'true';

        if ($this->useVault) {
            $this->initializeVault();
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize Vault connection
     */
    private function initializeVault(): void
    {
        try {
            $this->vault = new VaultClient();

            // Try AppRole authentication
            $roleId = getenv('VAULT_ROLE_ID');
            $secretId = getenv('VAULT_SECRET_ID');

            if ($roleId && $secretId) {
                $this->vault->authenticateAppRole($roleId, $secretId);
            } elseif ($token = getenv('VAULT_TOKEN')) {
                // Fallback to token authentication (development)
                $this->vault->authenticateToken($token);
            }
        } catch (\Exception $e) {
            error_log("Failed to initialize Vault: " . $e->getMessage());
            $this->vault = null;
            $this->useVault = false;
        }
    }

    /**
     * Check if Vault is being used
     */
    public function isUsingVault(): bool
    {
        return $this->useVault && $this->vault !== null && $this->vault->isAvailable();
    }

    /**
     * Get database configuration
     */
    public function getDatabase(): array
    {
        if ($this->isUsingVault()) {
            try {
                $env = getenv('APP_ENV') ?: 'production';
                return $this->vault->getSecret("nexus/database/{$env}");
            } catch (\Exception $e) {
                error_log("Vault database config error, falling back to env: " . $e->getMessage());
            }
        }

        return [
            'host' => getenv('DB_HOST') ?: 'localhost',
            'port' => getenv('DB_PORT') ?: '3306',
            'database' => getenv('DB_DATABASE') ?: 'nexus',
            'username' => getenv('DB_USERNAME') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ];
    }

    /**
     * Get Redis configuration
     */
    public function getRedis(): array
    {
        if ($this->isUsingVault()) {
            try {
                return $this->vault->getSecret('nexus/redis');
            } catch (\Exception $e) {
                error_log("Vault Redis config error: " . $e->getMessage());
            }
        }

        return [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('REDIS_PORT') ?: 6379),
            'password' => getenv('REDIS_PASSWORD') ?: null,
            'database' => (int) (getenv('REDIS_DATABASE') ?: 0),
        ];
    }

    /**
     * Get Pusher configuration
     */
    public function getPusher(): array
    {
        if ($this->isUsingVault()) {
            try {
                return $this->vault->getSecret('nexus/api-keys/pusher');
            } catch (\Exception $e) {
                error_log("Vault Pusher config error: " . $e->getMessage());
            }
        }

        return [
            'app_id' => getenv('PUSHER_APP_ID') ?: '',
            'key' => getenv('PUSHER_APP_KEY') ?: '',
            'secret' => getenv('PUSHER_APP_SECRET') ?: '',
            'cluster' => getenv('PUSHER_APP_CLUSTER') ?: 'eu',
        ];
    }

    /**
     * Get OpenAI configuration
     */
    public function getOpenAI(): array
    {
        if ($this->isUsingVault()) {
            try {
                return $this->vault->getSecret('nexus/api-keys/openai');
            } catch (\Exception $e) {
                error_log("Vault OpenAI config error: " . $e->getMessage());
            }
        }

        return [
            'api_key' => getenv('OPENAI_API_KEY') ?: '',
            'model' => getenv('OPENAI_MODEL') ?: 'gpt-4',
            'max_tokens' => (int) (getenv('OPENAI_MAX_TOKENS') ?: 2000),
        ];
    }

    /**
     * Get Anthropic configuration
     */
    public function getAnthropic(): array
    {
        if ($this->isUsingVault()) {
            try {
                return $this->vault->getSecret('nexus/api-keys/anthropic');
            } catch (\Exception $e) {
                error_log("Vault Anthropic config error: " . $e->getMessage());
            }
        }

        return [
            'api_key' => getenv('ANTHROPIC_API_KEY') ?: '',
            'model' => getenv('ANTHROPIC_MODEL') ?: 'claude-3-sonnet-20240229',
        ];
    }

    /**
     * Get Google Maps configuration
     */
    public function getGoogleMaps(): array
    {
        if ($this->isUsingVault()) {
            try {
                return $this->vault->getSecret('nexus/api-keys/google-maps');
            } catch (\Exception $e) {
                error_log("Vault Google Maps config error: " . $e->getMessage());
            }
        }

        return [
            'api_key' => getenv('GOOGLE_MAPS_API_KEY') ?: '',
        ];
    }

    /**
     * Get Firebase configuration
     */
    public function getFirebase(): array
    {
        if ($this->isUsingVault()) {
            try {
                $config = $this->vault->getSecret('nexus/api-keys/firebase');
                if (isset($config['service_account'])) {
                    $config['service_account'] = json_decode($config['service_account'], true);
                }
                return $config;
            } catch (\Exception $e) {
                error_log("Vault Firebase config error: " . $e->getMessage());
            }
        }

        // Fallback to file-based service account
        $path = getenv('FIREBASE_SERVICE_ACCOUNT_PATH') ?: __DIR__ . '/../../../firebase-service-account.json';
        $serviceAccount = [];

        if (file_exists($path)) {
            $serviceAccount = json_decode(file_get_contents($path), true) ?: [];
        }

        return [
            'service_account' => $serviceAccount,
            'project_id' => $serviceAccount['project_id'] ?? getenv('FIREBASE_PROJECT_ID') ?: '',
        ];
    }

    /**
     * Get SMTP/Email configuration
     */
    public function getSmtp(): array
    {
        if ($this->isUsingVault()) {
            try {
                return $this->vault->getSecret('nexus/smtp');
            } catch (\Exception $e) {
                error_log("Vault SMTP config error: " . $e->getMessage());
            }
        }

        return [
            'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
            'port' => (int) (getenv('SMTP_PORT') ?: 587),
            'username' => getenv('SMTP_USERNAME') ?: '',
            'password' => getenv('SMTP_PASSWORD') ?: '',
            'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
            'from_address' => getenv('MAIL_FROM_ADDRESS') ?: '',
            'from_name' => getenv('MAIL_FROM_NAME') ?: 'NEXUS',
        ];
    }

    /**
     * Get application encryption key
     */
    public function getAppKey(): string
    {
        if ($this->isUsingVault()) {
            try {
                $data = $this->vault->getSecret('nexus/encryption');
                return $data['app_key'] ?? '';
            } catch (\Exception $e) {
                error_log("Vault app key error: " . $e->getMessage());
            }
        }

        return getenv('APP_KEY') ?: '';
    }

    /**
     * Get any configuration value by path
     */
    public function get(string $path, string $key = null, $default = null)
    {
        // Check local cache
        $cacheKey = "config:{$path}";
        if (!isset($this->localCache[$cacheKey])) {
            if ($this->isUsingVault()) {
                try {
                    $this->localCache[$cacheKey] = $this->vault->getSecret($path);
                } catch (\Exception $e) {
                    $this->localCache[$cacheKey] = [];
                }
            } else {
                $this->localCache[$cacheKey] = [];
            }
        }

        $data = $this->localCache[$cacheKey];

        if ($key === null) {
            return $data ?: $default;
        }

        return $data[$key] ?? $default;
    }

    /**
     * Get environment-specific configuration
     */
    public function getEnv(string $key, $default = null)
    {
        return getenv($key) ?: $default;
    }

    /**
     * Check if running in production
     */
    public function isProduction(): bool
    {
        return getenv('APP_ENV') === 'production';
    }

    /**
     * Check if debug mode is enabled
     */
    public function isDebug(): bool
    {
        return strtolower(getenv('APP_DEBUG') ?: 'false') === 'true';
    }

    /**
     * Clear configuration cache
     */
    public function clearCache(): void
    {
        $this->localCache = [];
        if ($this->vault) {
            $this->vault->clearCache();
        }
    }

    /**
     * Get configuration status for admin dashboard
     */
    public function getStatus(): array
    {
        return [
            'vault_enabled' => $this->useVault,
            'vault_available' => $this->vault?->isAvailable() ?? false,
            'environment' => getenv('APP_ENV') ?: 'unknown',
            'debug_mode' => $this->isDebug(),
            'cache_entries' => count($this->localCache),
            'vault_cache' => $this->vault?->getCacheStats() ?? null,
        ];
    }
}
